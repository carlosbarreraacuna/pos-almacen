<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
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
        $warehouseId = $this->route('warehouse')->id ?? $this->route('warehouse');
        
        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('warehouses', 'code')->ignore($warehouseId)
            ],
            'name' => 'sometimes|required|string|max:255',
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['main', 'secondary', 'transit', 'quarantine', 'returns'])
            ],
            'is_main' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            
            // Información de contacto
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'manager_name' => 'sometimes|nullable|string|max:255',
            
            // Dirección
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:100',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            
            // Capacidad
            'total_capacity' => 'sometimes|nullable|numeric|min:0',
            'used_capacity' => 'sometimes|nullable|numeric|min:0',
            'capacity_unit' => 'sometimes|nullable|string|max:20',
            
            // Configuración de temperatura
            'temperature_controlled' => 'sometimes|boolean',
            'min_temperature' => 'sometimes|nullable|numeric',
            'max_temperature' => 'sometimes|nullable|numeric|gte:min_temperature',
            'temperature_unit' => 'sometimes|nullable|string|in:C,F',
            
            // Seguridad
            'security_level' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(['low', 'medium', 'high', 'maximum'])
            ],
            'access_code' => 'sometimes|nullable|string|max:50',
            
            // Horarios de operación
            'operating_hours' => 'sometimes|nullable|array',
            'operating_hours.*.day' => 'required_with:operating_hours|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'operating_hours.*.open_time' => 'required_with:operating_hours|date_format:H:i',
            'operating_hours.*.close_time' => 'required_with:operating_hours|date_format:H:i|after:operating_hours.*.open_time',
            'operating_hours.*.is_closed' => 'boolean',
            
            // Costos
            'storage_cost_per_unit' => 'sometimes|nullable|numeric|min:0',
            'handling_cost_per_unit' => 'sometimes|nullable|numeric|min:0',
            
            // Metadatos
            'metadata' => 'sometimes|nullable|array',
            'notes' => 'sometimes|nullable|string|max:1000'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'El código del almacén es obligatorio.',
            'code.unique' => 'Ya existe un almacén con este código.',
            'code.regex' => 'El código solo puede contener letras mayúsculas, números y guiones.',
            'name.required' => 'El nombre del almacén es obligatorio.',
            'type.required' => 'El tipo de almacén es obligatorio.',
            'type.in' => 'El tipo de almacén debe ser uno de los valores permitidos.',
            'email.email' => 'El formato del email no es válido.',
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
            'max_temperature.gte' => 'La temperatura máxima debe ser mayor o igual a la mínima.',
            'security_level.in' => 'El nivel de seguridad debe ser uno de los valores permitidos.',
            'operating_hours.*.open_time.date_format' => 'El formato de hora de apertura debe ser HH:MM.',
            'operating_hours.*.close_time.date_format' => 'El formato de hora de cierre debe ser HH:MM.',
            'operating_hours.*.close_time.after' => 'La hora de cierre debe ser posterior a la de apertura.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->code)
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $warehouseId = $this->route('warehouse')->id ?? $this->route('warehouse');
            
            // Validar que solo haya un almacén principal
            if ($this->has('is_main') && $this->is_main) {
                $existingMain = \App\Models\Warehouse::where('is_main', true)
                    ->where('id', '!=', $warehouseId)
                    ->exists();
                if ($existingMain) {
                    $validator->errors()->add('is_main', 'Ya existe un almacén principal. Solo puede haber uno.');
                }
            }

            // Validar configuración de temperatura
            if ($this->has('temperature_controlled') && $this->temperature_controlled) {
                $currentWarehouse = \App\Models\Warehouse::find($warehouseId);
                $minTemp = $this->min_temperature ?? $currentWarehouse?->min_temperature;
                $maxTemp = $this->max_temperature ?? $currentWarehouse?->max_temperature;
                
                if (is_null($minTemp) || is_null($maxTemp)) {
                    $validator->errors()->add('temperature_controlled', 'Para almacenes con control de temperatura, debe especificar temperaturas mínima y máxima.');
                }
            }

            // Validar capacidad
            if ($this->has('used_capacity') || $this->has('total_capacity')) {
                $currentWarehouse = \App\Models\Warehouse::find($warehouseId);
                $usedCapacity = $this->used_capacity ?? $currentWarehouse?->used_capacity ?? 0;
                $totalCapacity = $this->total_capacity ?? $currentWarehouse?->total_capacity;
                
                if ($totalCapacity && $usedCapacity > $totalCapacity) {
                    $validator->errors()->add('used_capacity', 'La capacidad utilizada no puede ser mayor a la capacidad total.');
                }
            }
        });
    }
}