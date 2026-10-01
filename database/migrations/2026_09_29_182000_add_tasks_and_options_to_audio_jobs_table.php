<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_jobs', function (Blueprint $table) {
            $table->json('tasks')->nullable()->after('kind');
            $table->json('task_state')->nullable()->after('tasks');
            $table->json('options')->nullable()->after('task_state');
        });
    }

    public function down(): void
    {
        Schema::table('audio_jobs', function (Blueprint $table) {
            $table->dropColumn(['tasks', 'task_state', 'options']);
        });
    }
};
