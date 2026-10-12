<?php

namespace Tests\Feature;

use App\Services\Narration\NarrationSpeech;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NarrationSpeechTest extends TestCase
{
    private const TEXT = 'Selamat datang di hotel kami.';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'services.openrouter.key' => 'test-key',
            'services.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'services.openrouter.tts_model' => 'google/gemini-3.8-flash-lite-tts',
            'services.openrouter.tts_voice' => 'Sulafat',
        ]);
    }

    private function fakeSpeech(): void
    {
        Http::fake(['openrouter.test/*' => Http::response(str_repeat('ab', 100), 200, ['Content-Type' => 'audio/pcm;rate=24000;channels=1'])]);
    }

    /**
     * @return array{text: string, locale: string, signature: string}
     */
    private function payload(string $text = self::TEXT, string $locale = 'id'): array
    {
        return ['text' => $text, 'locale' => $locale, 'signature' => app(NarrationSpeech::class)->signature($text, $locale)];
    }

    public function test_it_returns_synthesised_audio_for_a_signed_narration(): void
    {
        $this->fakeSpeech();

        $response = $this->postJson(route('narration.speech'), $this->payload())
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/wav');

        $wav = $response->getContent();
        $this->assertSame('RIFF', substr($wav, 0, 4));
        $this->assertSame('WAVE', substr($wav, 8, 4));
        $this->assertSame(24000, unpack('V', substr($wav, 24, 4))[1]);
        $this->assertSame(200, unpack('V', substr($wav, 40, 4))[1]);
        $this->assertSame(str_repeat('ab', 100), substr($wav, 44));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://openrouter.test/api/v1/audio/speech'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'google/gemini-3.8-flash-lite-tts'
            && $request['voice'] === 'Sulafat'
            && $request['input'] === self::TEXT
            && $request['response_format'] === 'pcm');
    }

    public function test_it_only_synthesises_a_narration_once(): void
    {
        $this->fakeSpeech();

        $this->postJson(route('narration.speech'), $this->payload())->assertOk();
        $this->postJson(route('narration.speech'), $this->payload())->assertOk();

        Http::assertSentCount(1);
    }

    public function test_it_rejects_text_that_was_not_signed(): void
    {
        $this->fakeSpeech();

        $this->postJson(route('narration.speech'), [...$this->payload(), 'text' => 'Something else entirely.'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_it_rejects_an_unsupported_locale(): void
    {
        $this->fakeSpeech();

        $this->postJson(route('narration.speech'), $this->payload(locale: 'fr'))
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_it_is_unavailable_when_the_provider_fails(): void
    {
        Http::fake(['openrouter.test/*' => Http::response(['error' => 'boom'], 500)]);

        $this->postJson(route('narration.speech'), $this->payload())->assertServiceUnavailable();
    }

    public function test_it_is_unavailable_without_an_api_key(): void
    {
        config(['services.openrouter.key' => null]);
        $this->fakeSpeech();

        $this->postJson(route('narration.speech'), $this->payload())->assertServiceUnavailable();

        Http::assertNothingSent();
    }
}
