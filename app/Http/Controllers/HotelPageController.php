<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HotelPageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function index(): View|RedirectResponse
    {
        $hotels = Hotel::where('public_status', 'published')->orderBy('name')->get();

        if ($hotels->count() === 1) {
            return redirect()->route('hotel.show', $hotels->first()->slug);
        }

        return view('welcome', ['hotels' => $hotels]);
    }

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
            'lobby' => $this->lobbyLabels($locale),
            'facilities' => $hotel->knowledgeItems()->where('is_active', true)
                ->whereIn('category', ['facilities', 'dining', 'transport'])
                ->orderBy('sort_order')->get(),
        ]);
    }

    private function lobbyLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Welcome to your', 'lobby' => 'virtual lobby',
                'intro' => 'A warm welcome. A wonderful stay. Let me take care of the details.',
                'home' => 'Lobby', 'explore' => 'Explore your stay', 'reservation' => 'Reservations',
                'reservation_intro' => 'Tell your AI Concierge your dates and number of guests. We will help you find a room and submit a booking request.',
                'reservation_q' => 'I would like to book a room. Please help me check availability.',
                'start_booking' => 'Plan my stay', 'available' => 'Here to help, 24/7',
                'assistant' => 'Your virtual host', 'illustration' => 'AI illustration',
                'staff_intro' => 'Need a personal touch? Send a message and we will connect you with the hotel team.',
                'empty' => 'Ask your AI Concierge for more information.', 'back' => 'Back to lobby',
                'check_in' => 'Check-in', 'check_out' => 'Check-out', 'location' => 'Find us',
                'connection_error' => 'Chat could not connect. Please reload to try again.',
                'rooms_empty' => 'Room information will be available soon. Please ask our team.',
            ],
            'ja' => [
                'welcome' => 'ようこそ', 'lobby' => 'バーチャルロビー',
                'intro' => '心を込めたおもてなしで、素敵なご滞在をお手伝いします。',
                'home' => 'ロビー', 'explore' => 'ご滞在のご案内', 'reservation' => 'ご予約',
                'reservation_intro' => 'ご希望の日程と人数をAIコンシェルジュにお伝えください。空室確認と予約リクエストをお手伝いします。',
                'reservation_q' => '部屋を予約したいです。空室を確認してください。',
                'start_booking' => '滞在を計画する', 'available' => '24時間お手伝いします',
                'assistant' => 'バーチャルホスト', 'illustration' => 'AIイラスト',
                'staff_intro' => 'ホテルのスタッフがお手伝いします。メッセージを送ってご相談ください。',
                'empty' => '詳しくはAIコンシェルジュにお尋ねください。', 'back' => 'ロビーに戻る',
                'check_in' => 'チェックイン', 'check_out' => 'チェックアウト', 'location' => 'アクセス',
                'connection_error' => 'チャットに接続できませんでした。再読み込みしてください。',
                'rooms_empty' => '客室情報は準備中です。スタッフにお尋ねください。',
            ],
            default => [
                'welcome' => 'Selamat datang di', 'lobby' => 'lobi virtual Anda',
                'intro' => 'Sambutan hangat. Pengalaman istimewa. Biarkan saya membantu rencana menginap Anda.',
                'home' => 'Beranda', 'explore' => 'Jelajahi hotel', 'reservation' => 'Reservasi',
                'reservation_intro' => 'Ceritakan tanggal menginap dan jumlah tamu kepada AI Concierge. Kami bantu cek ketersediaan kamar hingga pengajuan reservasi.',
                'reservation_q' => 'Saya ingin reservasi kamar. Bantu saya cek ketersediaan.',
                'start_booking' => 'Rencanakan menginap', 'available' => 'Siap membantu, 24 jam',
                'assistant' => 'Resepsionis virtual Anda', 'illustration' => 'Ilustrasi AI',
                'staff_intro' => 'Butuh bantuan langsung? Kirim pesan untuk terhubung dengan tim hotel kami.',
                'empty' => 'Tanyakan informasi selengkapnya kepada AI Concierge.', 'back' => 'Kembali ke lobi',
                'check_in' => 'Check-in', 'check_out' => 'Check-out', 'location' => 'Lokasi hotel',
                'connection_error' => 'Chat belum tersambung. Muat ulang halaman untuk mencoba lagi.',
                'rooms_empty' => 'Informasi kamar segera tersedia. Silakan tanyakan kepada staf kami.',
            ],
        };
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
