<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AudioJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastTestController
{
    /**
     * Smoke-test: create (or reuse) a dummy job and broadcast a status event via Pusher.
     */
    public function ping(Request $request): JsonResponse
    {
        $user = $request->user();

        $job = AudioJob::query()
            ->where('user_id', $user->id)
            ->where('source_name', 'pusher-ping')
            ->latest('id')
            ->first();

        if (! $job) {
            $job = new AudioJob([
                'user_id' => $user->id,
                'kind' => AudioJob::KIND_ANALYZE,
                'tasks' => [AudioJob::KIND_ANALYZE],
                'task_state' => [
                    AudioJob::KIND_ANALYZE => [
                        'status' => AudioJob::STATUS_PROCESSING,
                        'progress' => 10,
                        'message' => 'Pusher ping',
                    ],
                ],
                'provider' => 'pusher_test',
                'status' => AudioJob::STATUS_PROCESSING,
                'progress' => 10,
                'message' => 'Pusher ping',
                'source_type' => 'url',
                'source_url' => 'https://example.com/pusher-ping.mp3',
                'source_name' => 'pusher-ping',
                'credits_spent' => 0,
            ]);
            $job->save();
        } else {
            $progress = min(100, ((int) $job->progress) + 15);
            $status = $progress >= 100 ? AudioJob::STATUS_COMPLETE : AudioJob::STATUS_PROCESSING;
            $job->progress = $progress;
            $job->status = $status;
            $job->message = $status === AudioJob::STATUS_COMPLETE
                ? 'Pusher ping complete'
                : 'Pusher ping '.$progress.'%';
            $job->task_state = [
                AudioJob::KIND_ANALYZE => [
                    'status' => $status,
                    'progress' => $progress,
                    'message' => $job->message,
                ],
            ];
            $job->save();
        }

        return response()->json([
            'ok' => true,
            'channel' => 'private-audio-jobs.'.$job->uuid,
            'event' => 'AudioJobStatusUpdated',
            'job' => $job->fresh()->toStatusArray(),
            'hint' => 'Subscribe to the channel with Pusher JS, then call this endpoint again to see events.',
        ]);
    }
}
