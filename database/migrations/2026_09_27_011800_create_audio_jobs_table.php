<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32); // analyze|transcribe
            $table->string('provider', 40)->default('magic_chords');
            $table->string('status', 32)->default('queued'); // queued|processing|complete|failed
            $table->string('external_job_id')->nullable()->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('message')->nullable();
            $table->string('source_type', 16); // file|url
            $table->string('source_path')->nullable();
            $table->text('source_url')->nullable();
            $table->unsignedSmallInteger('credits_spent')->default(0);
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_jobs');
    }
};
