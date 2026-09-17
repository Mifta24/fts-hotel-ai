<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Services\Concierge\ConciergeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('concierge:chat {hotel? : Hotel slug} {--locale=id : id|en|ja}')]
#[Description('Talk to the AI Concierge for a hotel from the terminal, for testing the tool-calling loop before the web UI exists.')]
class ConciergeChatCommand extends Command
{
    public function handle(ConciergeService $service): int
    {
        $hotel = $this->argument('hotel')
            ? Hotel::where('slug', $this->argument('hotel'))->first()
            : Hotel::first();

        if (! $hotel) {
            $this->error('No hotel found. Seed one first: php artisan db:seed');

            return self::FAILURE;
        }

        if (blank(config('services.local_llm.base_url'))) {
            $this->error('LOCAL_LLM_BASE_URL is not set in .env — the concierge has no model endpoint to call.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');
        $conversation = $service->startConversation($hotel, $locale);

        $this->info("Chatting with the AI Concierge for {$hotel->name} ({$locale}). Type 'exit' to quit.");
        $this->newLine();

        while (true) {
            $guestMessage = $this->ask('You');

            if ($guestMessage === null || in_array(trim($guestMessage), ['exit', 'quit'], true)) {
                break;
            }

            $message = $service->reply($hotel, $conversation, $guestMessage);

            $this->newLine();
            $this->line('<fg=cyan>Concierge:</> '.($message->content ?? '(no text — see UI payload below)'));

            if ($message->ui_payload) {
                $this->line('<fg=gray>[ui_payload] '.json_encode($message->ui_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</>');
            }

            $conversation->refresh();
            if ($conversation->isHandedOver()) {
                $this->warn('Conversation handed over to human staff. Ending session.');
                break;
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
