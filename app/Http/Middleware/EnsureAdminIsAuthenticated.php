<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('admin')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expirée.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        // L'autorisation des canaux privés passe par $request->user(), et ce
        // projet n'a pas de modèle User. On fournit donc un utilisateur
        // minimal : le contrôle d'accès réel, c'est la ligne ci-dessus.
        $request->setUserResolver(fn () => new GenericUser([
            'id' => 'admin',
            'name' => 'Opérateur',
        ]));

        return $next($request);
    }
}
