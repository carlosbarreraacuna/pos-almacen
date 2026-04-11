<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    /**
     * Lista paginada de productos para la tienda
     * GET /api/tienda/productos
     */
    public function productos(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true);

        // Filtro por categoría
        // La tabla categories no tiene columna slug, se compara convirtiendo name a slug en SQL:
        // LOWER(REPLACE(name, ' ', '-')) = 'bandas-de-freno'
        if ($request->filled('categoria')) {
            $catSlug = strtolower(trim($request->categoria));
            $query->whereHas('category', function ($q) use ($catSlug) {
                $q->whereRaw("LOWER(REPLACE(name, ' ', '-')) = ?", [$catSlug])
                  ->orWhereRaw("LOWER(name) LIKE ?", [str_replace('-', '%', $catSlug) . '%']);
            });
        }

        // Filtro por marca/proveedor
        if ($request->has('marca')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->marca . '%');
            });
        }

        // Filtro por modelo de moto compatible
        if ($request->filled('modelo')) {
            $modelo = $request->modelo;
            $query->where('compatible_models', 'ilike', '%' . $modelo . '%');
        }

        // Búsqueda por nombre, código o descripción
        if ($request->has('busqueda')) {
            $search = $request->busqueda;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('sku', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Filtro por disponibilidad
        if ($request->has('disponible') && $request->disponible === 'true') {
            $query->where('stock_quantity', '>', 0);
        }

        // Filtro por rango de precio
        if ($request->has('precio_min')) {
            $query->where('unit_price', '>=', $request->precio_min);
        }
        if ($request->has('precio_max')) {
            $query->where('unit_price', '<=', $request->precio_max);
        }

        // Filtro: solo productos en oferta (descuento > 0)
        if ($request->has('en_oferta') && $request->en_oferta === 'true') {
            $query->where('discount_percentage', '>', 0);
        }

        // Ordenamiento
        $ordenar = $request->get('ordenar', 'recientes');
        switch ($ordenar) {
            case 'precio_asc':
                $query->orderByRaw('unit_price * (1 - discount_percentage / 100.0) ASC');
                break;
            case 'precio_desc':
                $query->orderByRaw('unit_price * (1 - discount_percentage / 100.0) DESC');
                break;
            case 'descuento_desc':
                $query->orderBy('discount_percentage', 'desc');
                break;
            case 'mas_vendidos':
                $query->withCount(['saleItems as total_vendidos' => function ($q) {
                    $q->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                      ->where('sales.status', 'completed');
                }])->orderBy('total_vendidos', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        // Paginación
        $page = $request->get('pagina', 1);
        $perPage = $request->get('por_pagina', 20);

        $productos = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $productos->map(function ($producto) {
                return $this->formatProducto($producto);
            }),
            'pagination' => [
                'total' => $productos->total(),
                'per_page' => $productos->perPage(),
                'current_page' => $productos->currentPage(),
                'last_page' => $productos->lastPage(),
                'from' => $productos->firstItem(),
                'to' => $productos->lastItem(),
            ]
        ]);
    }

    /**
     * Detalle de un producto por código
     * GET /api/tienda/productos/{codigo}
     */
    public function productoDetalle($codigo)
    {
        $query = Product::with(['category', 'brand', 'images'])
            ->where('sku', $codigo);

        if (is_numeric($codigo)) {
            $query->orWhere('id', (int) $codigo);
        }

        $producto = $query->first();

        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatProducto($producto, true)
        ]);
    }

    /**
     * Consulta rápida de stock
     * GET /api/tienda/stock/{codigo}
     */
    public function stock($codigo)
    {
        $query = Product::where('sku', $codigo);

        if (is_numeric($codigo)) {
            $query->orWhere('id', (int) $codigo);
        }

        $producto = $query->first(['stock_quantity', 'min_stock_level']);

        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'stock' => $producto->stock_quantity,
            'disponible' => $producto->stock_quantity > 0,
            'bajo_stock' => $producto->stock_quantity <= $producto->min_stock_level && $producto->stock_quantity > 0
        ]);
    }

    /**
     * Árbol de categorías con conteo de productos
     * GET /api/tienda/categorias
     */
    public function categorias()
    {
        $categorias = Category::with(['children'])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categorias->map(function ($categoria) {
                return $this->formatCategoria($categoria);
            })
        ]);
    }

    /**
     * Crear una orden desde la tienda
     * POST /api/tienda/ordenes
     */
    public function crearOrden(Request $request)
    {
        $validated = $request->validate([
            'cliente_nombre' => 'required|string|max:255',
            'cliente_email' => 'required|email|max:255',
            'cliente_telefono' => 'required|string|max:20',
            'cliente_direccion' => 'required|string',
            'cliente_ciudad' => 'required|string|max:100',
            'cliente_departamento' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:products,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.precio' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'envio' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'metodo_pago' => 'required|string|in:wompi,efectivo,transferencia',
            'referencia_pago' => 'nullable|string',
            'notas' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Crear la orden
            $orden = Order::create([
                'order_number' => 'WEB-' . strtoupper(Str::random(8)),
                'customer_name' => $validated['cliente_nombre'],
                'customer_email' => $validated['cliente_email'],
                'customer_phone' => $validated['cliente_telefono'],
                'shipping_address' => $validated['cliente_direccion'],
                'shipping_city' => $validated['cliente_ciudad'],
                'shipping_state' => $validated['cliente_departamento'],
                'subtotal' => $validated['subtotal'],
                'shipping_cost' => $validated['envio'] ?? 0,
                'total' => $validated['total'],
                'payment_method' => $validated['metodo_pago'],
                'payment_reference' => $validated['referencia_pago'] ?? null,
                'status' => 'pending',
                'notes' => $validated['notas'] ?? null,
                'source' => 'web',
            ]);

            // Crear los items de la orden
            foreach ($validated['items'] as $item) {
                $producto = Product::findOrFail($item['producto_id']);

                OrderItem::create([
                    'order_id' => $orden->id,
                    'product_id' => $producto->id,
                    'product_name' => $producto->name,
                    'product_sku' => $producto->sku,
                    'quantity' => $item['cantidad'],
                    'price' => $item['precio'],
                    'subtotal' => $item['cantidad'] * $item['precio'],
                ]);

                // Opcional: Reducir stock (descomentar si quieres reducir automáticamente)
                // $producto->decrement('stock', $item['cantidad']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden creada exitosamente',
                'data' => [
                    'orden_id' => $orden->id,
                    'numero_orden' => $orden->order_number,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar referencia de pago de una orden
     * PATCH /api/tienda/ordenes/{orderNumber}/payment
     */
    public function updatePayment(Request $request, $orderNumber)
    {
        $validated = $request->validate([
            'payment_reference' => 'required|string',
            'status' => 'nullable|string',
        ]);

        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }

        $order->update([
            'payment_reference' => $validated['payment_reference'],
            'status' => $validated['status'] ?? $order->status,
            'paid_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Orden actualizada exitosamente',
        ]);
    }

    /**
     * Subir imágenes de producto a Cloudinary
     * POST /api/tienda/productos/{codigo}/imagenes
     */
    public function subirImagenes(Request $request, $codigo)
    {
        $request->validate([
            'imagenes' => 'required|array|max:5',
            'imagenes.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
        ]);

        $q = Product::where('sku', $codigo);
        if (is_numeric($codigo)) {
            $q->orWhere('id', (int) $codigo);
        }
        $producto = $q->first();

        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $urls = [];

        try {
            foreach ($request->file('imagenes') as $imagen) {
                // Subir a Cloudinary
                $uploadedFile = cloudinary()->upload($imagen->getRealPath(), [
                    'folder' => "repuestos/{$producto->sku}",
                    'transformation' => [
                        'width' => 1200,
                        'height' => 1200,
                        'crop' => 'limit',
                        'quality' => 'auto',
                        'fetch_format' => 'auto'
                    ]
                ]);

                $urls[] = $uploadedFile->getSecurePath();

                // Guardar en la base de datos (asumiendo que tienes una tabla product_images)
                DB::table('product_images')->insert([
                    'product_id' => $producto->id,
                    'image_url' => $uploadedFile->getSecurePath(),
                    'public_id' => $uploadedFile->getPublicId(),
                    'is_primary' => count($urls) === 1, // Primera imagen es la principal
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Imágenes subidas exitosamente',
                'data' => $urls
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir imágenes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatear producto para respuesta
     */
    private function formatProducto($producto, $detallado = false)
    {
        $descuento = (int) ($producto->discount_percentage ?? 0);
        $precioOriginal = (float) $producto->unit_price;
        $precioOferta = $descuento > 0
            ? round($precioOriginal * (1 - $descuento / 100))
            : null;

        $data = [
            'id' => $producto->id,
            'codigo' => $producto->sku,
            'nombre' => $producto->name,
            'slug' => Str::slug($producto->name) . '--' . $producto->sku,
            'precio_venta' => $precioOriginal,
            'descuento_porcentaje' => $descuento,
            'precio_oferta' => $precioOferta,
            'en_oferta' => $descuento > 0,
            'stock' => (int) $producto->stock_quantity,
            'disponible' => $producto->stock_quantity > 0,
            'categoria' => $producto->category ? $producto->category->name : null,
            'categoria_slug' => $producto->category ? Str::slug($producto->category->name) : null,
            'proveedor_marca' => $producto->brand ? $producto->brand->name : null,
            'descripcion' => $producto->description ?? '',
            'modelos_compatibles' => $producto->compatible_models ?? null,
            'imagenes' => $this->getProductImages($producto),
        ];

        if ($detallado) {
            $data['especificaciones'] = [
                'unidad_medida' => $producto->unit_of_measure ?? 'unidad',
                'peso' => $producto->weight ?? null,
                'dimensiones' => $producto->dimensions ?? null,
            ];
            $data['productos_relacionados'] = $this->getRelatedProducts($producto);
        }

        return $data;
    }

    /**
     * Obtener imágenes del producto
     */
    private function getProductImages($producto)
    {
        // Si tienes una relación images en el modelo
        if ($producto->relationLoaded('images') && $producto->images->count() > 0) {
            return $producto->images->pluck('image_url')->toArray();
        }

        // Si tienes un campo image_url en el producto
        if ($producto->image_url) {
            return [$producto->image_url];
        }

        return [];
    }

    /**
     * Obtener productos relacionados
     */
    private function getRelatedProducts($producto)
    {
        return Product::where('category_id', $producto->category_id)
            ->where('id', '!=', $producto->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->limit(4)
            ->get()
            ->map(function ($p) {
                return $this->formatProducto($p);
            });
    }

    /**
     * Formatear categoría con conteo
     */
    private function formatCategoria($categoria)
    {
        return [
            'id' => $categoria->id,
            'nombre' => $categoria->name,
            'slug' => Str::slug($categoria->name),
            'descripcion' => $categoria->description ?? '',
            'imagen' => $categoria->image_url ?? null,
            'productos_count' => Product::where('category_id', $categoria->id)
                ->where('is_active', true)
                ->count(),
            'subcategorias' => $categoria->children->map(function ($child) {
                return $this->formatCategoria($child);
            }),
        ];
    }
}
