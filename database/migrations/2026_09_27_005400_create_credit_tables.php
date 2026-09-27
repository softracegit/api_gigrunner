<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // ai, ...
            $table->unsignedInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->integer('amount'); // +credit / -consume
            $table->unsignedInteger('balance_after');
            $table->string('reason', 80);
            $table->string('reference')->nullable(); // pack key, payment id, etc.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('credit_balances');
    }
};
