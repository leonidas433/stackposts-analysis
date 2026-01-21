<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            \Language::setLang($request);
        } catch (\Throwable $e) {
            app()->setLocale(config('app.locale', 'en'));
        }

        return $next($request);
    }
}
