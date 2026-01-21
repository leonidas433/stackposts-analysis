<?php

use App\Http\Middleware\Authentication;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\Themes;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

if (! class_exists('finfo', false)) {
    if (! defined('FILEINFO_NONE')) {
        define('FILEINFO_NONE', 0);
    }
    if (! defined('FILEINFO_MIME')) {
        define('FILEINFO_MIME', 0);
    }
    if (! defined('FILEINFO_MIME_TYPE')) {
        define('FILEINFO_MIME_TYPE', 0);
    }

    class finfo
    {
        public function __construct(int $flags = FILEINFO_NONE, ?string $magicDatabase = null) {}

        public function file(string $filename): ?string
        {
            return null;
        }

        public function buffer(string $string): ?string
        {
            return null;
        }

        public function set_flags(int $flags): bool
        {
            return true;
        }
    }
}

if (! function_exists('finfo_open')) {
    function finfo_open(int $flags = FILEINFO_NONE, ?string $magic_database = null): finfo
    {
        return new finfo($flags, $magic_database);
    }
}

if (! function_exists('finfo_file')) {
    function finfo_file(finfo $finfo, string $filename): ?string
    {
        return null;
    }
}

if (! function_exists('finfo_buffer')) {
    function finfo_buffer(finfo $finfo, string $string): ?string
    {
        return null;
    }
}

if (! function_exists('finfo_close')) {
    function finfo_close(finfo $finfo): bool
    {
        return true;
    }
}

spl_autoload_register(function ($class) {
    $prefix = 'Modules\\';
    $baseDir = base_path('modules/');

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the class name without the 'Modules\' prefix
    $relativeClass = substr($class, $len);
    $relativeClassPath = str_replace('\\', '/', $relativeClass).'.php';

    // Process the path to add 'app/' after the module name
    $pathParts = explode('/', $relativeClassPath);
    if (count($pathParts) > 1) {
        $moduleName = $pathParts[0];
        $restOfPath = implode('/', array_slice($pathParts, 1));
        $fileWithApp = $baseDir.$moduleName.'/app/'.$restOfPath;

        if (file_exists($fileWithApp)) {
            require $fileWithApp;

            return;
        }
    }

    // Check the path without adding 'app/'
    $fileWithoutApp = $baseDir.$relativeClassPath;
    if (file_exists($fileWithoutApp)) {
        require $fileWithoutApp;
    }
});

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->append(Themes::class);

        $middleware->web(append: [
            Authentication::class,
            SetLocale::class,
            Themes::class,
        ]);

        $middleware->alias([
            'theme' => Themes::class,
            'authentication' => Authentication::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})->create();

return $app;
