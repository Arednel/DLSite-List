<?php

namespace App\Http\Middleware;

use App\Models\Option;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireOptionalStorageAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Option::userAuthenticationEnabled() && Auth::guard('web')->guest()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
