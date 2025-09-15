<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // TODO: Implementar autorización
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'location_id' => 'sometimes|nullable|exists:locations,id',
            'adjustment_date' => 'sometimes|required|date|before_or_equal:today',
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['increase', 'decrease', 'correction', 'damage', 'loss', 'found'])
            ],
            'reason' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'physical_count', 'damage', 'theft', 'expiration', 'quality_issue',
                    'system_error', 'receiving_error', 'shipping_error', 'found_stock',
                    'transfer_error', 'other'
                ])
            ],
            'description' => 'sometimes|required|string|max:1000',
            'notes' => 'sometimes|nullable|string|max:1000',
            
            // Items del ajuste (solo si se proporcionan)
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.current_quantity' => 'required_with:items|numeric|min:0',
            'items.*.adjusted_quantity' => 'required_with:items|numeric|min:0',
            'items.*.unit_cost' => 'sometimes|nullable|numeric|min:0',
            'items.*.reason' => 'sometimes|nullable|string|max:500',
            'items.*.notes' => 'sometimes|nullable|string|max:500',
            
            // Metadatos
            'metadata' => 'sometimes|nullable|array'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'El almacén es obligatorio.',
            'warehouse_id.exists' => 'El almacén seleccionado no existe.',
            'location_id.exists' => 'La ubicación seleccionada no existe.',
            'adjustment_date.required' => 'La fecha de ajuste es obligatoria.',
            'adjustment_date.before_or_equal' => 'La fecha de ajuste no puede ser futura.',
            'type.required' => 'El tipo de ajuste es obligatorio.',
            'type.in' => 'El tipo de ajuste debe ser uno de los valores permitidos.',
            'reason.required' => 'La razón del ajuste es obligatoria.',
            'reason.in' => 'La razón del ajuste debe ser uno de los valores permitidos.',
            'description.required' => 'La descripción es obligatoria.',
            'description.max' => 'La descripción no puede exceder 1000 caracteres.',
            'items.min' => 'Debe incluir al menos un producto en el ajuste.',
            'items.*.product_id.required_with' => 'El producto es obligatorio.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe.',
            'items.*.current_quantity.required_with' => 'La cantidad actual es obligatoria.',
            'items.*.current_quantity.min' => 'La cantidad actual no puede ser negativa.',
            'items.*.adjusted_quantity.required_with' => 'La cantidad ajustada es obligatoria.',
            'items.*.adjusted_quantity.min' => 'La cantidad ajustada no puede ser negativa.',
            'items.*.unit_cost.min' => 'El costo unitario no puede ser negativo.',
            'items.*.reason.max' => 'La razón del producto no puede exceder 500 caracteres.',
            'items.*.notes.max' => 'Las notas del producto no pueden exceder 500 caracteres.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Calcular quantity_adjustment para cada item si se proporcionan
        if ($this->has('items')) {
            $items = $this->items;
            foreach ($items as $index => $item) {
                if (isset($item['current_quantity']) && isset($item['adjusted_quantity'])) {
                    $items[$index]['quantity_adjustment'] = $item['adjusted_quantity'] - $item['current_quantity'];
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $stockAdjustment = $this->route('stockAdjustment');
            
            // Solo permitir edición si está en estado 'pending'
            if ($stockAdjustment && $stockAdjustment->status !== 'pending') {
                $validator->errors()->add('status', 'Solo se pueden editar ajustes en estado pendiente.');
                return;
            }

            // Validar que el almacén esté activo
            if ($this->has('warehouse_id')) {
                $warehouse = \App\Models\Warehouse::find($this->warehouse_id);
                if ($warehouse && !$warehouse->is_active) {
                    $validator->errors()->add('warehouse_id', 'El almacén seleccionado no está activo.');
                }
            }

            // Validar que la ubicación pertenezca al almacén
            if ($this->has('location_id') && ($this->warehouse_id || $stockAdjustment)) {
                $warehouseId = $this->warehouse_id ?? $stockAdjustment->warehouse_id;
                $location = \App\Models\Location::find($this->location_id);
                if ($location && $location->warehouse_id !== (int)$warehouseId) {
                    $validator->errors()->add('location_id', 'La ubicación seleccionada no pertenece al almacén.');
                }
            }

            // Validar productos duplicados en items
            if ($this->has('items')) {
                $productIds = array_column($this->items, 'product_id');
                $duplicates = array_diff_assoc($productIds, array_unique($productIds));
                
                if (!empty($duplicates)) {
                    $validator->errors()->add('items', 'No se pueden incluir productos duplicados en el ajuste.');
                }
            }

            // Validar que las cantidades actuales coincidan con el stock real
            if ($this->has('items')) {
                $warehouseId = $this->warehouse_id ?? $stockAdjustment->warehouse_id;
                $locationId = $this->location_id ?? $stockAdjustment->location_id;
                
                foreach ($this->items as $index => $item) {
                    if (isset($item['product_id']) && isset($item['current_quantity'])) {
                        $query = \App\Models\ProductStock::where('product_id', $item['product_id'])
                            ->where('warehouse_id', $warehouseId);
                        
                        if ($locationId) {
                            $query->where('location_id', $locationId);
                        }
                        
                        $actualStock = $query->sum('quantity');
                        
                        // Permitir una pequeña tolerancia para diferencias de redondeo
                        $tolerance = 0.001;
                        if (abs($actualStock - $item['current_quantity']) > $tolerance) {
                            $validator->errors()->add(
                                "items.{$index}.current_quantity",
                                "La cantidad actual no coincide con el stock real. Stock actual: {$actualStock}"
                            );
                        }
                    }
                }
            }

            // Validar que haya al menos un cambio significativo si se actualizan items
            if ($this->has('items')) {
                $hasSignificantChange = false;
                foreach ($this->items as $item) {
                    if (isset($item['current_quantity']) && isset($item['adjusted_quantity'])) {
                        $difference = abs($item['adjusted_quantity'] - $item['current_quantity']);
                        if ($difference > 0.001) { // Tolerancia para diferencias mínimas
                            $hasSignificantChange = true;
                            break;
                        }
                    }
                }
                
                if (!$hasSignificantChange) {
                    $validator->errors()->add('items', 'Debe haber al menos un cambio significativo en las cantidades.');
                }
            }

            // Validar coherencia entre tipo y razón
            if ($this->has('type') && $this->has('reason')) {
                $typeReasonMap = [
                    'increase' => ['found_stock', 'receiving_error', 'system_error', 'other'],
                    'decrease' => ['damage', 'theft', 'expiration', 'quality_issue', 'shipping_error', 'transfer_error', 'other'],
                    'correction' => ['physical_count', 'system_error', 'other'],
                    'damage' => ['damage', 'quality_issue', 'expiration'],
                    'loss' => ['theft', 'damage', 'expiration', 'other'],
                    'found' => ['found_stock', 'physical_count', 'other']
                ];
                
                if (isset($typeReasonMap[$this->type]) && !in_array($this->reason, $typeReasonMap[$this->type])) {
                    $validator->errors()->add('reason', 'La razón seleccionada no es compatible con el tipo de ajuste.');
                }
            }

            // Validar que no se pueda cambiar fecha si ya fue aprobado anteriormente
            if ($this->has('adjustment_date') && $stockAdjustment) {
                if ($stockAdjustment->approved_at) {
                    $validator->errors()->add('adjustment_date', 'No se puede cambiar la fecha de un ajuste que ya fue aprobado.');
                }
            }
        });
    }
}