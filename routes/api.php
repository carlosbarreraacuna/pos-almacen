<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\StockAdjustmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas de autenticación
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'me']);
    });
});

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Rutas de usuarios
    Route::apiResource('users', UserController::class);
    
    // Ruta para obtener roles disponibles
    Route::get('roles', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Role::with('permissions')->get()
        ]);
    })->middleware('role.permission:,users.view');
    
    // === MÓDULO DE INVENTARIO ===
    
    // Rutas de Productos/Inventario
    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventoryController::class, 'index']);
        Route::post('/', [InventoryController::class, 'store']);
        Route::get('/summary', [InventoryController::class, 'summary']);
        Route::get('/low-stock', [InventoryController::class, 'lowStock']);
        Route::get('/out-of-stock', [InventoryController::class, 'outOfStock']);
        Route::get('/{product}', [InventoryController::class, 'show']);
        Route::put('/{product}', [InventoryController::class, 'update']);
        Route::delete('/{product}', [InventoryController::class, 'destroy']);
        Route::post('/{product}/adjust-stock', [InventoryController::class, 'adjustStock']);
        Route::get('/{product}/stock-movements', [InventoryController::class, 'stockMovements']);
    });
    
    // Rutas de Categorías
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::get('/select', [CategoryController::class, 'select']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });
    
    // Rutas de Marcas
    Route::prefix('brands')->group(function () {
        Route::get('/', [BrandController::class, 'index']);
        Route::post('/', [BrandController::class, 'store']);
        Route::get('/select', [BrandController::class, 'select']);
        Route::get('/{brand}', [BrandController::class, 'show']);
        Route::put('/{brand}', [BrandController::class, 'update']);
        Route::delete('/{brand}', [BrandController::class, 'destroy']);
        Route::get('/{brand}/statistics', [BrandController::class, 'statistics']);
    });
    
    // Rutas de Ubicaciones
    Route::prefix('locations')->group(function () {
        Route::get('/', [LocationController::class, 'index']);
        Route::post('/', [LocationController::class, 'store']);
        Route::get('/warehouses', [LocationController::class, 'warehouses']);
        Route::get('/tree', [LocationController::class, 'tree']);
        Route::get('/select', [LocationController::class, 'select']);
        Route::get('/types', [LocationController::class, 'types']);
        Route::get('/{location}', [LocationController::class, 'show']);
        Route::put('/{location}', [LocationController::class, 'update']);
        Route::delete('/{location}', [LocationController::class, 'destroy']);
        Route::get('/{location}/statistics', [LocationController::class, 'statistics']);
    });
    
    // Rutas para Clientes
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/search', [CustomerController::class, 'search']);
        Route::get('/types', [CustomerController::class, 'getCustomerTypes']);
        Route::get('/payment-terms', [CustomerController::class, 'getPaymentTerms']);
        Route::get('/{customer}', [CustomerController::class, 'show']);
        Route::put('/{customer}', [CustomerController::class, 'update']);
        Route::delete('/{customer}', [CustomerController::class, 'destroy']);
        Route::patch('/{customer}/toggle-status', [CustomerController::class, 'toggleStatus']);
        Route::get('/{customer}/stats', [CustomerController::class, 'stats']);
    });

    // Rutas para Ventas (POS)
    Route::prefix('sales')->middleware('sales.permission')->group(function () {
        Route::get('/', [SaleController::class, 'index']);
        Route::post('/', [SaleController::class, 'store']);
        Route::get('/stats', [SaleController::class, 'stats']);
        Route::get('/payment-methods', [SaleController::class, 'salesByPaymentMethod']);
        Route::get('/{sale}', [SaleController::class, 'show']);
        Route::put('/{sale}', [SaleController::class, 'update']);
        Route::patch('/{sale}/complete', [SaleController::class, 'complete']);
        Route::patch('/{sale}/cancel', [SaleController::class, 'cancel']);
    });

    // Rutas para Pagos
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('/stats', [PaymentController::class, 'stats']);
        Route::get('/methods', [PaymentController::class, 'getPaymentMethods']);
        Route::get('/by-method', [PaymentController::class, 'paymentsByMethod']);
        Route::get('/sale/{sale}', [PaymentController::class, 'getBySale']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
        Route::put('/{payment}', [PaymentController::class, 'update']);
        Route::patch('/{payment}/complete', [PaymentController::class, 'complete']);
        Route::patch('/{payment}/cancel', [PaymentController::class, 'cancel']);
    });

    // === MÓDULO DE GESTIÓN DE ALMACÉN ===
    
    // Módulo de Gestión de Almacén
    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index']);
        Route::post('/', [WarehouseController::class, 'store']);
        Route::get('/search', [WarehouseController::class, 'search']);
        Route::get('/stats', [WarehouseController::class, 'stats']);
        Route::get('/types', [WarehouseController::class, 'getWarehouseTypes']);
        Route::get('/security-levels', [WarehouseController::class, 'getSecurityLevels']);
        Route::get('/{warehouse}', [WarehouseController::class, 'show']);
        Route::put('/{warehouse}', [WarehouseController::class, 'update']);
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy']);
        Route::patch('/{warehouse}/toggle-status', [WarehouseController::class, 'toggleStatus']);
        Route::patch('/{warehouse}/set-main', [WarehouseController::class, 'setAsMain']);
        Route::get('/{warehouse}/locations', [WarehouseController::class, 'getLocations']);
        Route::patch('/{warehouse}/update-utilization', [WarehouseController::class, 'updateUtilization']);
    });

    // Transferencias de Stock
    Route::prefix('stock-transfers')->group(function () {
        Route::get('/', [StockTransferController::class, 'index']);
        Route::post('/', [StockTransferController::class, 'store']);
        Route::get('/stats', [StockTransferController::class, 'stats']);
        Route::get('/statuses', [StockTransferController::class, 'getStatuses']);
        Route::get('/types', [StockTransferController::class, 'getTypes']);
        Route::get('/priorities', [StockTransferController::class, 'getPriorities']);
        Route::get('/{stockTransfer}', [StockTransferController::class, 'show']);
        Route::put('/{stockTransfer}', [StockTransferController::class, 'update']);
        Route::patch('/{stockTransfer}/approve', [StockTransferController::class, 'approve']);
        Route::patch('/{stockTransfer}/start', [StockTransferController::class, 'startTransfer']);
        Route::patch('/{stockTransfer}/complete', [StockTransferController::class, 'complete']);
        Route::patch('/{stockTransfer}/cancel', [StockTransferController::class, 'cancel']);
    });

    // Ajustes de Inventario
    Route::prefix('stock-adjustments')->group(function () {
        Route::get('/', [StockAdjustmentController::class, 'index']);
        Route::post('/', [StockAdjustmentController::class, 'store']);
        Route::get('/current-stock', [StockAdjustmentController::class, 'getCurrentStock']);
        Route::post('/generate-from-count', [StockAdjustmentController::class, 'generateFromPhysicalCount']);
        Route::get('/stats', [StockAdjustmentController::class, 'stats']);
        Route::get('/types', [StockAdjustmentController::class, 'getTypes']);
        Route::get('/reasons', [StockAdjustmentController::class, 'getReasons']);
        Route::get('/statuses', [StockAdjustmentController::class, 'getStatuses']);
        Route::get('/{stockAdjustment}', [StockAdjustmentController::class, 'show']);
        Route::put('/{stockAdjustment}', [StockAdjustmentController::class, 'update']);
        Route::delete('/{stockAdjustment}', [StockAdjustmentController::class, 'destroy']);
        Route::patch('/{stockAdjustment}/submit', [StockAdjustmentController::class, 'submit']);
        Route::patch('/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve']);
        Route::patch('/{stockAdjustment}/apply', [StockAdjustmentController::class, 'apply']);
        Route::patch('/{stockAdjustment}/cancel', [StockAdjustmentController::class, 'cancel']);
    });

    // === MÓDULO DE VENTAS AVANZADO ===
    
    // Plantillas de Venta
    Route::prefix('sale-templates')->middleware('sales.permission')->group(function () {
        Route::get('/', [\App\Http\Controllers\SaleTemplateController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\SaleTemplateController::class, 'store']);
        Route::get('/most-used', [\App\Http\Controllers\SaleTemplateController::class, 'mostUsed']);
        Route::get('/{saleTemplate}', [\App\Http\Controllers\SaleTemplateController::class, 'show']);
        Route::put('/{saleTemplate}', [\App\Http\Controllers\SaleTemplateController::class, 'update']);
        Route::delete('/{saleTemplate}', [\App\Http\Controllers\SaleTemplateController::class, 'destroy']);
        Route::post('/{saleTemplate}/create-sale', [\App\Http\Controllers\SaleTemplateController::class, 'createSale']);
        Route::patch('/{saleTemplate}/toggle-active', [\App\Http\Controllers\SaleTemplateController::class, 'toggleActive']);
        Route::post('/{saleTemplate}/duplicate', [\App\Http\Controllers\SaleTemplateController::class, 'duplicate']);
    });
    
    // Facturación Electrónica DIAN
    Route::prefix('electronic-invoices')->middleware('sales.permission')->group(function () {
        Route::get('/', [\App\Http\Controllers\ElectronicInvoiceController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\ElectronicInvoiceController::class, 'store']);
        Route::get('/statistics', [\App\Http\Controllers\ElectronicInvoiceController::class, 'statistics']);
        Route::get('/{electronicInvoice}', [\App\Http\Controllers\ElectronicInvoiceController::class, 'show']);
        Route::put('/{electronicInvoice}', [\App\Http\Controllers\ElectronicInvoiceController::class, 'update']);
        Route::delete('/{electronicInvoice}', [\App\Http\Controllers\ElectronicInvoiceController::class, 'destroy']);
        Route::post('/{electronicInvoice}/send-to-dian', [\App\Http\Controllers\ElectronicInvoiceController::class, 'sendToDian']);
        Route::post('/{electronicInvoice}/generate-pdf', [\App\Http\Controllers\ElectronicInvoiceController::class, 'generatePdf']);
        Route::post('/{electronicInvoice}/generate-xml', [\App\Http\Controllers\ElectronicInvoiceController::class, 'generateXml']);
    });
    
    // Historial de Ventas y Análisis
    Route::prefix('sale-history')->group(function () {
        Route::get('/', [\App\Http\Controllers\SaleHistoryController::class, 'index']);
        Route::get('/statistics', [\App\Http\Controllers\SaleHistoryController::class, 'statistics']);
        Route::get('/frequent-customers', [\App\Http\Controllers\SaleHistoryController::class, 'frequentCustomers']);
        Route::get('/top-products', [\App\Http\Controllers\SaleHistoryController::class, 'topProducts']);
        Route::get('/upcoming-needs', [\App\Http\Controllers\SaleHistoryController::class, 'upcomingNeeds']);
        Route::get('/purchase-patterns', [\App\Http\Controllers\SaleHistoryController::class, 'purchasePatterns']);
        Route::get('/export', [\App\Http\Controllers\SaleHistoryController::class, 'export']);
        Route::get('/customers/{customer}/recommendations', [\App\Http\Controllers\SaleHistoryController::class, 'customerRecommendations']);
        Route::get('/{saleHistory}', [\App\Http\Controllers\SaleHistoryController::class, 'show']);
        Route::post('/update-from-sale', [\App\Http\Controllers\SaleHistoryController::class, 'updateFromSale']);
        Route::delete('/cleanup', [\App\Http\Controllers\SaleHistoryController::class, 'cleanup']);
    });

    // === MÓDULOS PENDIENTES ===
    // TODO: Implementar rutas de compras y proveedores
    // TODO: Implementar rutas de reportes y analytics
    // TODO: Implementar rutas de configuración
    // TODO: Implementar rutas de usuarios y permisos
});