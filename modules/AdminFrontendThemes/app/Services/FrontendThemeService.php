<?php

namespace Modules\AdminFrontendThemes\Services;

use Illuminate\Support\Facades\Log;

class FrontendThemeService
{
    protected $themesPath = 'resources/themes/guest';

    public function all()
    {
        $fullPath = base_path($this->themesPath);

        if (! is_dir($fullPath)) {
            return [];
        }

        $themes = [];

        foreach (scandir($fullPath) as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $themePath = $fullPath.'/'.$dir;

            if (is_dir($themePath)) {
                $jsonPath = $themePath.'/theme.json';
                if (file_exists($jsonPath)) {
                    $data = json_decode(file_get_contents($jsonPath), true);
                    if (is_array($data)) {
                        $data['id'] = $dir;
                        $data['path'] = $themePath;

                        // Lenient validation: legacy/third-party themes stay
                        // listed but flagged, so the selector can warn and
                        // block activation instead of hiding them.
                        $errors = app(ThemeManifestValidator::class)->validate($data);
                        $data['valid'] = $errors === [];
                        $data['validation_errors'] = $errors;
                        if ($errors !== []) {
                            Log::warning("Theme [{$dir}] has an invalid theme.json", ['errors' => $errors]);
                        }

                        $themes[] = $data;
                    }
                }
            }
        }

        usort($themes, function ($a, $b) {
            return ($b['sort'] ?? 0) <=> ($a['sort'] ?? 0);
        });

        return $themes;
    }

    public function get($themeId)
    {
        $fullPath = base_path($this->themesPath.'/'.$themeId.'/theme.json');
        if (! file_exists($fullPath)) {
            return null;
        }
        $data = json_decode(file_get_contents($fullPath), true);
        if (! is_array($data)) {
            return null;
        }
        $data['id'] = $themeId;
        $data['validation_errors'] = app(ThemeManifestValidator::class)->validate($data);
        $data['valid'] = $data['validation_errors'] === [];

        return $data;
    }

    /**
     * True when another installed guest theme declares "guest/<themeId>" as
     * its parent (extra.theme.parent in its composer.json).
     */
    public function isParentOfAnother(string $themeId): bool
    {
        $fullPath = base_path($this->themesPath);

        if (! is_dir($fullPath)) {
            return false;
        }

        foreach (scandir($fullPath) as $dir) {
            if ($dir === '.' || $dir === '..' || $dir === $themeId) {
                continue;
            }
            $composer = $fullPath.'/'.$dir.'/composer.json';
            if (! is_file($composer)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($composer), true);
            if (($data['extra']['theme']['parent'] ?? null) === 'guest/'.$themeId) {
                return true;
            }
        }

        return false;
    }
}
