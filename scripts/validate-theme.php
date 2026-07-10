<?php

/**
 * Standalone theme validator (no framework needed).
 *
 * Usage:  php scripts/validate-theme.php <type>/<name>
 *         php scripts/validate-theme.php guest/mitema
 *
 * Checks the same contract as Modules\AdminFrontendThemes\Services\
 * ThemeManifestValidator plus the structural rules from
 * docs/guia-crear-un-tema.md, so themes can be validated from the CLI,
 * from CI, or by agents before opening a PR. Exit code 0 = valid.
 */
$theme = $argv[1] ?? null;
if (! $theme || ! preg_match('#^(guest|app)/[a-z0-9][a-z0-9_-]{1,49}$#', $theme)) {
    fwrite(STDERR, "Usage: php scripts/validate-theme.php <guest|app>/<name>\n");
    exit(2);
}

$root = dirname(__DIR__);
$path = "$root/resources/themes/$theme";
$errors = [];
$warnings = [];

if (! is_dir($path)) {
    fwrite(STDERR, "Theme directory not found: $path\n");
    exit(2);
}

// --- composer.json (identity + inheritance, read by hexadog) ---------------
$composer = json_decode((string) @file_get_contents("$path/composer.json"), true);
if (! is_array($composer)) {
    $errors[] = 'composer.json is missing or not valid JSON.';
} else {
    if (($composer['name'] ?? null) !== $theme) {
        $errors[] = "composer.json name must be \"$theme\" (found: ".json_encode($composer['name'] ?? null).').';
    }
    if (($composer['type'] ?? null) !== 'laravel-theme') {
        $errors[] = 'composer.json type must be "laravel-theme".';
    }
    if (isset($composer['extra']['laravel-theme'])) {
        $errors[] = 'composer.json uses the legacy extra.laravel-theme key; use extra.theme (hexadog ignores the former).';
    }
    if (! array_key_exists('theme', $composer['extra'] ?? [])) {
        $warnings[] = 'composer.json has no extra.theme key (add "extra": {"theme": {}} or declare a parent).';
    }
    $parent = $composer['extra']['theme']['parent'] ?? null;
    if ($parent !== null) {
        if ($parent === $theme) {
            $errors[] = 'A theme cannot be its own parent.';
        } elseif (! is_dir("$root/resources/themes/$parent")) {
            $errors[] = "Declared parent \"$parent\" is not installed.";
        }
    }
}

// --- theme.json (admin selector manifest) -----------------------------------
$manifest = json_decode((string) @file_get_contents("$path/theme.json"), true);
if (! is_array($manifest)) {
    $errors[] = 'theme.json is missing or not valid JSON.';
} else {
    foreach (['name', 'version', 'description'] as $field) {
        if (! isset($manifest[$field]) || ! is_string($manifest[$field]) || trim($manifest[$field]) === '') {
            $errors[] = "theme.json: \"$field\" is required (non-empty string).";
        }
    }
    if (isset($manifest['version']) && is_string($manifest['version']) && ! preg_match('/^\d+\.\d+(\.\d+)?$/', $manifest['version'])) {
        $errors[] = 'theme.json: "version" must match X.Y or X.Y.Z.';
    }
    if (isset($manifest['description']) && is_string($manifest['description']) && mb_strlen($manifest['description']) > 500) {
        $errors[] = 'theme.json: "description" must be at most 500 characters.';
    }
    if (isset($manifest['preview'])) {
        if (! is_string($manifest['preview']) || ! preg_match('/^[\w\-. ]+\.(png|jpe?g|webp)$/i', $manifest['preview'])) {
            $errors[] = 'theme.json: "preview" must be a bare png/jpg/webp filename (no paths).';
        } elseif (! is_file("$path/{$manifest['preview']}")) {
            $warnings[] = "theme.json: preview file \"{$manifest['preview']}\" does not exist (the selector will show 'No Preview').";
        }
    } else {
        $warnings[] = 'theme.json: no "preview" set; add preview.png for a proper selector card.';
    }
    if (isset($manifest['tags']) && (! is_array($manifest['tags']) || count($manifest['tags']) > 20)) {
        $errors[] = 'theme.json: "tags" must be an array of at most 20 strings.';
    }
    if (isset($manifest['sort']) && ! is_int($manifest['sort'])) {
        $errors[] = 'theme.json: "sort" must be an integer.';
    }
    $manifestParent = $manifest['parent'] ?? null;
    $composerParent = $composer['extra']['theme']['parent'] ?? null;
    if ($manifestParent !== null && $manifestParent !== $composerParent) {
        $warnings[] = 'theme.json "parent" differs from composer.json extra.theme.parent (composer.json is the one that matters).';
    }
}

// --- structure ---------------------------------------------------------------
$hasParent = ! empty($composer['extra']['theme']['parent'] ?? null);
$hasViews = is_dir("$path/resources/views");
$hasLayout = is_file("$path/resources/views/layouts/app.blade.php");
$hasCssEntry = is_file("$path/assets/css/app.css");
$hasJsEntry = is_file("$path/assets/js/app.js");
$hasManifestBuild = is_file("$path/public/.vite/manifest.json");

if (! $hasParent && ! $hasLayout) {
    $errors[] = 'Standalone theme (no parent) must ship resources/views/layouts/app.blade.php.';
}
if (! $hasParent && ! $hasCssEntry) {
    $warnings[] = 'Standalone theme has no assets/css/app.css (nothing to build).';
}
if (($hasCssEntry || $hasJsEntry) && ! $hasManifestBuild) {
    $warnings[] = "Theme has asset sources but no compiled build — run: npm run build --theme=$theme";
}
if ($hasParent && $hasViews) {
    $count = iterator_count(new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator("$path/resources/views", FilesystemIterator::SKIP_DOTS)
    ));
    if ($count > 15) {
        $warnings[] = "Child theme overrides $count view files — check they are not byte-identical copies of the parent (defeats inheritance).";
    }
}

// --- report ------------------------------------------------------------------
foreach ($errors as $e) {
    echo "ERROR   $e\n";
}
foreach ($warnings as $w) {
    echo "WARNING $w\n";
}

if ($errors === []) {
    echo "OK $theme valid".($warnings ? ' ('.count($warnings).' warnings)' : '')."\n";
    exit(0);
}
exit(1);
