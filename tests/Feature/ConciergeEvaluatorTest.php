<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Hotel;
use App\Services\Concierge\ConciergeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConciergeEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.local_llm.base_url' => 'http://llm.test', 'services.local_llm.api_key' => 'k', 'services.local_llm.model' => 'test-model']);
        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en']);
    }

    private function reply(string $content): array
    {
        return ['choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']]];
    }

    public function test_every_check_catches_the_failure_it_is_named_for(): void
    {
        $evaluator = app(ConciergeEvaluator::class);

        $this->assertSame([], $evaluator->check(['tool_any' => ['search_rooms'], 'language' => 'en'], 'Here are the rooms you can book with us today.', ['search_rooms'], 'x'));
        $this->assertNotSame([], $evaluator->check(['tool_any' => ['search_rooms']], 'Sure', [], 'x'));
        $this->assertNotSame([], $evaluator->check(['no_tools' => true], 'Sure', ['show_menu'], 'x'));
        $this->assertNotSame([], $evaluator->check(['no_price_without_tool' => true], 'It costs Rp 950.000 per night', [], 'x'));
        $this->assertSame([], $evaluator->check(['no_price_without_tool' => true], 'It costs Rp 950.000 per night', ['check_availability'], 'x'));
        $this->assertNotSame([], $evaluator->check(['language' => 'ja'], 'What is your question about the rooms?', [], 'x'));
        $this->assertNotSame([], $evaluator->check(['max_length' => 10], 'This is much too long', [], 'x'));
        $this->assertNotSame([], $evaluator->check(['not_contains' => ['/\bparis\b/i']], 'It is Paris.', [], 'x'));
        $this->assertNotSame([], $evaluator->check(['is_refusal' => true], 'Sure, you idiot', [], 'Dasar goblok'));
    }

    public function test_a_run_reports_pass_and_fail_and_removes_its_conversations(): void
    {
        Http::fake(['llm.test/*' => Http::response($this->reply('The capital of France is Paris.'))]);

        $results = app(ConciergeEvaluator::class)->run($this->hotel, [
            ['name' => 'off topic', 'locale' => 'en', 'message' => 'What is the capital of France?', 'checks' => ['no_tools' => true, 'not_contains' => ['/\bparis\b/i']]],
            ['name' => 'plain greeting', 'locale' => 'en', 'message' => 'Hello there', 'checks' => ['no_tools' => true]],
        ]);

        $this->assertFalse($results[0]['passed']);
        $this->assertTrue($results[1]['passed']);
        $this->assertSame(0, Conversation::count());
    }

    public function test_a_model_outage_is_reported_as_a_failed_scenario_not_a_crash(): void
    {
        Http::fake(['llm.test/*' => Http::response('down', 500)]);

        $results = app(ConciergeEvaluator::class)->run($this->hotel, [
            ['name' => 'any', 'locale' => 'en', 'message' => 'Hello there', 'checks' => []],
        ]);

        $this->assertFalse($results[0]['passed']);
        $this->assertStringContainsString('error', $results[0]['failures'][0]);
    }

    public function test_the_command_exits_with_failure_when_a_scenario_fails(): void
    {
        Http::fake(['llm.test/*' => Http::response($this->reply('Paris.'))]);

        $this->artisan('concierge:eval', ['--only' => 'refuses general knowledge'])->assertFailed();
        $this->artisan('concierge:eval', ['--hotel' => 'nope'])->assertFailed();
    }

    public function test_the_built_in_scenarios_cover_prices_scope_language_and_prompt_leaks(): void
    {
        $names = collect(ConciergeEvaluator::scenarios())->pluck('name')->implode(' | ');

        foreach (['price', 'refuses general knowledge', 'Japanese', 'English', 'instructions'] as $needle) {
            $this->assertStringContainsString($needle, $names);
        }
    }
}
