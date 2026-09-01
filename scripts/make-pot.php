<?php
/**
 * Generate the plugin POT file.
 *
 * Scans PHP sources for translation calls and writes a gettext template
 * with translator comments. Usage: php scripts/make-pot.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$files = array(
    $root . '/mudrava-acf-lucide-field.php',
    $root . '/includes/class-mudrava-acf-field-lucide-icon.php',
);

$functions = array(
    '__' => 'echo',
    '_e' => 'echo',
    '_x' => 'echo',
    '_ex' => 'echo',
    'esc_html__' => 'echo',
    'esc_html_e' => 'echo',
    'esc_attr__' => 'echo',
    'esc_attr_e' => 'echo',
);

$entries = array();

$pattern = '/(\/\*\s*Translators:[^\*]*\*\/\s*)?(?:\'[a-zA-Z_]+\'\s*=>\s*)?(' . implode('|', array_map('preg_quote', array_keys($functions))) . ')\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")(?:\s*,\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"))?/s';

foreach ($files as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, sprintf('Missing source file: %s%s', $file, PHP_EOL));
        exit(1);
    }

    $contents = (string) file_get_contents($file);
    $lines = explode("\n", $contents);

    if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $singular = '' !== $match[3][0] ? $match[3][0] : $match[4][0];
            $function = $match[2][0];
            $second = '' !== $match[5][0] ? $match[5][0] : $match[6][0];
            $context = ('_x' === $function || '_ex' === $function) ? $second : '';

            if ('' === $singular) {
                continue;
            }

            $offset = $match[2][1];
            $line = count(explode("\n", substr($contents, 0, $offset)));

            $comment = '';

            if (!empty($match[1][0])) {
                $comment = trim(preg_replace('/\s+/', ' ', str_replace(array('/*', '*/', 'Translators:'), '', $match[1][0])));
            }

            $key = $context . "\x04" . $singular;

            if (!isset($entries[$key])) {
                $entries[$key] = array(
                    'singular' => $singular,
                    'context' => $context,
                    'comment' => $comment,
                    'references' => array(),
                    'functions' => array(),
                );
            }

            $entries[$key]['references'][] = substr(basename($file), 0) . ':' . $line;
            $entries[$key]['functions'][$function] = true;
        }
    }

    unset($lines);
}

ksort($entries);

$header = array(
    'Project-Id-Version: Mudrava Icon Field for ACF with Lucide 1.2.0',
    'Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/mudrava-acf-lucide-field/',
    'POT-Creation-Date: ' . gmdate('Y-m-d H:i+0000'),
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE',
    'Last-Translator: FULL NAME <EMAIL@ADDRESS>',
    'Language-Team: LANGUAGE <LL@li.org>',
    'X-Generator: scripts/make-pot.php',
);

$pot = sprintf( "# Copyright (C) %s Mudrava\n# This file is distributed under the GPL-2.0-or-later license.\n", gmdate('Y') );
$pot .= 'msgid ""' . "\n";
$pot .= 'msgstr ""' . "\n";

foreach ($header as $line) {
    $pot .= '"' . $line . '\\n"' . "\n";
}

foreach ($entries as $entry) {
    $pot .= "\n";

    if (!empty($entry['comment'])) {
        $pot .= '#. ' . $entry['comment'] . "\n";
    }

    $pot .= '#: ' . implode(' ' . "\n#: ", array_slice(array_unique($entry['references']), 0, 5)) . "\n";

    if ('' !== $entry['context']) {
        $pot .= 'msgctxt ' . quote_po($entry['context']) . "\n";
    }

    $pot .= 'msgid ' . quote_po($entry['singular']) . "\n";
    $pot .= 'msgstr ""' . "\n";
}

$out = $root . '/languages/mudrava-acf-lucide-field.pot';

file_put_contents($out, $pot);

printf("Wrote %d strings to %s\n", count($entries), 'languages/mudrava-acf-lucide-field.pot');

/**
 * Quote a gettext string.
 *
 * @param string $value Raw string.
 * @return string Quoted string.
 */
function quote_po(string $value): string
{
    $escaped = str_replace(array('\\', "\n", '"'), array('\\\\', '\n', '\"'), $value);

    return '"' . $escaped . '"';
}
