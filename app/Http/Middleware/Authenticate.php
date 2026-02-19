<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * This is an API-only application — never redirect to a login route.
     * Always return null so Laravel throws AuthenticationException with no redirect,
     * which the exception handler in bootstrap/app.php converts to a JSON 401.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
