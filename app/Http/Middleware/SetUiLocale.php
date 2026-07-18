<?php

namespace App\Http\Middleware;

use App\Models\Option;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetUiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(Option::uiLanguage()->value);

        return $next($request);
    }
}
