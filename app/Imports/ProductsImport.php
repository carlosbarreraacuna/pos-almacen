<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Importa productos desde el archivo Excel estándar.
 *
 * Columnas por índice (fila 1 = encabezados, se salta con WithStartRow):
 *   0=SKU  1=Nombre  2=Categoría  3=Precio Venta  4=Costo
 *   5=Stock  6=Stock Mínimo  7=Unidad de Medida  8=Marca
 *   9=Modelos Compatibles  10=Descripción  11=Fecha Creación
 */
class ProductsImport implements ToModel, WithStartRow, SkipsOnError
{
    use SkipsErrors;

    private int $importedCount = 0;
    private int $updatedCount  = 0;
    private array $categoryCache = [];
    private array $brandCache    = [];

    /** Empezar en fila 2 para saltar la fila de encabezados */
    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        $sku          = trim((string) ($row[0] ?? ''));
        $name         = trim((string) ($row[1] ?? ''));
        $categoryName = trim((string) ($row[2] ?? ''));
        $price        = (float) ($row[3] ?? 0);
        $cost         = (float) ($row[4] ?? 0) ?: $price;
        $stock        = (int)   ($row[5] ?? 0);
        $minStock     = (int)   ($row[6] ?? 0);
        $presentation = trim((string) ($row[7] ?? '')) ?: 'unidad';
        $brandName    = trim((string) ($row[8] ?? ''));
        $compatible   = trim((string) ($row[9] ?? ''));
        $description  = trim((string) ($row[10] ?? '')) ?: $name;
        $isActive     = true;

        // Saltar filas separadoras de categoría (ej: "▶ GUARDABARROS")
        if (empty($sku) || empty($name)) {
            return null;
        }

        $categoryId = $this->resolveCategoryId($categoryName);
        $brandId    = $this->resolveBrandId($brandName);

        // withTrashed() para encontrar también productos borrados lógicamente
        $product = Product::withTrashed()->where('sku', $sku)->first();

        if ($product) {
            $product->restore(); // no hace nada si no estaba borrado
            $product->update([
                'name'              => $name,
                'description'       => $description,
                'compatible_models' => $compatible ?: null,
                'category_id'       => $categoryId,
                'brand_id'          => $brandId,
                'unit_price'        => $price,
                'cost_price'        => $cost,
                'stock_quantity'    => $stock,
                'min_stock_level'   => $minStock,
                'unit_of_measure'   => $presentation,
                'is_active'         => $isActive,
            ]);
            $this->updatedCount++;
            return null;
        }

        $this->importedCount++;
        return new Product([
            'sku'               => $sku,
            'name'              => $name,
            'description'       => $description,
            'compatible_models' => $compatible ?: null,
            'category_id'       => $categoryId,
            'brand_id'          => $brandId,
            'unit_price'        => $price,
            'cost_price'        => $cost,
            'stock_quantity'    => $stock,
            'min_stock_level'   => $minStock,
            'unit_of_measure'   => $presentation,
            'is_active'         => $isActive,
        ]);
    }

    private function resolveCategoryId(string $name): ?int
    {
        if (empty($name)) return null;

        if (!isset($this->categoryCache[$name])) {
            $this->categoryCache[$name] = Category::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            )->id;
        }

        return $this->categoryCache[$name];
    }

    private function resolveBrandId(string $name): ?int
    {
        if (empty($name)) return null;

        if (!isset($this->brandCache[$name])) {
            $this->brandCache[$name] = Brand::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            )->id;
        }

        return $this->brandCache[$name];
    }

    public function getImportedCount(): int { return $this->importedCount; }
    public function getUpdatedCount(): int  { return $this->updatedCount;  }
}
