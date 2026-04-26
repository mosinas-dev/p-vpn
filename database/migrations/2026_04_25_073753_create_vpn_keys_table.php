<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->unsignedBigInteger('panel_server_id');
            $table->unsignedBigInteger('panel_client_id');
            $table->string('name');
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->longText('config_text')->nullable();
            $table->longText('qr_code_base64')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index('subscription_id');
            $table->index(['status', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_keys');
    }
};
