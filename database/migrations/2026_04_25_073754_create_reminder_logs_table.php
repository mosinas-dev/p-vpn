<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', [
                'topup_needed_d7',
                'topup_needed_d3',
                'topup_needed_d1',
                'expired',
                'grace_end',
                'auto_renewed',
            ]);
            $table->timestamp('sent_at')->useCurrent();

            $table->unique(['subscription_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
