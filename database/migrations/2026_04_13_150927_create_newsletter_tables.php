<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('category')->default('general');
            $table->text('html_body');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->integer('recipient_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('status')->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        $this->seedTemplates();
    }

    private function seedTemplates(): void
    {
        $promoBody = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333;background:#fff"><div style="background:linear-gradient(135deg,#1a1a1a,#333);padding:32px 24px;text-align:center"><h1 style="color:#fff;margin:0;font-size:26px;letter-spacing:1px">MOTO SPA</h1><p style="color:#f59e0b;margin:8px 0 0;font-size:14px;font-weight:bold;text-transform:uppercase;letter-spacing:2px">🔥 Promociones del mes</p></div><div style="padding:28px 24px 0"><h2 style="font-size:20px;color:#111;margin:0 0 8px">¡Hola! Tenemos ofertas especiales para ti</h2><p style="color:#666;font-size:14px;line-height:1.6;margin:0">Este mes preparamos los mejores descuentos en repuestos, accesorios y equipos para tu moto. ¡No te los pierdas!</p></div><div style="margin:24px;background:#fff7ed;border:2px solid #f59e0b;border-radius:12px;padding:20px;text-align:center"><p style="margin:0 0 4px;font-size:12px;font-weight:bold;color:#d97706;text-transform:uppercase;letter-spacing:1px">⭐ Oferta destacada</p><h3 style="font-size:22px;color:#111;margin:8px 0">NOMBRE DEL PRODUCTO</h3><div style="margin:12px 0"><span style="font-size:28px;font-weight:bold;color:#dc2626">$XX.XXX</span>&nbsp;<span style="font-size:18px;color:#999;text-decoration:line-through">$XX.XXX</span>&nbsp;<span style="background:#dc2626;color:#fff;font-size:13px;font-weight:bold;padding:4px 10px;border-radius:20px">-XX%</span></div><p style="color:#666;font-size:13px;margin:0 0 16px">Descripción breve del producto o condiciones de la oferta</p><a href="https://motospa.com.co/catalogo" style="background:#f59e0b;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block">Ver oferta →</a></div><div style="padding:0 24px"><h3 style="font-size:16px;color:#111;margin:0 0 16px;padding-bottom:8px;border-bottom:2px solid #f3f4f6">Más productos en oferta</h3><div style="display:flex;align-items:center;padding:12px 0;border-bottom:1px solid #f3f4f6;gap:16px"><div style="width:60px;height:60px;background:#f3f4f6;border-radius:8px;flex-shrink:0"></div><div style="flex:1"><p style="margin:0 0 2px;font-weight:bold;font-size:14px;color:#111">Nombre del producto 1</p><p style="margin:0;font-size:12px;color:#999">Referencia o descripción corta</p></div><div style="text-align:right"><p style="margin:0;font-weight:bold;color:#dc2626;font-size:15px">$XX.XXX</p><p style="margin:0;font-size:11px;color:#999;text-decoration:line-through">$XX.XXX</p></div></div><div style="display:flex;align-items:center;padding:12px 0;gap:16px"><div style="width:60px;height:60px;background:#f3f4f6;border-radius:8px;flex-shrink:0"></div><div style="flex:1"><p style="margin:0 0 2px;font-weight:bold;font-size:14px;color:#111">Nombre del producto 2</p><p style="margin:0;font-size:12px;color:#999">Referencia o descripción corta</p></div><div style="text-align:right"><p style="margin:0;font-weight:bold;color:#dc2626;font-size:15px">$XX.XXX</p><p style="margin:0;font-size:11px;color:#999;text-decoration:line-through">$XX.XXX</p></div></div></div><div style="text-align:center;padding:24px"><a href="https://motospa.com.co/catalogo?en_oferta=true" style="background:#111;color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;display:inline-block">Ver todas las ofertas →</a></div><div style="background:#f9fafb;padding:20px 24px;text-align:center;border-top:1px solid #e5e7eb"><p style="margin:0 0 6px;font-weight:bold;color:#111;font-size:13px">Moto Spa</p><p style="margin:0;color:#999;font-size:12px">Si no deseas recibir más correos, ingresa a tu cuenta en <a href="https://motospa.com.co/cuenta/perfil" style="color:#f59e0b">motospa.com.co</a> y desactiva el boletín.</p></div></div>';

        $productBody = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333;background:#fff"><div style="background:#111;padding:28px 24px;text-align:center"><h1 style="color:#fff;margin:0;font-size:24px">MOTO SPA</h1><p style="color:#6b7280;margin:6px 0 0;font-size:13px">Novedades</p></div><div style="padding:32px 24px;text-align:center"><span style="background:#dcfce7;color:#16a34a;font-size:12px;font-weight:bold;padding:4px 14px;border-radius:20px;text-transform:uppercase;letter-spacing:1px">✨ Nuevo en catálogo</span><h2 style="font-size:24px;color:#111;margin:16px 0 8px">NOMBRE DEL PRODUCTO</h2><p style="color:#666;font-size:14px;line-height:1.7;max-width:420px;margin:0 auto 20px">Descripción del producto. Explica qué lo hace especial, para qué tipo de moto aplica, y por qué tus clientes lo necesitan.</p><div style="background:#f3f4f6;border-radius:12px;height:200px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;max-width:300px"><p style="color:#9ca3af;font-size:13px">[Imagen del producto]</p></div><p style="font-size:28px;font-weight:bold;color:#111;margin:0 0 20px">$XX.XXX</p><a href="https://motospa.com.co/catalogo" style="background:#111;color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block">Ver producto →</a></div><div style="padding:0 24px 28px"><h3 style="font-size:15px;color:#111;margin:0 0 14px">Características destacadas</h3><ul style="padding:0;margin:0;list-style:none"><li style="padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:13px;color:#444">✅ Característica 1</li><li style="padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:13px;color:#444">✅ Característica 2</li><li style="padding:8px 0;font-size:13px;color:#444">✅ Característica 3</li></ul></div><div style="background:#f9fafb;padding:20px 24px;text-align:center;border-top:1px solid #e5e7eb"><p style="margin:0;color:#999;font-size:12px">© Moto Spa · <a href="https://motospa.com.co/cuenta/perfil" style="color:#6b7280">Desuscribirse</a></p></div></div>';

        $flashBody = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333;background:#fff"><div style="background:linear-gradient(135deg,#dc2626,#b91c1c);padding:32px 24px;text-align:center"><p style="color:#fca5a5;font-size:12px;font-weight:bold;margin:0 0 6px;text-transform:uppercase;letter-spacing:2px">⚡ Oferta por tiempo limitado</p><h1 style="color:#fff;margin:0;font-size:32px;font-weight:900">LIQUIDACIÓN</h1><p style="color:#fecaca;margin:8px 0 0;font-size:15px">Hasta <strong>XX% de descuento</strong> · Solo hasta el DD/MM</p></div><div style="background:#1a1a1a;padding:16px 24px;text-align:center"><p style="color:#f59e0b;font-size:13px;font-weight:bold;margin:0">⏰ La oferta termina pronto — ¡Aprovecha ahora!</p></div><div style="padding:28px 24px"><p style="font-size:14px;color:#666;line-height:1.6;margin:0 0 24px">Hola, tenemos una liquidación especial de inventario. Estos productos tienen descuentos por tiempo limitado, ¡no esperes más!</p><div style="border:2px dashed #dc2626;border-radius:12px;padding:20px;text-align:center;margin-bottom:20px"><p style="margin:0 0 4px;font-size:11px;font-weight:bold;color:#dc2626;text-transform:uppercase">🔥 Hasta agotar existencias</p><h3 style="font-size:20px;color:#111;margin:8px 0">PRODUCTO EN LIQUIDACIÓN</h3><div style="margin:12px 0"><span style="font-size:32px;font-weight:900;color:#dc2626">$XX.XXX</span>&nbsp;<span style="font-size:18px;color:#9ca3af;text-decoration:line-through">$XX.XXX</span></div><a href="https://motospa.com.co/catalogo?en_oferta=true" style="background:#dc2626;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block;margin-top:8px">Comprar ahora →</a></div><p style="font-size:13px;color:#9ca3af;text-align:center;margin:0">* Precios válidos hasta agotar existencias o hasta el DD/MM/YYYY</p></div><div style="background:#f9fafb;padding:20px 24px;text-align:center;border-top:1px solid #e5e7eb"><p style="margin:0;color:#999;font-size:12px"><a href="https://motospa.com.co/cuenta/perfil" style="color:#6b7280">Desuscribirse del boletín</a></p></div></div>';

        $eventBody = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333;background:#fff"><div style="background:#1e3a5f;padding:32px 24px;text-align:center"><h1 style="color:#fff;margin:0;font-size:24px">MOTO SPA</h1><p style="color:#93c5fd;margin:8px 0 0;font-size:13px">Te invita a</p></div><div style="padding:32px 24px;text-align:center"><span style="background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:bold;padding:4px 14px;border-radius:20px;text-transform:uppercase;letter-spacing:1px">📅 Evento especial</span><h2 style="font-size:26px;color:#111;margin:16px 0 8px">NOMBRE DEL EVENTO</h2><p style="color:#666;font-size:14px;line-height:1.7;margin:0 auto 24px;max-width:420px">Descripción del evento, taller o actividad. Explica de qué trata y por qué vale la pena asistir.</p><div style="background:#f8fafc;border-radius:12px;padding:20px;text-align:left;margin-bottom:24px"><div style="padding:10px 0;border-bottom:1px solid #e5e7eb"><p style="margin:0;font-size:12px;color:#9ca3af">📅 Fecha</p><p style="margin:4px 0 0;font-weight:bold;color:#111;font-size:14px">DD de Mes de YYYY</p></div><div style="padding:10px 0;border-bottom:1px solid #e5e7eb"><p style="margin:0;font-size:12px;color:#9ca3af">🕐 Hora</p><p style="margin:4px 0 0;font-weight:bold;color:#111;font-size:14px">9:00 AM – 5:00 PM</p></div><div style="padding:10px 0"><p style="margin:0;font-size:12px;color:#9ca3af">📍 Lugar</p><p style="margin:4px 0 0;font-weight:bold;color:#111;font-size:14px">Dirección del evento</p></div></div><a href="https://motospa.com.co" style="background:#1e3a5f;color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block">Más información →</a></div><div style="background:#f9fafb;padding:20px 24px;text-align:center;border-top:1px solid #e5e7eb"><p style="margin:0;color:#999;font-size:12px">© Moto Spa · <a href="https://motospa.com.co/cuenta/perfil" style="color:#6b7280">Desuscribirse</a></p></div></div>';

        $rows = [
            ['name' => 'Promociones del mes', 'description' => 'Plantilla para enviar promociones mensuales con lista de ofertas', 'category' => 'promo', 'is_default' => true, 'html_body' => $promoBody],
            ['name' => 'Nuevo producto', 'description' => 'Anuncio de lanzamiento de un nuevo producto o referencia', 'category' => 'product', 'is_default' => false, 'html_body' => $productBody],
            ['name' => 'Oferta flash / Liquidación', 'description' => 'Oferta por tiempo limitado o liquidación de inventario', 'category' => 'promo', 'is_default' => false, 'html_body' => $flashBody],
            ['name' => 'Evento / Taller', 'description' => 'Invitación a eventos, talleres o fechas especiales', 'category' => 'event', 'is_default' => false, 'html_body' => $eventBody],
        ];

        foreach ($rows as $row) {
            DB::table('newsletter_templates')->insert([...$row, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
        Schema::dropIfExists('newsletter_templates');
    }
};
