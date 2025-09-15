<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckSalesPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = null): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $user = Auth::user();
        
        // Si no se especifica permiso, verificar permisos básicos de ventas
        if (!$permission) {
            $permission = $this->getPermissionFromRoute($request);
        }

        // Verificar si el usuario tiene el permiso requerido
        if (!$user->can($permission)) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción',
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }

    /**
     * Obtener el permiso requerido basado en la ruta
     */
    private function getPermissionFromRoute(Request $request): string
    {
        $method = $request->method();
        $routeName = $request->route()->getName();
        
        // Mapear métodos HTTP a permisos
        $permissionMap = [
            'GET' => 'sales.view',
            'POST' => 'sales.create',
            'PUT' => 'sales.edit',
            'PATCH' => 'sales.edit',
            'DELETE' => 'sales.delete'
        ];

        // Permisos específicos para ciertas rutas
        $specificPermissions = [
            'sales.stats' => 'sales.reports',
            'sales.complete' => 'sales.complete',
            'sales.cancel' => 'sales.cancel',
            'sale-templates.create-sale' => 'sales.create',
            'electronic-invoices.send-to-dian' => 'sales.electronic_invoice'
        ];

        // Verificar permisos específicos primero
        if ($routeName && isset($specificPermissions[$routeName])) {
            return $specificPermissions[$routeName];
        }

        // Usar mapeo por método HTTP
        return $permissionMap[$method] ?? 'sales.view';
    }
}
