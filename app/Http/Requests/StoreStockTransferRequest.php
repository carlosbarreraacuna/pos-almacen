<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
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
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'transfer_date' => 'required|date|after_or_equal:today',
            'expected_date' => 'nullable|date|after_or_equal:transfer_date',
            'type' => [
                'required',
                'string',
                Rule::in(['regular', 'emergency', 'return', 'adjustment'])
            ],
            'priority' => [
                'required',
                'string',
                Rule::in(['low', 'medium', 'high', 'urgent'])
            ],
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
            
            // Información de envío
            'shipping_method' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'carrier' => 'nullable|string|max:100',
            'shipping_cost' => 'nullable|numeric|min:0',
            
            // Items de la transferencia
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.notes' => 'nullable|string|max:500',
            
            // Metadatos
            'metadata' => 'nullable|array'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from_warehouse_id.required' => 'El almacén de origen es obligatorio.',
            'from_warehouse_id.exists' => 'El almacén de origen seleccionado no existe.',
            'to_warehouse_id.required' => 'El almacén de destino es obligatorio.',
            'to_warehouse_id.exists' => 'El almacén de destino seleccionado no existe.',
            'to_warehouse_id.different' => 'El almacén de destino debe ser diferente al de origen.',
            'transfer_date.required' => 'La fecha de transferencia es obligatoria.',
            'transfer_date.after_or_equal' => 'La fecha de transferencia no puede ser anterior a hoy.',
            'expected_date.after_or_equal' => 'La fecha esperada debe ser igual o posterior a la fecha de transferencia.',
            'type.required' => 'El tipo de transferencia es obligatorio.',
            'type.in' => 'El tipo de transferencia debe ser uno de los valores permitidos.',
            'priority.required' => 'La prioridad es obligatoria.',
            'priority.in' => 'La prioridad debe ser uno de los valores permitidos.',
            'reason.required' => 'La razón de la transferencia es obligatoria.',
            'reason.max' => 'La razón no puede exceder 500 caracteres.',
            'shipping_cost.min' => 'El costo de envío no puede ser negativo.',
            'items.required' => 'Debe incluir al menos un producto en la transferencia.',
            'items.min' => 'Debe incluir al menos un producto en la transferencia.',
            'items.*.product_id.required' => 'El producto es obligatorio.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
            'items.*.notes.max' => 'Las notas del producto no pueden exceder 500 caracteres.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si no se especifica fecha esperada, usar la fecha de transferencia + 1 día
        if (!$this->has('expected_date') && $this->has('transfer_date')) {
            $this->merge([
                'expected_date' => date('Y-m-d', strtotime($this->transfer_date . ' +1 day'))
            ]);
        }

        // Si no se especifica prioridad, establecer como 'medium'
        if (!$this->has('priority')) {
            $this->merge(['priority' => 'medium']);
        }

        // Si no se especifica tipo, establecer como 'regular'
        if (!$this->has('type')) {
            $this->merge(['type' => 'regular']);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que los almacenes estén activos
            if ($this->from_warehouse_id) {
                $fromWarehouse = \App\Models\Warehouse::find($this->from_warehouse_id);
                if ($fromWarehouse && !$fromWarehouse->is_active) {
                    $validator->errors()->add('from_warehouse_id', 'El almacén de origen no está activo.');
                }
            }

            if ($this->to_warehouse_id) {
                $toWarehouse = \App\Models\Warehouse::find($this->to_warehouse_id);
                if ($toWarehouse && !$toWarehouse->is_active) {
                    $validator->errors()->add('to_warehouse_id', 'El almacén de destino no está activo.');
                }
            }

            // Validar stock disponible en almacén de origen
            if ($this->items && $this->from_warehouse_id) {
                foreach ($this->items as $index => $item) {
                    if (isset($item['product_id']) && isset($item['quantity'])) {
                        $availableStock = \App\Models\ProductStock::where('product_id', $item['product_id'])
                            ->where('warehouse_id', $this->from_warehouse_id)
                            ->sum('quantity');

                        if ($availableStock < $item['quantity']) {
                            $validator->errors()->add(
                                "items.{$index}.quantity",
                                "Stock insuficiente. Disponible: {$availableStock}, Solicitado: {$item['quantity']}"
                            );
                        }
                    }
                }
            }

            // Validar productos duplicados en items
            if ($this->items) {
                $productIds = array_column($this->items, 'product_id');
                $duplicates = array_diff_assoc($productIds, array_unique($productIds));
                
                if (!empty($duplicates)) {
                    $validator->errors()->add('items', 'No se pueden incluir productos duplicados en la transferencia.');
                }
            }

            // Validar capacidad del almacén de destino si está configurada
            if ($this->to_warehouse_id && $this->items) {
                $toWarehouse = \App\Models\Warehouse::find($this->to_warehouse_id);
                if ($toWarehouse && $toWarehouse->total_capacity) {
                    $totalQuantity = array_sum(array_column($this->items, 'quantity'));
                    $availableCapacity = $toWarehouse->total_capacity - $toWarehouse->used_capacity;
                    
                    if ($totalQuantity > $availableCapacity) {
                        $validator->errors()->add('items', "El almacén de destino no tiene capacidad suficiente. Disponible: {$availableCapacity}, Requerido: {$totalQuantity}");
                    }
                }
            }
        });
    }
}