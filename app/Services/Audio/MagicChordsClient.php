<?php

namespace App\Services\Audio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MagicChordsClient
{
    public function __construct(
        private ?string $baseUrl = null,
        private ?int $timeout = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('audio.providers.magic_chords.base_url'), '/');
        $this->timeout = $timeout ?? (int) config('audio.providers.magic_chords.timeout', 60);
    }

    public function submitFile(string $absolutePath, string $kind): array
    {
        $endpoint = $kind === 'transcribe' ? '/transcribe' : '/analyze';

        $response = $this->http()
            ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
            ->post($this->baseUrl.$endpoint);

        if (! $response->successful()) {
            throw new RuntimeException('Magic Chords upload failed: '.$response->body());
        }

        return $response->json();
    }

    public function submitUrl(string $url, string $kind): array
    {
        $endpoint = $kind === 'transcribe' ? '/transcribe/url' : '/analyze/url';

        $response = $this->http()->post($this->baseUrl.$endpoint, [
            'url' => $url,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Magic Chords URL submit failed: '.$response->body());
        }

        return $response->json();
    }

    public function status(string $externalJobId): array
    {
        $response = $this->http()->get($this->baseUrl.'/jobs/'.$externalJobId);

        if (! $response->successful()) {
            throw new RuntimeException('Magic Chords status failed: '.$response->body());
        }

        return $response->json();
    }

    public function result(string $externalJobId): array
    {
        $response = $this->http()->get($this->baseUrl.'/jobs/'.$externalJobId.'/result');

        if (! $response->successful()) {
            throw new RuntimeException('Magic Chords result failed: '.$response->body());
        }

        return $response->json();
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout($this->timeout)
            ->connectTimeout(15);
    }
}
