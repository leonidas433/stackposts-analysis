<?php

namespace Modules\AdminFrontendThemes\Services;

use Illuminate\Support\Facades\Validator;

/**
 * Formal schema for a theme's theme.json manifest.
 *
 * Required: name, version (semver-ish), description.
 * Optional but validated when present: author, preview, tags, sort, parent.
 *
 * The manifest only feeds the admin theme selector (hexadog reads the
 * theme's composer.json); keep the schema in sync with
 * docs/guia-crear-un-tema.md.
 */
class ThemeManifestValidator
{
    public const RULES = [
        'name' => 'required|string|min:1|max:100',
        'version' => ['required', 'string', 'regex:/^\d+\.\d+(\.\d+)?$/'],
        'description' => 'required|string|min:1|max:500',
        'author' => 'sometimes|string|max:100',
        'preview' => ['sometimes', 'string', 'max:100', 'regex:/^[\w\-. ]+\.(png|jpe?g|webp)$/i'],
        'tags' => 'sometimes|array|max:20',
        'tags.*' => 'string|max:30',
        'sort' => 'sometimes|integer',
        'parent' => 'sometimes|string|max:120',
    ];

    /**
     * @return array<int, string> validation errors (empty array = valid)
     */
    public function validate(array $data): array
    {
        $validator = Validator::make($data, self::RULES);

        return $validator->fails()
            ? $validator->errors()->all()
            : [];
    }

    /**
     * @return array<int, string> validation errors (empty array = valid)
     */
    public function validateFile(string $path): array
    {
        if (! is_file($path)) {
            return [__('theme.json file is missing.')];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return [__('theme.json is not valid JSON.')];
        }

        return $this->validate($data);
    }
}
