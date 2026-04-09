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
 * Importa productos desde el Catálogo Darrow.
 *
 * Columnas por índice (fila 1 = encabezados, se salta con WithStartRow):
 *   0=REFERENCIA  1=CATEGORÍA  2=DESCRIPCIÓN  3=MODELOS COMPATIBLES
 *   4=MARCA       5=PRESENTACION  6=PRECIO  7=STOCK  8=STOCK MINIMO  9=ACTIVO
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
        $categoryName = trim((string) ($row[1] ?? ''));
        $name         = trim((string) ($row[2] ?? ''));
        $compatible   = trim((string) ($row[3] ?? ''));
        $brandName    = trim((string) ($row[4] ?? ''));
        $presentation = trim((string) ($row[5] ?? '')) ?: 'unidad';
        $price        = (float) ($row[6] ?? 0);
        $stock        = (int)   ($row[7] ?? 0);
        $minStock     = (int)   ($row[8] ?? 0);
        $isActive     = (bool)  ($row[9] ?? true);

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
                'compatible_models' => $compatible ?: null,
                'category_id'       => $categoryId,
                'brand_id'          => $brandId,
                'unit_price'        => $price,
                'cost_price'        => $price,
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
            'description'       => $name,
            'compatible_models' => $compatible ?: null,
            'category_id'       => $categoryId,
            'brand_id'          => $brandId,
            'unit_price'        => $price,
            'cost_price'        => $price,
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
