<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs([
            'password.first.edit',
            'password.update',
            'logout',
        ])) {
            return $next($request);
        }

        return to_route('password.first.edit')->with(
            'warning',
            'Anda wajib mengganti password sementara sebelum menggunakan fitur lain.',
        );
    }
}
