<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('demo_room')->nullable()->index();
            $table->timestampTz('demo_expires_at')->nullable();
        });
        Schema::table('pedidos', function (Blueprint $table) {
            $table->uuid('client_request_id')->nullable();
            $table->uuid('demo_room')->nullable()->index();
            $table->unique(['id_usuario', 'client_request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropUnique(['id_usuario', 'client_request_id']);
            $table->dropColumn(['client_request_id', 'demo_room']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['demo_room', 'demo_expires_at']));
    }
};
