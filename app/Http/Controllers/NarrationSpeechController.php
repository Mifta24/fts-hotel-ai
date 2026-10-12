<?php

namespace App\Http\Controllers;

use App\Services\Narration\NarrationSpeech;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NarrationSpeechController extends Controller
{
    public function __construct(private readonly NarrationSpeech $speech) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($this->speech->isConfigured(), 503);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.NarrationSpeech::MAX_TEXT_LENGTH],
            'locale' => ['required', Rule::in(NarrationSpeech::SUPPORTED_LOCALES)],
            'signature' => ['required', 'string'],
        ]);

        abort_unless($this->speech->hasValidSignature($data['text'], $data['locale'], $data['signature']), 403);

        try {
            $audio = $this->speech->audio($data['text'], $data['locale']);
        } catch (\Throwable $e) {
            Log::error('Narration speech failed', ['locale' => $data['locale'], 'error' => $e->getMessage()]);

            abort(503);
        }

        return response($audio, 200, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
