<?php
namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    // GET /brands
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Brand::all()
        ]);
    }

    // POST /brands
    public function store(Request $request)
{
    $data = $request->validate([
        'name'           => 'required|string|max:255|unique:brands,name',
        'description'    => 'nullable|string',
        'logo_url'       => 'nullable|string|max:2048',
        'website'        => 'nullable|url|max:255',
        'contact_email'  => 'nullable|email|max:255',
        'contact_phone'  => 'nullable|string|max:50',
        'is_active'      => 'nullable|boolean',
    ]);

    // por si no viene en el body
    $data['is_active'] = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : true;

    $brand = Brand::create($data);

    return response()->json([
        'success' => true,
        'data'    => $brand,
    ], 201);
}

    // GET /brands/select
    public function select()
    {
        $brands = Brand::select('id', 'name')->get();
        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    // GET /brands/{brand}
    public function show(Brand $brand)
    {
        return response()->json([
            'success' => true,
            'data' => $brand
        ]);
    }

    // PUT /brands/{brand}
    public function update(Request $request, Brand $brand)
{
    $data = $request->validate([
        'name' => [
            'sometimes','required','string','max:255',
            Rule::unique('brands','name')->whereNull('deleted_at')->ignore($brand->id),
        ],
        'description'   => 'sometimes|nullable|string',
        'logo_url'      => 'sometimes|nullable|string|max:2048',
        'website'       => 'sometimes|nullable|url|max:255',
        'contact_email' => 'sometimes|nullable|email|max:255',
        'contact_phone' => 'sometimes|nullable|string|max:50',
        'is_active'     => 'sometimes|boolean',
    ]);

    $brand->update($data);

    return response()->json(['success' => true, 'data' => $brand]);
}

    // DELETE /brands/{brand}
    public function destroy(Brand $brand)
    {
        $brand->delete();
        return response()->json([
            'success' => true,
            'message' => 'Marca eliminada correctamente'
        ]);
    }

    // GET /brands/{brand}/statistics
    public function statistics(Brand $brand)
    {
        // Ejemplo: cantidad de productos asociados a la marca
        $productCount = $brand->products()->count();
        return response()->json([
            'success' => true,
            'data' => [
                'product_count' => $productCount
            ]
        ]);
    }
}
