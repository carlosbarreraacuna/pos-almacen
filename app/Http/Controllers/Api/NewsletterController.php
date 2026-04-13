<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    // ── Suscriptores ──────────────────────────────────────────────────────────

    public function subscribers()
    {
        $subscribers = Customer::where('newsletter_subscribed', true)
            ->where('is_active', true)
            ->select('id', 'name', 'first_name', 'last_name', 'email', 'phone', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $subscribers, 'total' => $subscribers->count()]);
    }

    // ── Plantillas ────────────────────────────────────────────────────────────

    public function templates()
    {
        $templates = DB::table('newsletter_templates')->orderBy('is_default', 'desc')->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function storeTemplate(Request $request)
    {
        $v = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'category'    => 'nullable|string|in:general,promo,product,event',
            'html_body'   => 'required|string',
        ]);

        $id = DB::table('newsletter_templates')->insertGetId([
            ...$v,
            'category'   => $v['category'] ?? 'general',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => DB::table('newsletter_templates')->find($id)], 201);
    }

    public function updateTemplate(Request $request, $id)
    {
        $v = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:255',
            'category'    => 'nullable|string|in:general,promo,product,event',
            'html_body'   => 'sometimes|required|string',
        ]);

        DB::table('newsletter_templates')->where('id', $id)->update([...$v, 'updated_at' => now()]);
        return response()->json(['success' => true, 'data' => DB::table('newsletter_templates')->find($id)]);
    }

    public function destroyTemplate($id)
    {
        $tpl = DB::table('newsletter_templates')->where('id', $id)->first();
        if (!$tpl) return response()->json(['success' => false, 'message' => 'Plantilla no encontrada.'], 404);
        if ($tpl->is_default) return response()->json(['success' => false, 'message' => 'No se puede eliminar una plantilla predeterminada.'], 403);

        DB::table('newsletter_templates')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Campañas (historial) ──────────────────────────────────────────────────

    public function campaigns()
    {
        $campaigns = DB::table('newsletter_campaigns')->orderByDesc('sent_at')->get();
        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function destroyCampaign($id)
    {
        DB::table('newsletter_campaigns')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Envío ─────────────────────────────────────────────────────────────────

    public function send(Request $request)
    {
        $validated = $request->validate([
            'subject'       => 'required|string|max:255',
            'body'          => 'required|string',
            'recipient_ids' => 'nullable|array',
            'template_id'   => 'nullable|integer',
        ]);

        $query = Customer::where('newsletter_subscribed', true)->where('is_active', true);
        if (!empty($validated['recipient_ids'])) {
            $query->whereIn('id', $validated['recipient_ids']);
        }
        $customers = $query->select('id', 'name', 'email')->get();

        if ($customers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No hay suscriptores para enviar.'], 400);
        }

        $sent = 0; $failed = 0;
        foreach ($customers as $customer) {
            try {
                Mail::html($validated['body'], function ($message) use ($customer, $validated) {
                    $message->to($customer->email, $customer->name)->subject($validated['subject']);
                });
                $sent++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        $status = $failed === 0 ? 'sent' : ($sent === 0 ? 'failed' : 'partial');

        DB::table('newsletter_campaigns')->insert([
            'subject'         => $validated['subject'],
            'body'            => $validated['body'],
            'template_id'     => $validated['template_id'] ?? null,
            'recipient_count' => $customers->count(),
            'sent_count'      => $sent,
            'failed_count'    => $failed,
            'status'          => $status,
            'sent_at'         => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Enviado a {$sent} suscriptores." . ($failed > 0 ? " {$failed} fallidos." : ''),
            'sent'    => $sent,
            'failed'  => $failed,
        ]);
    }
}
