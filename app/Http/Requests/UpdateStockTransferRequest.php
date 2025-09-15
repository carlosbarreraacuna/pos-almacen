<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockTransferRequest extends FormRequest
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
            'from_warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'to_warehouse_id' => 'sometimes|required|exists:warehouses,id|different:from_warehouse_id',
            'transfer_date' => 'sometimes|required|date|after_or_equal:today',
            'expected_date' => 'sometimes|nullable|date|after_or_equal:transfer_date',
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['regular', 'emergency', 'return', 'adjustment'])
            ],
            'priority' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['low', 'medium', 'high', 'urgent'])
            ],
            'reason' => 'sometimes|required|string|max:500',
            'notes' => 'sometimes|nullable|string|max:1000',
            
            // Información de envío
            'shipping_method' => 'sometimes|nullable|string|max:100',
            'tracking_number' => 'sometimes|nullable|string|max:100',
            'carrier' => 'sometimes|nullable|string|max:100',
            'shipping_cost' => 'sometimes|nullable|numeric|min:0',
            
            // Items de la transferencia (solo si se proporcionan)
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.quantity_received' => 'sometimes|nullable|numeric|min:0|lte:items.*.quantity',
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
            'items.min' => 'Debe incluir al menos un producto en la transferencia.',
            'items.*.product_id.required_with' => 'El producto es obligatorio.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe.',
            'items.*.quantity.required_with' => 'La cantidad es obligatoria.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
            'items.*.quantity_received.min' => 'La cantidad recibida no puede ser negativa.',
            'items.*.quantity_received.lte' => 'La cantidad recibida no puede ser mayor a la cantidad enviada.',
            'items.*.notes.max' => 'Las notas del producto no pueden exceder 500 caracteres.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $stockTransfer = $this->route('stockTransfer');
            
            // Solo permitir edición si está en estado 'pending' o 'approved'
            if ($stockTransfer && !in_array($stockTransfer->status, ['pending', 'approved'])) {
                $validator->errors()->add('status', 'Solo se pueden editar transferencias en estado pendiente o aprobado.');
                return;
            }

            // Validar que los almacenes estén activos
            if ($this->has('from_warehouse_id')) {
                $fromWarehouse = \App\Models\Warehouse::find($this->from_warehouse_id);
                if ($fromWarehouse && !$fromWarehouse->is_active) {
                    $validator->errors()->add('from_warehouse_id', 'El almacén de origen no está activo.');
                }
            }

            if ($this->has('to_warehouse_id')) {
                $toWarehouse = \App\Models\Warehouse::find($this->to_warehouse_id);
                if ($toWarehouse && !$toWarehouse->is_active) {
                    $validator->errors()->add('to_warehouse_id', 'El almacén de destino no está activo.');
                }
            }

            // Validar stock disponible si se actualizan los items
            if ($this->has('items')) {
                $fromWarehouseId = $this->from_warehouse_id ?? $stockTransfer->from_warehouse_id;
                
                foreach ($this->items as $index => $item) {
                    if (isset($item['product_id']) && isset($item['quantity'])) {
                        // Obtener la cantidad actual en la transferencia para este producto
                        $currentTransferItem = $stockTransfer->items()
                            ->where('product_id', $item['product_id'])
                            ->first();
                        
                        $currentQuantityInTransfer = $currentTransferItem ? $currentTransferItem->quantity : 0;
                        
                        // Calcular stock disponible considerando la cantidad actual en la transferencia
                        $availableStock = \App\Models\ProductStock::where('product_id', $item['product_id'])
                            ->where('warehouse_id', $fromWarehouseId)
                            ->sum('quantity') + $currentQuantityInTransfer;

                        if ($availableStock < $item['quantity']) {
                            $validator->errors()->add(
                                "items.{$index}.quantity",
                                "Stock insuficiente. Disponible: {$availableStock}, Solicitado: {$item['quantity']}"
                            );
                        }
                    }
                }

                // Validar productos duplicados en items
                $productIds = array_column($this->items, 'product_id');
                $duplicates = array_diff_assoc($productIds, array_unique($productIds));
                
                if (!empty($duplicates)) {
                    $validator->errors()->add('items', 'No se pueden incluir productos duplicados en la transferencia.');
                }
            }

            // Validar fechas si se están actualizando
            if ($this->has('transfer_date') && $stockTransfer) {
                // No permitir cambiar fecha si ya está en tránsito o completada
                if (in_array($stockTransfer->status, ['in_transit', 'completed'])) {
                    $validator->errors()->add('transfer_date', 'No se puede cambiar la fecha de una transferencia en tránsito o completada.');
                }
            }
        });
    }
}