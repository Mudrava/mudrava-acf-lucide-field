import { execFileSync } from 'node:child_process';

const SITE = process.env.WP_SITE_PATH || '/var/folders/8l/qy_hbfsx1lxc2_2_rkn33mzw0000gn/T/opencode/wp-site';
const WP_BIN = process.env.WP_BIN || 'wp';

export function resetFixtures(ids) {
	if (!ids.rocket || !ids.unknown || !ids.strict) {
		throw new Error('Fixture post IDs not resolved (missing ids.json).');
	}

	const php = [
		`update_post_meta(${ids.rocket}, "icon", "rocket");`,
		`update_post_meta(${ids.unknown}, "icon", "totally-removed-icon");`,
		`update_post_meta(${ids.strict}, "icon", "rocket");`,
		`update_post_meta(${ids.strict}, "strict_icon", "totally-removed-icon");`,
		'delete_transient("mudrava_lucide_unknown_values");',
		'delete_transient("mudrava_lucide_invalid_defaults");',
		'echo "reset";',
	].join(' ');

	execFileSync(WP_BIN, ['eval', php, '--path=' + SITE], { stdio: 'pipe' });
}
