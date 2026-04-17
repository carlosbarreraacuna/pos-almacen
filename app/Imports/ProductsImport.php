<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Importa productos desde el archivo Excel estándar.
 *
 * FORMATO ACTUAL (formato B — plantilla unificada importar/exportar):
 *   0  A = SKU               (obligatorio)
 *   1  B = Nombre            (obligatorio)
 *   2  C = Categoría
 *   3  D = Costo Base        ← inputs de la fórmula
 *   4  E = Flete ($)
 *   5  F = IVA (%)
 *   6  G = Margen Ganancia (%)
 *   7  H = Precio Venta      (0 = calcular desde D-G; >0 = usar directamente)
 *   8  I = Descuento (%)
 *   9  J = Stock             (obligatorio)
 *   10 K = Stock Mínimo
 *   11 L = Unidad de Medida
 *   12 M = Marca
 *   13 N = Modelos Compatibles
 *   14 O = Descripción
 *   15 P = Fecha Creación    (ignorado)
 *
 * FORMATO LEGADO (plantilla original de 12 columnas):
 *   0=SKU 1=Nombre 2=Categoría 3=Precio 4=Costo 5=Stock 6=StockMin 7=Presentación 8=Marca …
 *   Detectado cuando col[3] parece un precio (>100) y col[9] está vacío.
 */
class ProductsImport implements ToModel, WithStartRow, SkipsOnError
{
    use SkipsErrors;

    private int $importedCount = 0;
    private int $updatedCount  = 0;
    private array $categoryCache = [];
    private array $brandCache    = [];

    // Defaults globales cargados una sola vez
    private float $defaultIva    = 19;
    private float $defaultFlete  = 0;
    private float $defaultMargen = 30;

    public function __construct()
    {
        $this->defaultIva    = (float) Setting::get('pricing.default_iva',            19);
        $this->defaultFlete  = (float) Setting::get('pricing.default_flete',           0);
        $this->defaultMargen = (float) Setting::get('pricing.default_profit_margin',  30);
    }

    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        // Formato B (actual): col[3]=Costo, col[7]=Precio, col[9]=Stock
        // Formato legado:     col[3]=Precio, col[5]=Stock
        // Distinción: en formato B, col[9] tiene stock (entero) y col[7] tiene precio
        // En legado, col[5] tiene stock y col[7] suele ser vacío o tener marca/modelo
        $isLegacy = !isset($row[9]) || (string)($row[9] ?? '') === '';

        if ($isLegacy) {
            return $this->modelLegacy($row);
        }

        return $this->modelNew($row);
    }

    /** Formato B — columnas unificadas importar/exportar */
    private function modelNew(array $row)
    {
        $sku          = trim((string) ($row[0]  ?? ''));
        $name         = trim((string) ($row[1]  ?? ''));
        $categoryName = trim((string) ($row[2]  ?? ''));
        $costBase     = (float) ($row[3]  ?? 0);   // D — Costo Base
        $flete        = isset($row[4]) && $row[4] !== '' ? (float) $row[4] : $this->defaultFlete;
        $iva          = isset($row[5]) && $row[5] !== '' ? (float) $row[5] : $this->defaultIva;
        $margen       = isset($row[6]) && $row[6] !== '' ? (float) $row[6] : $this->defaultMargen;
        $priceRaw     = (float) ($row[7]  ?? 0);   // H — Precio Venta (puede ser fórmula resuelta)
        $discount     = max(0, min(100, (int) ($row[8] ?? 0)));
        $stock        = (int)   ($row[9]  ?? 0);   // J — Stock
        $minStock     = (int)   ($row[10] ?? 0);
        $presentation = trim((string) ($row[11] ?? '')) ?: 'unidad';
        $brandName    = trim((string) ($row[12] ?? ''));
        $compatible   = trim((string) ($row[13] ?? ''));
        $description  = trim((string) ($row[14] ?? '')) ?: $name;

        if (empty($sku) || empty($name)) {
            return null;
        }

        // Calcular precio si no fue especificado o es 0
        $price = $priceRaw > 0
            ? $priceRaw
            : $this->calcularPrecio($costBase, $flete, $iva, $margen);

        // Si aún no hay precio, usar costo base como fallback
        if ($price <= 0) {
            $price = $costBase;
        }

        $categoryId = $this->resolveCategoryId($categoryName);
        $brandId    = $this->resolveBrandId($brandName);

        $fields = [
            'name'                => $name,
            'description'         => $description,
            'compatible_models'   => $compatible ?: null,
            'category_id'         => $categoryId,
            'brand_id'            => $brandId,
            'unit_price'          => round($price, 2),
            'cost_price'          => $costBase,
            'freight_cost'        => $flete,
            'tax_rate'            => $iva,
            'profit_margin'       => $margen,
            'discount_percentage' => $discount,
            'stock_quantity'      => $stock,
            'min_stock_level'     => $minStock,
            'unit_of_measure'     => $presentation,
            'is_active'           => true,
        ];

        $product = Product::withTrashed()->where('sku', $sku)->first();

        if ($product) {
            $product->restore();
            $product->update($fields);
            $this->updatedCount++;
            return null;
        }

        $this->importedCount++;
        return new Product(array_merge(['sku' => $sku], $fields));
    }

    /** Esquema antiguo (compatibilidad con archivos viejos de 12 columnas) */
    private function modelLegacy(array $row)
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

        if (empty($sku) || empty($name)) {
            return null;
        }

        $categoryId = $this->resolveCategoryId($categoryName);
        $brandId    = $this->resolveBrandId($brandName);

        $fields = [
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
            'is_active'         => true,
        ];

        $product = Product::withTrashed()->where('sku', $sku)->first();

        if ($product) {
            $product->restore();
            $product->update($fields);
            $this->updatedCount++;
            return null;
        }

        $this->importedCount++;
        return new Product(array_merge(['sku' => $sku], $fields));
    }

    private function calcularPrecio(float $costo, float $flete, float $iva, float $margen): float
    {
        return ($costo + $flete) * (1 + $iva / 100) * (1 + $margen / 100);
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
