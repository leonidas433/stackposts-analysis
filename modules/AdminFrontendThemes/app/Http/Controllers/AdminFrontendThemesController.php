<?php

namespace Modules\AdminFrontendThemes\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Modules\AdminFrontendThemes\Services\FrontendThemeService;
use Modules\AdminFrontendThemes\Services\ThemeManifestValidator;
use ZipArchive;

class AdminFrontendThemesController extends Controller
{
    /** Valid theme directory name (also blocks path tricks in ids). */
    protected const THEME_ID_PATTERN = '/^[a-z0-9][a-z0-9_-]{1,49}$/';

    /** Reserved directory names inside resources/themes/. */
    protected const RESERVED_NAMES = ['_shared'];

    protected const MAX_ZIP_ENTRIES = 5000;

    protected const MAX_UNCOMPRESSED_BYTES = 300 * 1024 * 1024; // 300MB (zip-bomb guard)

    public function index()
    {
        $themes = app(FrontendThemeService::class)->all();
        $activeTheme = get_option('frontend_theme', env('THEME_FRONTEND'));

        return view('adminfrontendthemes::index', compact('themes', 'activeTheme'));
    }

    public function setDefault(Request $request)
    {
        $themeId = $request->input('id');
        $service = app(FrontendThemeService::class);
        $theme = $service->get($themeId);
        if (! $theme) {
            return response()->json([
                'status' => 0,
                'message' => __('Theme does not exist or is unavailable.'),
            ]);
        }
        if (! empty($theme['validation_errors'])) {
            return response()->json([
                'status' => 0,
                'message' => __('This theme has an invalid theme.json and cannot be activated:').' '.implode(' ', $theme['validation_errors']),
            ]);
        }
        update_option('frontend_theme', $themeId);

        return response()->json([
            'status' => 1,
            'message' => __('Theme activated successfully!'),
        ]);
    }

    public function import(Request $request)
    {
        $files = $request->file('files');
        $file = is_array($files) ? ($files[0] ?? null) : $files;

        if (! $file || ! $file->isValid()
            || strtolower($file->getClientOriginalExtension()) !== 'zip'
            || $file->getSize() > 50 * 1024 * 1024) {
            return response()->json([
                'status' => 0,
                'message' => __('Invalid file. A .zip of at most 50MB is required.'),
            ]);
        }

        $zipName = uniqid('theme_', true).'.zip';
        $tmpZipPath = storage_path('app/tmp/'.$zipName);
        $file->move(storage_path('app/tmp'), $zipName);

        $stagingPath = null;

        try {
            $zip = new ZipArchive;
            if ($zip->open($tmpZipPath) !== true) {
                return $this->importError(__('Could not open the zip file.'));
            }

            // Pre-scan every entry BEFORE extracting anything.
            $error = $this->scanZip($zip, $mainDir, $totalBytes);
            if ($error !== null) {
                $zip->close();

                return $this->importError($error);
            }

            if (in_array($mainDir, self::RESERVED_NAMES, true)
                || file_exists(base_path('resources/themes/guest/'.$mainDir))) {
                $zip->close();

                return $this->importError(__('Theme already exists or uses a reserved name.'));
            }

            // Extract to an isolated staging directory, never directly to the
            // themes tree.
            $stagingPath = storage_path('app/tmp/theme_staging_'.uniqid());
            File::ensureDirectoryExists($stagingPath, 0775);
            $extracted = $zip->extractTo($stagingPath);
            $zip->close();

            if (! $extracted) {
                return $this->importError(__('Could not extract the zip file.'));
            }

            $errors = app(ThemeManifestValidator::class)
                ->validateFile($stagingPath.'/'.$mainDir.'/theme.json');
            if ($errors !== []) {
                return $this->importError(__('Invalid theme.json:').' '.implode(' ', $errors));
            }

            $themesRoot = base_path('resources/themes/guest');
            File::ensureDirectoryExists($themesRoot, 0775);
            File::moveDirectory($stagingPath.'/'.$mainDir, $themesRoot.'/'.$mainDir);

            return response()->json([
                'status' => 1,
                'message' => __('Theme imported successfully!'),
                'theme' => $mainDir,
            ]);
        } finally {
            if ($stagingPath && file_exists($stagingPath)) {
                File::deleteDirectory($stagingPath);
            }
            if (file_exists($tmpZipPath)) {
                unlink($tmpZipPath);
            }
        }
    }

    public function destroy(Request $request)
    {
        $id = (string) $request->input('id');
        $service = app(FrontendThemeService::class);

        if (! preg_match(self::THEME_ID_PATTERN, $id) || ! $service->get($id)) {
            return response()->json([
                'status' => 0,
                'message' => __('Theme does not exist or is unavailable.'),
            ]);
        }
        if ($id === get_option('frontend_theme', env('THEME_FRONTEND'))) {
            return response()->json([
                'status' => 0,
                'message' => __('You cannot delete the active theme.'),
            ]);
        }
        if ($service->isParentOfAnother($id)) {
            return response()->json([
                'status' => 0,
                'message' => __('This theme is the parent of another installed theme and cannot be deleted.'),
            ]);
        }

        File::deleteDirectory(base_path('resources/themes/guest/'.$id));

        return response()->json([
            'status' => 1,
            'message' => __('Theme deleted successfully!'),
        ]);
    }

    /**
     * Validate every zip entry (path traversal, size, single sane root dir).
     * Sets $mainDir and $totalBytes by reference; returns an error message or
     * null when the archive is safe.
     */
    protected function scanZip(ZipArchive $zip, ?string &$mainDir, ?int &$totalBytes): ?string
    {
        if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_ZIP_ENTRIES) {
            return __('The zip file is empty or contains too many entries.');
        }

        $mainDir = null;
        $totalBytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $stat = $zip->statIndex($i);

            if ($name === false || $stat === false) {
                return __('Could not read the zip file.');
            }
            if (str_contains($name, "\0") || str_contains($name, '..')
                || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:/', $name)) {
                return __('The zip file contains unsafe paths.');
            }

            $root = explode('/', $name, 2)[0];
            if ($mainDir === null) {
                if (! preg_match(self::THEME_ID_PATTERN, $root)) {
                    return __('The theme folder name inside the zip is invalid (use lowercase letters, numbers, - and _).');
                }
                $mainDir = $root;
            } elseif ($root !== $mainDir) {
                return __('The zip file must contain a single theme folder at its root.');
            }

            $totalBytes += (int) $stat['size'];
            if ($totalBytes > self::MAX_UNCOMPRESSED_BYTES) {
                return __('The uncompressed theme is too large.');
            }
        }

        return null;
    }

    protected function importError(string $message)
    {
        return response()->json([
            'status' => 0,
            'message' => $message,
        ]);
    }
}
