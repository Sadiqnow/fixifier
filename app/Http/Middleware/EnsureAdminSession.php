<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('web');
        if (! $user) {
            return redirect()->route('admin.login');
        }
        abort_unless($user->role?->value === 'admin' && $user->is_active, 403);
        auth()->shouldUse('web');

        return $next($request);
    }
}
