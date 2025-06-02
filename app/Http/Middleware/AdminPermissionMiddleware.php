<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = null): Response
    {
        $user = $request->user();

        // Vérifier si l'utilisateur est connecté
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Non authentifié',
                    'message' => 'Vous devez être connecté pour accéder à cette ressource.'
                ], 401);
            }
            return redirect()->route('login');
        }

        // Si aucune permission spécifique, vérifier juste si c'est un admin
        if (!$permission) {
            if (!$user->isAdmin()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Permissions insuffisantes',
                        'message' => 'Vous n\'avez pas les permissions administrateur nécessaires.'
                    ], 403);
                }
                abort(403, 'Accès refusé');
            }
            return $next($request);
        }

        // Vérifier la permission spécifique
        if (!$user->hasPermissionTo($permission) && !$user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Permission refusée',
                    'message' => "Vous n'avez pas la permission '{$permission}'.",
                    'required_permission' => $permission
                ], 403);
            }
            abort(403, 'Permission manquante: ' . $permission);
        }

        return $next($request);
    }
}
