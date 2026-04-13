<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    /** POST /api/cliente/auth/register */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => 'required|email|unique:customers,email',
            'password'   => 'required|string|min:8|confirmed',
            'phone'      => 'nullable|string|max:20',
        ]);

        // Build name from first_name + last_name if not provided explicitly
        $firstName = $validated['first_name'] ?? null;
        $lastName  = $validated['last_name'] ?? null;
        $fullFromParts = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
        $name = !empty($validated['name'])
            ? $validated['name']
            : ($fullFromParts ?: $validated['email']);

        $customer = Customer::create([
            'name'          => $name,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'phone'         => $validated['phone'] ?? null,
            'customer_type' => 'individual',
            'is_active'     => true,
        ]);

        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'customer' => $this->format($customer),
        ], 201);
    }

    /** POST /api/cliente/auth/login */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = Customer::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json([
            'success'  => true,
            'token'    => $token,
            'customer' => $this->format($customer),
        ]);
    }

    /** POST /api/cliente/auth/logout */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sesión cerrada.']);
    }

    /** GET /api/cliente/auth/me */
    public function me(Request $request)
    {
        return response()->json([
            'success'  => true,
            'customer' => $this->format($request->user()),
        ]);
    }

    /** PUT /api/cliente/auth/profile */
    public function updateProfile(Request $request)
    {
        $customer = $request->user();

        $validated = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'birth_date'      => 'nullable|date',
            'gender'          => 'nullable|in:masculino,femenino,otro',
            'document_number' => 'nullable|string|max:30',
        ]);

        $customer->update($validated);

        return response()->json([
            'success'  => true,
            'customer' => $this->format($customer->fresh()),
            'message'  => 'Perfil actualizado.',
        ]);
    }

    /** PUT /api/cliente/auth/newsletter */
    public function toggleNewsletter(Request $request)
    {
        $customer = $request->user();
        $customer->update(['newsletter_subscribed' => $request->boolean('subscribed')]);

        return response()->json([
            'success' => true,
            'newsletter_subscribed' => $customer->newsletter_subscribed,
        ]);
    }

    /** PUT /api/cliente/auth/change-password */
    public function changePassword(Request $request)
    {
        $customer = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $customer->update(['password' => Hash::make($request->password)]);

        // Revocar todos los tokens y crear uno nuevo
        $customer->tokens()->delete();
        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'message' => 'Contraseña actualizada.',
        ]);
    }

    /** POST /api/cliente/auth/forgot-password */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $customer = Customer::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'not_found' => true,
                'message'  => 'No existe una cuenta con ese correo.',
            ], 404);
        }

        // Código de 6 dígitos, expira en 15 min
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        // Enviar por correo
        $name = $customer->first_name ?? $customer->name;
        $html = "
        <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;color:#333'>
          <div style='background:#1a1a1a;padding:24px;text-align:center'>
            <h1 style='color:#fff;margin:0;font-size:20px'>Moto Spa</h1>
            <p style='color:#aaa;margin:6px 0 0;font-size:13px'>Recuperación de contraseña</p>
          </div>
          <div style='padding:28px'>
            <p>Hola <strong>{$name}</strong>,</p>
            <p>Tu código de verificación para restablecer tu contraseña es:</p>
            <div style='background:#f5f5f5;border-radius:12px;padding:24px;text-align:center;margin:20px 0'>
              <span style='font-size:40px;font-weight:bold;letter-spacing:10px;color:#111'>{$code}</span>
            </div>
            <p style='color:#666;font-size:13px'>Este código es válido por <strong>15 minutos</strong>. Si no solicitaste este cambio, ignora este correo.</p>
          </div>
        </div>";

        try {
            Mail::html($html, fn($m) =>
                $m->to($customer->email, $customer->name)
                  ->subject('Código de verificación — Moto Spa')
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el correo. Intenta más tarde.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Código enviado a tu correo.',
        ]);
    }

    /** POST /api/cliente/auth/verify-reset-code */
    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Código inválido o expirado.'], 422);
        }

        // Verificar expiración (15 min)
        if (now()->diffInMinutes($record->created_at) > 15) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['success' => false, 'message' => 'El código ha expirado. Solicita uno nuevo.'], 422);
        }

        if (!Hash::check($request->code, $record->token)) {
            return response()->json(['success' => false, 'message' => 'El código ingresado no es correcto.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Código verificado.']);
    }

    /** POST /api/cliente/auth/reset-password */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'code'                  => 'required|string|size:6',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->code, $record->token)) {
            return response()->json(['success' => false, 'message' => 'Código inválido o expirado.'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 15) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['success' => false, 'message' => 'El código ha expirado. Solicita uno nuevo.'], 422);
        }

        $customer = Customer::where('email', $request->email)->where('is_active', true)->firstOrFail();
        $customer->update(['password' => Hash::make($request->password)]);

        // Invalidar tokens de sesión anteriores
        $customer->tokens()->delete();

        // Eliminar el código usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada correctamente.']);
    }

    private function format(Customer $c): array
    {
        return [
            'id'                    => $c->id,
            'name'                  => $c->name,
            'first_name'            => $c->first_name,
            'last_name'             => $c->last_name,
            'email'                 => $c->email,
            'phone'                 => $c->phone,
            'birth_date'            => $c->birth_date?->format('Y-m-d'),
            'gender'                => $c->gender,
            'document_number'       => $c->document_number,
            'newsletter_subscribed' => (bool) $c->newsletter_subscribed,
            'customer_type'         => $c->customer_type,
            'city'                  => $c->city,
            'state'                 => $c->state,
            'country'               => $c->country,
        ];
    }
}
