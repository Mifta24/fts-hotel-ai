<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HotelPageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function show(Request $request, string $hotelSlug): View
    {
        $hotel = Hotel::where('slug', $hotelSlug)->firstOrFail();

        abort_if(! $hotel->isPublished(), 404);

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : $hotel->default_locale;

        $roomTypes = $hotel->roomTypes()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('hotel.show', [
            'hotel' => $hotel,
            'roomTypes' => $roomTypes,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $this->labels($locale),
        ]);
    }

    private function labels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'from' => 'from',
                'per_night' => '/ night',
                'ask_ai' => 'Ask the AI Concierge',
                'rooms_heading' => 'Rooms & Suites',
                'chat_heading' => 'AI Concierge',
                'chat_subtitle' => 'Ask about rooms, facilities, or your stay — available 24/7.',
                'chat_placeholder' => 'Type your message…',
                'chat_send' => 'Send',
                'chat_intro' => "Hi! I'm the AI Concierge here. Ask me about rooms, facilities, or anything about your stay.",
                'breakfast_included' => 'Breakfast included',
                'max_guests' => 'guests',
                'handed_over' => 'A staff member has joined this conversation and will reply shortly.',
                'thinking' => 'Thinking…',
                'view_details' => 'View details',
                'book_now' => 'Book this room',
                'menu_heading' => 'Start here',
                'menu_rooms' => 'Find a room',
                'menu_rooms_q' => 'I would like to see the available rooms.',
                'menu_facilities' => 'Hotel facilities',
                'menu_facilities_q' => 'What facilities does the hotel have?',
                'menu_policies' => 'Policies & FAQ',
                'menu_policies_q' => 'What are the check-in, check-out, and cancellation policies?',
                'menu_staff' => 'Talk to staff',
                'menu_staff_q' => 'I would like to speak with hotel staff.',
            ],
            'ja' => [
                'from' => '',
                'per_night' => '〜 / 泊',
                'ask_ai' => 'AIコンシェルジュに聞く',
                'rooms_heading' => '客室・スイート',
                'chat_heading' => 'AIコンシェルジュ',
                'chat_subtitle' => '客室・設備・ご滞在について24時間いつでもお尋ねください。',
                'chat_placeholder' => 'メッセージを入力…',
                'chat_send' => '送信',
                'chat_intro' => 'こんにちは。AIコンシェルジュです。客室や設備、ご滞在について何でもお尋ねください。',
                'breakfast_included' => '朝食付き',
                'max_guests' => '名まで',
                'handed_over' => 'スタッフがこの会話に参加しました。まもなく返信いたします。',
                'thinking' => '入力中…',
                'view_details' => '詳細を見る',
                'book_now' => 'この部屋を予約',
                'menu_heading' => 'ここから始める',
                'menu_rooms' => '客室を探す',
                'menu_rooms_q' => '空いている部屋を見せてください。',
                'menu_facilities' => 'ホテル施設',
                'menu_facilities_q' => 'ホテルにはどんな施設がありますか？',
                'menu_policies' => '規定・よくある質問',
                'menu_policies_q' => 'チェックイン、チェックアウト、キャンセルのポリシーを教えてください。',
                'menu_staff' => 'スタッフに相談',
                'menu_staff_q' => 'ホテルスタッフと話したいです。',
            ],
            default => [
                'from' => 'mulai dari',
                'per_night' => '/ malam',
                'ask_ai' => 'Tanya AI Concierge',
                'rooms_heading' => 'Kamar & Suite',
                'chat_heading' => 'AI Concierge',
                'chat_subtitle' => 'Tanyakan kamar, fasilitas, atau rencana menginap Anda — siap 24 jam.',
                'chat_placeholder' => 'Tulis pesan Anda…',
                'chat_send' => 'Kirim',
                'chat_intro' => 'Halo! Saya AI Concierge hotel ini. Tanyakan apa saja soal kamar, fasilitas, atau rencana menginap Anda.',
                'breakfast_included' => 'Termasuk sarapan',
                'max_guests' => 'tamu',
                'handed_over' => 'Staf kami telah bergabung dalam percakapan ini dan akan segera membalas.',
                'thinking' => 'Sedang mengetik…',
                'view_details' => 'Lihat detail',
                'book_now' => 'Pesan kamar ini',
                'menu_heading' => 'Mulai dari sini',
                'menu_rooms' => 'Cari kamar',
                'menu_rooms_q' => 'Saya ingin melihat pilihan kamar yang tersedia.',
                'menu_facilities' => 'Fasilitas hotel',
                'menu_facilities_q' => 'Apa saja fasilitas yang tersedia di hotel ini?',
                'menu_policies' => 'Kebijakan & FAQ',
                'menu_policies_q' => 'Apa kebijakan check-in, check-out, dan pembatalan?',
                'menu_staff' => 'Bicara dengan staf',
                'menu_staff_q' => 'Saya ingin bicara dengan staf hotel.',
            ],
        };
    }
}
