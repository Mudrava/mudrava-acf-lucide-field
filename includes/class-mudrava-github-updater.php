<?php
/**
 * GitHub Plugin Updater
 *
 * Enables automatic updates for the plugin from GitHub releases.
 *
 * @package Mudrava\LucideField
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin updates from GitHub releases.
 *
 * @since 1.0.0
 */
class Mudrava_GitHub_Updater
{
    /**
     * Plugin slug.
     *
     * @var string
     */
    private string $slug;

    /**
     * Plugin data.
     *
     * @var array
     */
    private array $plugin_data;

    /**
     * GitHub username.
     *
     * @var string
     */
    private string $github_username;

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private string $github_repo;

    /**
     * Plugin file path.
     *
     * @var string
     */
    private string $plugin_file;

    /**
     * Cached GitHub response.
     *
     * @var object|null
     */
    private ?object $github_response = null;

    /**
     * Constructor.
     *
     * @param string $plugin_file     Full path to the main plugin file.
     * @param string $github_username GitHub username or organization.
     * @param string $github_repo     GitHub repository name.
     */
    public function __construct(string $plugin_file, string $github_username, string $github_repo)
    {
        $this->plugin_file = $plugin_file;
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;

        add_action('admin_init', [$this, 'set_plugin_data']);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    /**
     * Set plugin data from the plugin file header.
     *
     * @return void
     */
    public function set_plugin_data(): void
    {
        $this->slug = plugin_basename($this->plugin_file);
        $this->plugin_data = get_plugin_data($this->plugin_file);
    }

    /**
     * Get the latest release info from GitHub.
     *
     * @return object|null Release data or null on failure.
     */
    private function get_github_release(): ?object
    {
        if (null !== $this->github_response) {
            return $this->github_response;
        }

        $transient_key = 'mudrava_github_release_' . $this->github_repo;
        $cached = get_transient($transient_key);

        if (false !== $cached) {
            $this->github_response = $cached;
            return $this->github_response;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version'),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data) || !isset($data->tag_name)) {
            return null;
        }

        $this->github_response = $data;
        set_transient($transient_key, $data, HOUR_IN_SECONDS * 6);

        return $this->github_response;
    }

    /**
     * Check for plugin updates.
     *
     * @param object $transient Plugin update transient.
     * @return object Modified transient.
     */
    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();

        if (null === $release) {
            return $transient;
        }

        $github_version = ltrim($release->tag_name, 'v');
        $current_version = $this->plugin_data['Version'] ?? '0.0.0';

        if (version_compare($github_version, $current_version, '>')) {
            $download_url = $this->get_download_url($release);

            if ($download_url) {
                $transient->response[$this->slug] = (object) [
                    'slug' => dirname($this->slug),
                    'plugin' => $this->slug,
                    'new_version' => $github_version,
                    'url' => $this->plugin_data['PluginURI'] ?? '',
                    'package' => $download_url,
                    'icons' => [],
                    'banners' => [],
                    'tested' => '',
                    'requires_php' => $this->plugin_data['RequiresPHP'] ?? '8.0',
                ];
            }
        }

        return $transient;
    }

    /**
     * Get the download URL for the release.
     *
     * @param object $release GitHub release data.
     * @return string|null Download URL or null.
     */
    private function get_download_url(object $release): ?string
    {
        // Check for a zip asset first
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (str_ends_with($asset->name, '.zip')) {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fall back to the source zip
        return $release->zipball_url ?? null;
    }

    /**
     * Provide plugin information for the WordPress plugin info popup.
     *
     * @param false|object|array $result Plugin info result.
     * @param string             $action API action.
     * @param object             $args   API arguments.
     * @return false|object Plugin info or false.
     */
    public function plugin_info($result, $action, $args)
    {
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (dirname($this->slug) !== ($args->slug ?? '')) {
            return $result;
        }

        $release = $this->get_github_release();

        if (null === $release) {
            return $result;
        }

        $github_version = ltrim($release->tag_name, 'v');

        return (object) [
            'name' => $this->plugin_data['Name'] ?? '',
            'slug' => dirname($this->slug),
            'version' => $github_version,
            'author' => $this->plugin_data['Author'] ?? '',
            'author_profile' => $this->plugin_data['AuthorURI'] ?? '',
            'homepage' => $this->plugin_data['PluginURI'] ?? '',
            'download_link' => $this->get_download_url($release),
            'requires' => $this->plugin_data['RequiresWP'] ?? '6.0',
            'requires_php' => $this->plugin_data['RequiresPHP'] ?? '8.0',
            'tested' => '',
            'last_updated' => $release->published_at ?? '',
            'sections' => [
                'description' => $this->plugin_data['Description'] ?? '',
                'changelog' => $this->parse_changelog($release->body ?? ''),
            ],
        ];
    }

    /**
     * Parse the release body as changelog.
     *
     * @param string $body Release body from GitHub.
     * @return string Formatted changelog HTML.
     */
    private function parse_changelog(string $body): string
    {
        if (empty($body)) {
            return '<p>No changelog available.</p>';
        }

        // Convert markdown to basic HTML
        $body = esc_html($body);
        $body = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $body);
        $body = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $body);
        $body = preg_replace('/^- (.+)$/m', '<li>$1</li>', $body);
        $body = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $body);
        $body = nl2br($body);

        return $body;
    }

    /**
     * Rename the plugin folder after installation.
     *
     * @param bool  $response   Installation response.
     * @param array $hook_extra Extra hook data.
     * @param array $result     Installation result.
     * @return array Modified result.
     */
    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        $plugin_folder = dirname($this->plugin_file);
        $destination = $plugin_folder;

        // Move the extracted folder to the correct location
        $wp_filesystem->move($result['destination'], $destination);
        $result['destination'] = $destination;

        // Reactivate the plugin if it was active
        if (is_plugin_active($this->slug)) {
            activate_plugin($this->slug);
        }

        return $result;
    }
}
