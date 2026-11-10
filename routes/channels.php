<?php

use App\Models\AudioJob;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('audio-jobs.{uuid}', function ($user, string $uuid) {
    return AudioJob::query()
        ->where('uuid', $uuid)
        ->where('user_id', $user->id)
        ->exists();
});
