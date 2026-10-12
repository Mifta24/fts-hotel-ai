<?php

namespace App\Services\Narration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns a scene's narration into natural-sounding speech through OpenRouter's
 * text-to-speech endpoint. Narrations are stored hotel text, so each line is
 * synthesised once and the audio is kept on disk for every later guest.
 */
class NarrationSpeech
{
    public const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public const MAX_TEXT_LENGTH = 1500;

    private const REQUEST_TIMEOUT_SECONDS = 30;

    public function isConfigured(): bool
    {
        return filled(config('services.openrouter.key')) && filled(config('services.openrouter.tts_model'));
    }

    /**
     * Proves the text came from a rendered page, so the public endpoint can't
     * be used to synthesise (and bill) arbitrary text.
     */
    public function signature(string $text, string $locale): string
    {
        return hash_hmac('sha256', $locale."\n".$this->normalise($text), (string) config('app.key'));
    }

    public function hasValidSignature(string $text, string $locale, string $signature): bool
    {
        return hash_equals($this->signature($text, $locale), $signature);
    }

    /**
     * @return string WAV bytes.
     *
     * @throws RuntimeException When the provider fails or returns something that isn't audio.
     */
    public function audio(string $text, string $locale): string
    {
        $text = $this->normalise($text);
        $path = $this->cachePath($text, $locale);

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->get($path);
        }

        $response = Http::withToken(config('services.openrouter.key'))
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->post(rtrim(config('services.openrouter.base_url'), '/').'/audio/speech', array_filter([
                'model' => config('services.openrouter.tts_model'),
                'input' => $text,
                'voice' => config('services.openrouter.tts_voice'),
                'response_format' => 'pcm',
            ]));

        if ($response->failed()) {
            throw new RuntimeException("OpenRouter speech request failed: HTTP {$response->status()} — {$response->body()}");
        }

        $pcm = $response->body();
        $contentType = (string) $response->header('Content-Type');

        if ($pcm === '' || ! str_starts_with($contentType, 'audio/')) {
            throw new RuntimeException('OpenRouter speech response was not audio.');
        }

        $audio = $this->wavFromPcm(
            $pcm,
            (int) (preg_match('/rate=(\d+)/', $contentType, $rate) ? $rate[1] : 24000),
            (int) (preg_match('/channels=(\d+)/', $contentType, $channels) ? $channels[1] : 1),
        );

        Storage::disk('local')->put($path, $audio);

        return $audio;
    }

    /**
     * Browsers can't play raw PCM, so wrap the 16-bit little-endian samples in a WAV header.
     */
    private function wavFromPcm(string $pcm, int $sampleRate, int $channels): string
    {
        $bytesPerSample = 2;
        $dataLength = strlen($pcm);

        return 'RIFF'.pack('V', 36 + $dataLength).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, $channels, $sampleRate, $sampleRate * $channels * $bytesPerSample, $channels * $bytesPerSample, $bytesPerSample * 8)
            .'data'.pack('V', $dataLength).$pcm;
    }

    private function normalise(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }

    private function cachePath(string $text, string $locale): string
    {
        $key = hash('sha256', implode("\n", [
            config('services.openrouter.tts_model'),
            config('services.openrouter.tts_voice'),
            $locale,
            $text,
        ]));

        return "narration/{$key}.wav";
    }
}
