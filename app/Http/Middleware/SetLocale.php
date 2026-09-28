<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['pt', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale', 'pt'));

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'pt';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
