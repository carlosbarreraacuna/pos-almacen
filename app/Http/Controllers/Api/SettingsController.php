<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * GET /api/settings?group=pricing
     * Returns settings, optionally filtered by group.
     */
    public function index(Request $request)
    {
        $query = Setting::query();

        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }

        $settings = $query->get()->map(fn($s) => [
            'key'   => $s->key,
            'value' => $s->typed_value,
            'label' => $s->label,
            'type'  => $s->type,
            'group' => $s->group,
        ]);

        return response()->json(['success' => true, 'data' => $settings]);
    }

    /**
     * POST /api/settings
     * Body: { "key": "pricing.default_iva", "value": 19 }
     * Or bulk: { "settings": [{ "key": "...", "value": ... }, ...] }
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings'         => 'sometimes|array',
            'settings.*.key'   => 'required|string',
            'settings.*.value' => 'required',
            'key'              => 'sometimes|string',
            'value'            => 'sometimes',
        ]);

        if ($request->has('settings')) {
            foreach ($request->settings as $item) {
                $this->upsert($item['key'], $item['value']);
            }
        } elseif ($request->has('key')) {
            $this->upsert($request->key, $request->value);
        }

        return response()->json(['success' => true, 'message' => 'Configuración guardada.']);
    }

    private function upsert(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );
    }
}
