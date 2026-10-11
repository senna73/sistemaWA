<?php

namespace App\Http\Middleware;

use App\Support\PopCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgendaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowed = $user
            && ($user->isSuperAdmin()
                || $user->can(PopCatalog::PERMISSION_AGENDA)
                || filled($user->work_function));

        abort_unless($allowed, 403);

        return $next($request);
    }
}
