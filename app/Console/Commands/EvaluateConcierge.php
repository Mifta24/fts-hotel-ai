<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Services\Concierge\ConciergeEvaluator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('concierge:eval {--hotel=demo : Slug of the hotel whose data the concierge answers from} {--only= : Run only scenarios whose name contains this text} {--keep : Keep the test conversations instead of deleting them}')]
#[Description('Run the concierge against fixed guest messages and report any answer that breaks the rules. Run it after changing the model or the prompt.')]
class EvaluateConcierge extends Command
{
    public function handle(ConciergeEvaluator $evaluator): int
    {
        $hotel = Hotel::where('slug', $this->option('hotel'))->first();

        if (! $hotel) {
            $this->error("No hotel with slug \"{$this->option('hotel')}\".");

            return self::FAILURE;
        }

        // A scenario may trigger a handover; the team must not be mailed for a test.
        Notification::fake();

        $scenarios = collect(ConciergeEvaluator::scenarios())
            ->when($this->option('only'), fn ($all, $only) => $all->filter(fn (array $scenario) => str_contains($scenario['name'], (string) $only)))
            ->values()
            ->all();

        $this->line('Model: '.config('services.local_llm.model').' · hotel: '.$hotel->slug.' · '.count($scenarios).' scenario(s) — this calls the real model.');

        $results = $evaluator->run($hotel, $scenarios, (bool) $this->option('keep'));

        foreach ($results as $result) {
            $this->line(($result['passed'] ? '<info>PASS</info>' : '<error>FAIL</error>').'  '.$result['name']);

            foreach ($result['failures'] as $failure) {
                $this->line("      - {$failure}");
            }

            if (! $result['passed'] && $result['reply'] !== null) {
                $this->line('      reply: '.str($result['reply'])->limit(200));
            }
        }

        $failed = collect($results)->where('passed', false)->count();
        $this->newLine();
        $this->line(($failed === 0 ? '<info>All ' : "<error>{$failed} of ").count($results).' scenarios '.($failed === 0 ? 'passed.</info>' : 'failed.</error>'));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
