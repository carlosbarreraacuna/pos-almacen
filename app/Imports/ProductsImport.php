<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Illuminate\Support\Facades\Log;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;

    private $importedCount = 0;
    private $updatedCount = 0;

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        try {
            // Buscar o crear categoría
            $category = null;
            if (!empty($row['categoria'])) {
                $category = Category::firstOrCreate(
                    ['name' => $row['categoria']],
                    ['description' => 'Categoría importada']
                );
            }

            // Verificar si el producto ya existe por SKU
            $product = Product::where('sku', $row['sku'])->first();

            if ($product) {
                // Actualizar producto existente
                $product->update([
                    'name' => $row['nombre'],
                    'description' => $row['descripcion'] ?? null,
                    'unit_price' => $row['precio'],
                    'cost_price' => $row['precio_costo'] ?? 0,
                    'stock_quantity' => $row['stock'],
                    'min_stock_level' => $row['stock_minimo'] ?? 10,
                    'category_id' => $category ? $category->id : null,
                    'unit_of_measure' => $row['unidad_medida'] ?? 'unidad',
                    'is_active' => isset($row['activo']) ? (bool)$row['activo'] : true,
                ]);
                $this->updatedCount++;
                return null;
            }

            // Crear nuevo producto
            $this->importedCount++;
            return new Product([
                'sku' => $row['sku'],
                'name' => $row['nombre'],
                'description' => $row['descripcion'] ?? null,
                'unit_price' => $row['precio'],
                'cost_price' => $row['precio_costo'] ?? 0,
                'stock_quantity' => $row['stock'],
                'min_stock_level' => $row['stock_minimo'] ?? 10,
                'category_id' => $category ? $category->id : null,
                'unit_of_measure' => $row['unidad_medida'] ?? 'unidad',
                'is_active' => isset($row['activo']) ? (bool)$row['activo'] : true,
            ]);
        } catch (\Exception $e) {
            Log::error('Error importing product: ' . $e->getMessage(), ['row' => $row]);
            throw $e;
        }
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:50',
            'nombre' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ];
    }

    /**
     * Mensajes de validación personalizados
     */
    public function customValidationMessages()
    {
        return [
            'sku.required' => 'El SKU es obligatorio',
            'nombre.required' => 'El nombre es obligatorio',
            'precio.required' => 'El precio es obligatorio',
            'precio.numeric' => 'El precio debe ser un número',
            'stock.required' => 'El stock es obligatorio',
            'stock.integer' => 'El stock debe ser un número entero',
        ];
    }

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    public function getUpdatedCount()
    {
        return $this->updatedCount;
    }
}
