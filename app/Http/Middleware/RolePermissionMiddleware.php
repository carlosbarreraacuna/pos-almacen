<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RolePermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $role
     * @param  string|null  $permission
     */
    public function handle(Request $request, Closure $next, ?string $role = null, ?string $permission = null): Response
    {
        // Verificar si el usuario está autenticado
        if (!Auth::check()) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        $user = Auth::user();

        // Verificar rol si se especifica
        if ($role && !$user->hasRole($role)) {
            return response()->json([
                'message' => 'No tienes el rol requerido para acceder a este recurso'
            ], 403);
        }

        // Verificar permiso si se especifica
        if ($permission && !$user->hasPermission($permission)) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción'
            ], 403);
        }

        return $next($request);
    }
}
