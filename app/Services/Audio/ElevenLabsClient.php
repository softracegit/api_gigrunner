<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElevenLabsClient
{
    public function __construct(
        private ?string $apiKey = null,
        private ?string $baseUrl = null,
        private ?int $timeout = null,
    ) {
        $this->apiKey = $apiKey ?? (string) config('audio.providers.elevenlabs.api_key', '');
        $this->baseUrl = rtrim($baseUrl ?? (string) config('audio.providers.elevenlabs.base_url', 'https://api.elevenlabs.io'), '/');
        $this->timeout = $timeout ?? (int) config('audio.providers.elevenlabs.timeout', 300);
    }

    /**
     * Compose music from a text prompt. Returns raw audio bytes + metadata.
     *
     * @param  array{prompt: string, music_length_ms?: int|null, model_id?: string, force_instrumental?: bool, output_format?: string}  $params
     * @return array{bytes: string, content_type: string, song_id: ?string, output_format: string}
     */
    public function composeMusic(array $params): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ELEVENLABS_API_KEY não está configurada.');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
        @ini_set('max_execution_time', '600');

        $outputFormat = $params['output_format'] ?? (string) config('audio.providers.elevenlabs.output_format', 'mp3_44100_128');
        $body = array_filter([
            'prompt' => $params['prompt'],
            'music_length_ms' => $params['music_length_ms'] ?? null,
            'model_id' => $params['model_id'] ?? (string) config('audio.providers.elevenlabs.model_id', 'music_v1'),
            'force_instrumental' => $params['force_instrumental'] ?? false,
        ], fn ($v) => $v !== null && $v !== '');

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Accept' => 'audio/mpeg, application/json',
        ])
            ->timeout($this->timeout)
            ->connectTimeout(20)
            ->withQueryParameters(['output_format' => $outputFormat])
            ->post($this->baseUrl.'/v1/music', $body);

        if (! $response->successful()) {
            $message = $response->json('detail.0.msg')
                ?? $response->json('detail')
                ?? $response->body();
            if (is_array($message)) {
                $message = json_encode($message);
            }

            throw new RuntimeException('ElevenLabs music compose failed: '.$message);
        }

        $bytes = $response->body();
        if ($bytes === '' || $bytes === false) {
            throw new RuntimeException('ElevenLabs devolveu áudio vazio.');
        }

        return [
            'bytes' => $bytes,
            'content_type' => $response->header('Content-Type') ?: 'audio/mpeg',
            'song_id' => $response->header('song-id')
                ?? $response->header('x-song-id')
                ?? null,
            'output_format' => $outputFormat,
        ];
    }

    /**
     * Separate an audio file into stems. Returns a ZIP archive as bytes.
     *
     * @param  array{absolute_path: string, filename?: string, stem_variation_id?: string, output_format?: string}  $params
     * @return array{bytes: string, content_type: string, stem_variation_id: string, output_format: string}
     */
    public function separateStems(array $params): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ELEVENLABS_API_KEY não está configurada.');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
        @ini_set('max_execution_time', '600');

        $absolutePath = $params['absolute_path'] ?? '';
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            throw new RuntimeException('Ficheiro de áudio inválido para stem separation.');
        }

        $outputFormat = $params['output_format']
            ?? (string) config('audio.providers.elevenlabs.stems_output_format', 'mp3_44100_128');
        $variation = $params['stem_variation_id']
            ?? (string) config('audio.providers.elevenlabs.stem_variation_id', 'six_stems_v1');
        $filename = $params['filename'] ?? basename($absolutePath);

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Accept' => 'application/zip, application/json',
        ])
            ->timeout($this->timeout)
            ->connectTimeout(20)
            ->attach('file', file_get_contents($absolutePath), $filename)
            ->withQueryParameters(['output_format' => $outputFormat])
            ->post($this->baseUrl.'/v1/music/stem-separation', [
                'stem_variation_id' => $variation,
            ]);

        if (! $response->successful()) {
            $message = $response->json('detail.0.msg')
                ?? $response->json('detail')
                ?? $response->body();
            if (is_array($message)) {
                $message = json_encode($message);
            }

            throw new RuntimeException('ElevenLabs stem separation failed: '.$message);
        }

        $bytes = $response->body();
        if ($bytes === '' || $bytes === false) {
            throw new RuntimeException('ElevenLabs devolveu ZIP de stems vazio.');
        }

        return [
            'bytes' => $bytes,
            'content_type' => $response->header('Content-Type') ?: 'application/zip',
            'stem_variation_id' => $variation,
            'output_format' => $outputFormat,
        ];
    }
}
