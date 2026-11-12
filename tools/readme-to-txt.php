<?php

declare(strict_types=1);

/*
 * Builds readme.txt from wordpress-org/readme.md, the only file to edit.
 * Same generator as Vertex-Systems-Network/wpessential tools/release/readme-to-txt.php
 * (conversion and listing rules unchanged); only the plugin-file lookup is generic.
 *
 *   php tools/readme-to-txt.php           write readme.txt at the repository root
 *   php tools/readme-to-txt.php --check   exit 1 when readme.txt is out of date
 *   php tools/readme-to-txt.php --out=X   write the result to X instead of readme.txt
 *
 * Mapping: "# Name" -> "=== Name ===", "## Section" -> "== Section ==", "### Title" -> "= Title =",
 * "**Header:** value" lines (ending in a "\\" Markdown line break) -> "Header: value", "- item" -> "* item".
 * Everything else is copied.
 */

/** Converts the WordPress.org readme Markdown source to readme.txt format. */
function wpeReadmeMarkdownToTxt(string $markdown): string
{
    $lines = preg_split('/\R/', $markdown);
    if ($lines === false) {
        throw new RuntimeException('readme.md could not be split into lines.');
    }

    $out = [];
    foreach ($lines as $line) {
        $line = rtrim($line);
        // readme.txt puts the header fields directly under the title line.
        if ($line === '' && count($out) === 1) {
            continue;
        }
        if (preg_match('/^# (.+)$/', $line, $match) === 1) {
            $out[] = '=== ' . $match[1] . ' ===';
        } elseif (preg_match('/^## (.+)$/', $line, $match) === 1) {
            $out[] = '== ' . $match[1] . ' ==';
        } elseif (preg_match('/^### (.+)$/', $line, $match) === 1) {
            $out[] = '= ' . $match[1] . ' =';
        } elseif (preg_match('/^\*\*([A-Za-z ]+):\*\* (.+?)\\\\?$/', $line, $match) === 1) {
            $out[] = $match[1] . ': ' . $match[2];
        } elseif (preg_match('/^- (.+)$/', $line, $match) === 1) {
            $out[] = '* ' . $match[1];
        } else {
            $out[] = $line;
        }
    }

    return rtrim(implode("\n", $out)) . "\n";
}

/** Fails with a message when the readme breaks a WordPress.org listing rule this repo can check offline. */
function wpeAssertReadmeTxtRules(string $txt, string $pluginVersion): void
{
    $header = [];
    foreach (preg_split('/\R/', $txt) ?: [] as $index => $line) {
        if ($index === 0) {
            if (preg_match('/^=== .+ ===$/', $line) !== 1) {
                throw new RuntimeException('readme.txt must start with "=== Plugin Name ===".');
            }
            continue;
        }
        if ($line === '') {
            break;
        }
        [$key, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
        $header[$key] = $value;
    }

    foreach (['Contributors', 'Tags', 'Requires at least', 'Tested up to', 'Requires PHP', 'Stable tag', 'License', 'License URI'] as $required) {
        if (($header[$required] ?? '') === '') {
            throw new RuntimeException("readme.txt header is missing \"{$required}\".");
        }
    }
    if ($header['Stable tag'] !== $pluginVersion) {
        throw new RuntimeException("Stable tag {$header['Stable tag']} does not match plugin Version {$pluginVersion}.");
    }
    if (count(array_filter(array_map('trim', explode(',', $header['Tags'])))) > 5) {
        throw new RuntimeException('WordPress.org shows at most 5 tags.');
    }

    // The short description is the paragraph directly after the header block, before any section.
    $blocks = explode("\n\n", $txt, 3);
    $summary = trim($blocks[1] ?? '');
    if ($summary === '' || str_starts_with($summary, '=') || str_contains($summary, "\n") || (function_exists('mb_strlen') ? mb_strlen($summary, 'UTF-8') : preg_match_all('/./us', $summary)) > 150) {
        throw new RuntimeException('The short description must follow the header and be one line of at most 150 characters.');
    }
    foreach (['== Description ==', '== Installation ==', '== Changelog ==', '== External services =='] as $section) {
        if (!str_contains($txt, $section)) {
            throw new RuntimeException("readme.txt is missing the {$section} section.");
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $root = dirname(__DIR__);
    $source = $root . '/wordpress-org/readme.md';
    $markdown = file_get_contents($source);
    $plugin = false;
    foreach (glob($root . '/*.php') ?: [] as $candidate) {
        $contents = (string) file_get_contents($candidate);
        if (preg_match('/^ \* Plugin Name:/m', $contents) === 1) {
            $plugin = $contents;
            break;
        }
    }
    if ($markdown === false || $plugin === false || preg_match('/^ \* Version:\s+(\S+)$/m', $plugin, $version) !== 1) {
        fwrite(STDERR, "Could not read wordpress-org/readme.md or the plugin Version header.\n");
        exit(1);
    }

    try {
        $txt = wpeReadmeMarkdownToTxt($markdown);
        wpeAssertReadmeTxtRules($txt, $version[1]);
    } catch (RuntimeException $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }

    $options = getopt('', ['check', 'out:']);
    $target = is_string($options['out'] ?? null) ? $options['out'] : $root . '/readme.txt';
    if (isset($options['check'])) {
        if ((string) @file_get_contents($root . '/readme.txt') !== $txt) {
            fwrite(STDERR, "readme.txt is out of date. Edit wordpress-org/readme.md and run: php tools/readme-to-txt.php\n");
            exit(1);
        }
        echo "readme.txt matches wordpress-org/readme.md\n";
        exit(0);
    }

    if (file_put_contents($target, $txt) === false) {
        fwrite(STDERR, "Could not write {$target}.\n");
        exit(1);
    }
    echo "Wrote {$target}\n";
}
