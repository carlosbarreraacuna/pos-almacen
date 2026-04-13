<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->boolean('newsletter_subscribed')->default(false)->after('is_active');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->date('birth_date')->nullable()->after('last_name');
            $table->enum('gender', ['masculino', 'femenino', 'otro'])->nullable()->after('birth_date');
            $table->string('document_number')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'password', 'remember_token', 'newsletter_subscribed',
                'first_name', 'last_name', 'birth_date', 'gender', 'document_number'
            ]);
        });
    }
};
