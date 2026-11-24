<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Restrict the request to the supplied roles.
     *
     * Usage: ->middleware('role:chief_engineer,technician')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $allowedRoles = array_map(
            fn (string $role): UserRole => UserRole::from($role),
            $roles,
        );

        if (! $user->hasRole(...$allowedRoles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
