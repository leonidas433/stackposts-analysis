<?php

namespace App\Translation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\FileLoader;

class CustomFileLoader extends FileLoader
{
    protected $mergedTranslations;

    public function loadJsonPaths($locale)
    {
        return Cache::remember("merged_translations_{$locale}", now()->addDay(), function () use ($locale) {
            $langFile = resource_path("lang/{$locale}.json");
            if (file_exists($langFile)) {
                $translations = json_decode(file_get_contents($langFile), true);

                return is_array($translations) ? $translations : [];
            }

            return [];
        });
    }
}
