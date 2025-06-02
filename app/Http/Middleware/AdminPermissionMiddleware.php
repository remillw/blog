<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

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
            return response()->json([
                'error' => 'Non authentifié',
                'message' => 'Vous devez être connecté pour accéder à cette ressource.'
            ], 401);
        }

        // Vérifier si l'utilisateur est actif
        if (!$user->is_active) {
            return response()->json([
                'error' => 'Compte désactivé',
                'message' => 'Votre compte a été désactivé.'
            ], 403);
        }

        // Mettre à jour la dernière activité
        $user->updateLastActivity();

        // Si aucune permission spécifique, vérifier juste si c'est un modérateur+
        if (!$permission) {
            if (!$user->isModerator()) {
                return response()->json([
                    'error' => 'Permissions insuffisantes',
                    'message' => 'Vous n\'avez pas les permissions nécessaires.',
                    'required_role' => 'moderator'
                ], 403);
            }
            return $next($request);
        }

        // Vérifier la permission spécifique
        if (!$user->hasPermission($permission)) {
            return response()->json([
                'error' => 'Permission refusée',
                'message' => "Vous n'avez pas la permission '{$permission}'.",
                'user_role' => $user->role,
                'user_permissions' => $user->getAllPermissions(),
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
