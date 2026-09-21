<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelKnowledgeItem;
use App\Models\RoomType;
use App\Services\Reservation\ReservationHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HotelPageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly ReservationHandover $handover) {}

    /**
     * What the AI concierge says when the guest steps into the rooms scenes.
     * Every sentence is built from stored hotel data, never generated.
     *
     * @var array<string, array<string, string>>
     */
    private const NARRATION = [
        'en' => [
            'rooms' => 'Welcome to our Rooms & Suites. We have :count room types, starting from :price per night. Choose a room to see its photos and details, or ask me anything.',
            'rooms_one' => 'Welcome to our Rooms & Suites. We have one room type, from :price per night. Open it to see its photos and details, or ask me anything.',
            'size_guests' => 'It offers :size m² for up to :guests guests.',
            'guests' => 'It welcomes up to :guests guests.',
            'breakfast' => 'Breakfast is included.',
            'price' => 'Rates start from :price per night. Final availability and rates are confirmed by our team.',
            'facilities' => 'We have :count facilities and services, including :examples. Choose one to read the details, or ask me anything.',
            'facilities_one' => 'We offer :examples. Open it to read the details, or ask me anything.',
            'info_welcome' => 'Welcome to :hotel.',
            'info_place' => 'We are located in :location.',
            'info_hours' => 'Check-in is from :in and check-out is at :out.',
            'info_more' => 'Below you will find the address, our policies and frequently asked questions — or just ask me.',
            'listen' => 'Listen', 'stop' => 'Stop', 'skip' => 'Skip', 'speaks' => 'is speaking',
        ],
        'id' => [
            'rooms' => 'Selamat datang di Kamar & Suite kami. Kami memiliki :count tipe kamar, mulai dari :price per malam. Pilih kamar untuk melihat foto dan detailnya, atau tanyakan apa saja kepada saya.',
            'rooms_one' => 'Selamat datang di Kamar & Suite kami. Kami memiliki satu tipe kamar, mulai dari :price per malam. Buka untuk melihat foto dan detailnya, atau tanyakan apa saja kepada saya.',
            'size_guests' => 'Luasnya :size m² untuk maksimal :guests tamu.',
            'guests' => 'Kamar ini untuk maksimal :guests tamu.',
            'breakfast' => 'Sudah termasuk sarapan.',
            'price' => 'Tarif mulai dari :price per malam. Ketersediaan dan tarif final dikonfirmasi oleh tim kami.',
            'facilities' => 'Kami memiliki :count fasilitas dan layanan, di antaranya :examples. Pilih salah satu untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'facilities_one' => 'Kami memiliki :examples. Buka untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'info_welcome' => 'Selamat datang di :hotel.',
            'info_place' => 'Kami berada di :location.',
            'info_hours' => 'Check-in mulai pukul :in dan check-out pukul :out.',
            'info_more' => 'Di bawah ini ada alamat, kebijakan, dan pertanyaan yang sering diajukan — atau tanyakan langsung kepada saya.',
            'listen' => 'Dengarkan', 'stop' => 'Berhenti', 'skip' => 'Lewati', 'speaks' => 'sedang berbicara',
        ],
        'ja' => [
            'rooms' => '客室・スイートへようこそ。:count タイプのお部屋をご用意しています。1泊 :price からです。お部屋を選ぶと写真と詳細をご覧いただけます。ご質問もお気軽にどうぞ。',
            'rooms_one' => '客室・スイートへようこそ。1タイプのお部屋をご用意しています。1泊 :price からです。写真と詳細をご覧ください。ご質問もお気軽にどうぞ。',
            'size_guests' => '広さは:size m²、最大:guests名様までご利用いただけます。',
            'guests' => '最大:guests名様までご利用いただけます。',
            'breakfast' => '朝食付きです。',
            'price' => '料金は1泊 :price からです。空室状況と料金は、スタッフが最終確認いたします。',
            'facilities' => ':count件の施設・サービスをご用意しています。:examples などです。選ぶと詳細をご覧いただけます。ご質問もどうぞ。',
            'facilities_one' => ':examples をご用意しています。詳細をご覧ください。ご質問もどうぞ。',
            'info_welcome' => 'ようこそ、:hotel へ。',
            'info_place' => '所在地は:locationです。',
            'info_hours' => 'チェックインは:in以降、チェックアウトは:outまでです。',
            'info_more' => '以下に、所在地、ご利用規定、よくあるご質問をご案内しています。お気軽にお尋ねください。',
            'listen' => '音声で聞く', 'stop' => '停止', 'skip' => 'スキップ', 'speaks' => '話しています',
        ],
    ];

    /**
     * Guest-facing names for the coded room values stored in the database.
     *
     * @var array<string, array{bed: array<string, string>, view: array<string, string>, amenity: array<string, string>}>
     */
    private const ROOM_TERMS = [
        'en' => [
            'bed' => ['king' => 'King bed', 'queen' => 'Queen bed', 'twin' => 'Twin bed', 'single' => 'Single bed', 'double' => 'Double bed'],
            'view' => ['garden' => 'Garden view', 'ocean' => 'Ocean view', 'pool' => 'Pool view', 'city' => 'City view', 'mountain' => 'Mountain view'],
            'amenity' => ['air_conditioning' => 'Air conditioning', 'wifi' => 'Wi-Fi', 'minibar' => 'Minibar', 'safe_deposit_box' => 'Safe deposit box', 'balcony' => 'Balcony', 'bathtub' => 'Bathtub', 'coffee_maker' => 'Coffee maker', 'living_room' => 'Living room', 'private_pool' => 'Private pool'],
        ],
        'id' => [
            'bed' => ['king' => 'Tempat tidur king', 'queen' => 'Tempat tidur queen', 'twin' => 'Tempat tidur twin', 'single' => 'Tempat tidur single', 'double' => 'Tempat tidur double'],
            'view' => ['garden' => 'Pemandangan taman', 'ocean' => 'Pemandangan laut', 'pool' => 'Pemandangan kolam', 'city' => 'Pemandangan kota', 'mountain' => 'Pemandangan gunung'],
            'amenity' => ['air_conditioning' => 'AC', 'wifi' => 'Wi-Fi', 'minibar' => 'Minibar', 'safe_deposit_box' => 'Brankas', 'balcony' => 'Balkon', 'bathtub' => 'Bak mandi', 'coffee_maker' => 'Mesin kopi', 'living_room' => 'Ruang tamu', 'private_pool' => 'Kolam renang pribadi'],
        ],
        'ja' => [
            'bed' => ['king' => 'キングベッド', 'queen' => 'クイーンベッド', 'twin' => 'ツインベッド', 'single' => 'シングルベッド', 'double' => 'ダブルベッド'],
            'view' => ['garden' => 'ガーデンビュー', 'ocean' => 'オーシャンビュー', 'pool' => 'プールビュー', 'city' => 'シティビュー', 'mountain' => 'マウンテンビュー'],
            'amenity' => ['air_conditioning' => 'エアコン', 'wifi' => 'Wi-Fi', 'minibar' => 'ミニバー', 'safe_deposit_box' => 'セーフティボックス', 'balcony' => 'バルコニー', 'bathtub' => 'バスタブ', 'coffee_maker' => 'コーヒーメーカー', 'living_room' => 'リビングルーム', 'private_pool' => 'プライベートプール'],
        ],
    ];

    public function index(Request $request): View
    {
        $hotels = Hotel::where('public_status', 'published')->orderBy('name')->get();

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : ($hotels->first()?->default_locale ?? 'id');

        return view('opening', [
            'hotels' => $hotels,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'opening' => $this->openingLabels($locale),
        ]);
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

        $facilities = $hotel->knowledgeItems()->where('is_active', true)
            ->whereIn('category', ['facilities', 'dining', 'transport'])
            ->orderBy('sort_order')->get();

        return view('hotel.show', [
            'hotel' => $hotel,
            'roomTypes' => $roomTypes,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $this->labels($locale),
            'lobby' => $this->lobbyLabels($locale),
            'roomTerms' => self::ROOM_TERMS[$locale],
            'wizard' => $this->wizardLabels($locale),
            'narration' => self::NARRATION[$locale],
            'roomNarrations' => $this->roomNarrations($hotel, $roomTypes, $locale),
            'staffLinks' => $this->handover->forStaff($hotel, $locale),
            'today' => now($hotel->timezone)->toDateString(),
            'infoItems' => $hotel->knowledgeItems()->where('is_active', true)
                ->whereIn('category', ['general', 'policies', 'faq'])
                ->orderBy('sort_order')->get()->groupBy('category'),
            'facilities' => $facilities,
            'sceneNarrations' => $this->sceneNarrations($hotel, $facilities, $locale),
        ]);
    }

    /**
     * Intros for the facilities list and the hotel information scene, built
     * only from stored hotel data.
     *
     * @param  Collection<int, HotelKnowledgeItem>  $facilities
     * @return array{facilities: ?string, info: string}
     */
    private function sceneNarrations(Hotel $hotel, Collection $facilities, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $separator = $locale === 'ja' ? '、' : '; ';
        $time = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $intro = null;

        if ($facilities->isNotEmpty()) {
            $examples = $facilities->take(3)->map(fn ($item) => $item->translatedTitle($locale))->implode($separator);
            $intro = str_replace(
                [':count', ':examples'],
                [(string) $facilities->count(), $examples],
                $templates[$facilities->count() === 1 ? 'facilities_one' : 'facilities']
            );
        }

        $location = collect([$hotel->city, $hotel->country])->filter()->implode($locale === 'ja' ? '、' : ', ');
        $parts = [str_replace(':hotel', $hotel->name, $templates['info_welcome'])];

        if ($location !== '') {
            $parts[] = str_replace(':location', $location, $templates['info_place']);
        }

        if ($time($hotel->check_in_time) && $time($hotel->check_out_time)) {
            $parts[] = str_replace([':in', ':out'], [$time($hotel->check_in_time), $time($hotel->check_out_time)], $templates['info_hours']);
        }

        $parts[] = $templates['info_more'];

        return ['facilities' => $intro, 'info' => implode(' ', $parts)];
    }

    /**
     * @param  Collection<int, RoomType>  $roomTypes
     * @return array{rooms: ?string, room: array<string, string>}
     */
    private function roomNarrations(Hotel $hotel, Collection $roomTypes, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $terms = self::ROOM_TERMS[$locale];
        $money = fn ($value) => $hotel->currency.' '.number_format((float) $value, 0, ',', '.');
        $sentence = fn (string $text) => preg_match('/[.!?。！？]$/u', $text) ? $text : $text.($locale === 'ja' ? '。' : '.');

        if ($roomTypes->isEmpty()) {
            return ['rooms' => null, 'room' => []];
        }

        $intro = str_replace(
            [':count', ':price'],
            [(string) $roomTypes->count(), $money($roomTypes->min('base_price'))],
            $templates[$roomTypes->count() === 1 ? 'rooms_one' : 'rooms']
        );

        $rooms = $roomTypes->mapWithKeys(function ($roomType) use ($templates, $terms, $money, $sentence, $locale) {
            $parts = [$roomType->translatedName($locale).'.', filled($roomType->translatedDescription($locale)) ? $sentence(trim($roomType->translatedDescription($locale))) : null];

            $parts[] = $roomType->size_sqm
                ? str_replace([':size', ':guests'], [(string) $roomType->size_sqm, (string) $roomType->maxOccupancy()], $templates['size_guests'])
                : str_replace(':guests', (string) $roomType->maxOccupancy(), $templates['guests']);

            if ($roomType->view_type) {
                $parts[] = $sentence($terms['view'][$roomType->view_type] ?? Str::headline($roomType->view_type));
            }

            if ($roomType->breakfast_included) {
                $parts[] = $templates['breakfast'];
            }

            $parts[] = str_replace(':price', $money($roomType->base_price), $templates['price']);

            return [$roomType->slug => implode(' ', array_filter($parts))];
        })->all();

        return ['rooms' => $intro, 'room' => $rooms];
    }

    /**
     * @return array<string, string>
     */
    private function openingLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Welcome to', 'tagline' => 'Your AI concierge, ready to help you find the perfect stay.',
                'enter' => 'Enter :name', 'empty' => 'The virtual lobby is being prepared. Please come back soon.',
                'loading' => 'Preparing your hotel experience…', 'sound_on' => 'Sound on', 'sound_off' => 'Sound off', 'rooms' => 'Explore rooms', 'facilities' => 'See facilities', 'reservation' => 'Plan a reservation', 'staff' => 'Talk to staff',
            ],
            'ja' => [
                'welcome' => 'ようこそ', 'tagline' => 'AIコンシェルジュが、理想のご滞在をお手伝いします。',
                'enter' => ':name に入る', 'empty' => 'バーチャルロビーは準備中です。しばらくしてからお越しください。',
                'loading' => 'ホテル体験を準備しています…', 'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ', 'rooms' => '客室を見る', 'facilities' => '施設を見る', 'reservation' => '予約を計画する', 'staff' => 'スタッフに相談',
            ],
            default => [
                'welcome' => 'Selamat datang di', 'tagline' => 'AI Concierge siap membantu Anda menemukan pengalaman menginap terbaik.',
                'enter' => 'Masuk ke :name', 'empty' => 'Lobi virtual sedang dipersiapkan. Silakan kembali lagi nanti.',
                'loading' => 'Menyiapkan pengalaman hotel Anda…', 'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati', 'rooms' => 'Jelajahi kamar', 'facilities' => 'Lihat fasilitas', 'reservation' => 'Rencanakan reservasi', 'staff' => 'Bicara dengan staf',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardLabels(string $locale): array
    {
        return [...ReservationController::MESSAGES[$locale], ...match ($locale) {
            'en' => [
                'title' => 'Reservation request', 'intro' => 'A few quick steps. Final availability is confirmed by our team.',
                'step_of' => 'Step :current of :total', 'steps' => ['Dates', 'Guests', 'Room', 'Your details', 'Summary'],
                'check_in' => 'Check-in', 'check_out' => 'Check-out', 'nights' => 'night(s)', 'adults' => 'Adults', 'children' => 'Children', 'rooms' => 'Rooms',
                'choose_room' => 'Choose a room', 'fits' => 'Up to :count guests per room', 'too_small' => 'Too small for your party',
                'extra_bed' => 'Add an extra bed', 'name' => 'Full name', 'contact_method' => 'How should we contact you?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'email' => 'Email', 'contact_value' => 'Number or email address',
                'special' => 'Special request (optional)', 'special_placeholder' => 'e.g. high floor, late arrival',
                'next' => 'Next', 'back' => 'Back', 'edit' => 'Edit', 'submit' => 'Submit reservation request', 'sending' => 'Sending…',
                'checking' => 'Checking availability…', 'estimated_total' => 'Estimated total', 'guests' => 'Guests', 'room' => 'Room', 'dates' => 'Dates',
                'contact' => 'Contact', 'disclaimer' => 'This is a reservation request. Final availability and rates are confirmed by hotel staff. No payment is taken now.',
                'available_instead' => 'Available for your dates instead:', 'done_title' => 'Request received', 'reference' => 'Your reference',
                'awaiting' => 'Awaiting hotel confirmation', 'done_hint' => 'Send your request to our team to speed up confirmation.',
                'send_whatsapp' => 'Send to WhatsApp', 'call_hotel' => 'Call the hotel', 'email_hotel' => 'Email the hotel', 'new_request' => 'New request',
                'error_network' => 'Connection problem. Your details are saved — please try again or contact hotel staff.',
                'error_generic' => 'Something went wrong. Please try again or contact hotel staff.', 'select_room' => 'Please choose a room.',
                'contact_staff' => 'Contact hotel staff',
            ],
            'ja' => [
                'title' => 'ご予約リクエスト', 'intro' => 'かんたんな手順です。空室状況はスタッフが最終確認いたします。',
                'step_of' => 'ステップ :current / :total', 'steps' => ['日程', '人数', '客室', 'ご連絡先', '確認'],
                'check_in' => 'チェックイン', 'check_out' => 'チェックアウト', 'nights' => '泊', 'adults' => '大人', 'children' => '子ども', 'rooms' => '客室数',
                'choose_room' => '客室を選ぶ', 'fits' => '1室あたり最大:count名', 'too_small' => '人数に対応できません',
                'extra_bed' => 'エキストラベッドを追加', 'name' => 'お名前', 'contact_method' => 'ご連絡方法',
                'whatsapp' => 'WhatsApp', 'phone' => '電話', 'email' => 'メール', 'contact_value' => '番号またはメールアドレス',
                'special' => 'ご要望（任意）', 'special_placeholder' => '例：高層階、遅い到着',
                'next' => '次へ', 'back' => '戻る', 'edit' => '編集', 'submit' => '予約リクエストを送信', 'sending' => '送信中…',
                'checking' => '空室を確認中…', 'estimated_total' => '概算合計', 'guests' => '人数', 'room' => '客室', 'dates' => '日程',
                'contact' => 'ご連絡先', 'disclaimer' => 'これは予約リクエストです。空室状況と料金はスタッフが最終確認します。現時点でお支払いは発生しません。',
                'available_instead' => 'ご希望の日程で空いている客室：', 'done_title' => 'リクエストを受け付けました', 'reference' => '予約番号',
                'awaiting' => 'ホテルの確認待ち', 'done_hint' => 'リクエストをスタッフに送ると、確認がスムーズです。',
                'send_whatsapp' => 'WhatsAppで送る', 'call_hotel' => 'ホテルに電話', 'email_hotel' => 'ホテルにメール', 'new_request' => '新しいリクエスト',
                'error_network' => '接続に問題があります。入力内容は保存されています。もう一度お試しいただくか、スタッフにご連絡ください。',
                'error_generic' => 'エラーが発生しました。もう一度お試しいただくか、スタッフにご連絡ください。', 'select_room' => '客室を選択してください。',
                'contact_staff' => 'スタッフに連絡',
            ],
            default => [
                'title' => 'Permintaan reservasi', 'intro' => 'Hanya beberapa langkah singkat. Ketersediaan final dikonfirmasi oleh tim kami.',
                'step_of' => 'Langkah :current dari :total', 'steps' => ['Tanggal', 'Tamu', 'Kamar', 'Data Anda', 'Ringkasan'],
                'check_in' => 'Check-in', 'check_out' => 'Check-out', 'nights' => 'malam', 'adults' => 'Dewasa', 'children' => 'Anak', 'rooms' => 'Jumlah kamar',
                'choose_room' => 'Pilih kamar', 'fits' => 'Maks. :count tamu per kamar', 'too_small' => 'Tidak cukup untuk rombongan Anda',
                'extra_bed' => 'Tambah extra bed', 'name' => 'Nama lengkap', 'contact_method' => 'Bagaimana kami menghubungi Anda?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Telepon', 'email' => 'Email', 'contact_value' => 'Nomor atau alamat email',
                'special' => 'Permintaan khusus (opsional)', 'special_placeholder' => 'mis. lantai tinggi, tiba larut malam',
                'next' => 'Lanjut', 'back' => 'Kembali', 'edit' => 'Ubah', 'submit' => 'Kirim permintaan reservasi', 'sending' => 'Mengirim…',
                'checking' => 'Memeriksa ketersediaan…', 'estimated_total' => 'Perkiraan total', 'guests' => 'Tamu', 'room' => 'Kamar', 'dates' => 'Tanggal',
                'contact' => 'Kontak', 'disclaimer' => 'Ini adalah permintaan reservasi. Ketersediaan dan tarif final dikonfirmasi oleh staf hotel. Belum ada pembayaran yang diambil.',
                'available_instead' => 'Tersedia di tanggal Anda:', 'done_title' => 'Permintaan diterima', 'reference' => 'Nomor referensi',
                'awaiting' => 'Menunggu konfirmasi hotel', 'done_hint' => 'Kirim permintaan ke tim kami agar konfirmasi lebih cepat.',
                'send_whatsapp' => 'Kirim ke WhatsApp', 'call_hotel' => 'Telepon hotel', 'email_hotel' => 'Email hotel', 'new_request' => 'Permintaan baru',
                'error_network' => 'Koneksi bermasalah. Data Anda tersimpan — coba lagi atau hubungi staf hotel.',
                'error_generic' => 'Terjadi kesalahan. Coba lagi atau hubungi staf hotel.', 'select_room' => 'Silakan pilih kamar.',
                'contact_staff' => 'Hubungi staf hotel',
            ],
        }];
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
                'menu_info' => 'Hotel information', 'info_about' => 'About the hotel', 'info_policies' => 'Policies', 'info_faq' => 'Frequently asked questions',
                'info_address' => 'Address', 'info_hours' => 'Check-in / check-out', 'info_contact' => 'Contact', 'info_map' => 'Open in Maps', 'info_ask' => 'Ask about the hotel',
                'facility_counter' => 'Facility', 'prev_facility' => 'Previous', 'next_facility' => 'Next', 'ask_facility' => 'Ask about this facility', 'back_facilities' => 'All facilities', 'open_facility' => 'Read more', 'ask_facility_q' => 'Tell me more about :name.',
                'loading' => 'Preparing your hotel experience…',
                'sound_on' => 'Sound on', 'sound_off' => 'Sound off',
                'room_scene' => 'Room details', 'room_counter' => 'Room', 'gallery' => 'Photo gallery', 'photo' => 'Photo',
                'no_photo' => 'Photos coming soon', 'prev_room' => 'Previous room', 'next_room' => 'Next room',
                'ask_room' => 'Ask about this room', 'reserve_room' => 'Request reservation', 'breakfast_excluded' => 'Room only', 'breakfast' => 'Breakfast',
                'size' => 'Size', 'bed' => 'Bed', 'guests' => 'Guests', 'view' => 'View', 'extra_bed' => 'Extra bed',
                'extra_bed_yes' => 'Available', 'extra_bed_no' => 'Not available', 'amenities' => 'In the room',
                'availability_note' => 'Final availability and rates are confirmed by hotel staff.',
                'ask_room_q' => 'Tell me more about the :name.',
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
                'menu_info' => 'ホテル情報', 'info_about' => 'ホテルについて', 'info_policies' => 'ご利用規定', 'info_faq' => 'よくあるご質問',
                'info_address' => '所在地', 'info_hours' => 'チェックイン / チェックアウト', 'info_contact' => 'お問い合わせ', 'info_map' => '地図で開く', 'info_ask' => 'ホテルについて聞く',
                'facility_counter' => '施設', 'prev_facility' => '前へ', 'next_facility' => '次へ', 'ask_facility' => 'この施設について聞く', 'back_facilities' => '施設一覧', 'open_facility' => '詳しく見る', 'ask_facility_q' => ':name について詳しく教えてください。',
                'loading' => 'ホテル体験を準備しています…',
                'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ',
                'room_scene' => '客室のご案内', 'room_counter' => '客室', 'gallery' => 'フォトギャラリー', 'photo' => '写真',
                'no_photo' => '写真は準備中です', 'prev_room' => '前の客室', 'next_room' => '次の客室',
                'ask_room' => 'この客室について聞く', 'reserve_room' => '予約をリクエスト', 'breakfast_excluded' => '朝食なし', 'breakfast' => '朝食',
                'size' => '広さ', 'bed' => 'ベッド', 'guests' => '定員', 'view' => '眺望', 'extra_bed' => 'エキストラベッド',
                'extra_bed_yes' => '利用可', 'extra_bed_no' => '利用不可', 'amenities' => '客室設備',
                'availability_note' => '空室状況と料金は、ホテルスタッフが最終確認いたします。',
                'ask_room_q' => ':name について詳しく教えてください。',
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
                'menu_info' => 'Informasi hotel', 'info_about' => 'Tentang hotel', 'info_policies' => 'Kebijakan', 'info_faq' => 'Pertanyaan yang sering diajukan',
                'info_address' => 'Alamat', 'info_hours' => 'Check-in / check-out', 'info_contact' => 'Kontak', 'info_map' => 'Buka di Maps', 'info_ask' => 'Tanya tentang hotel',
                'facility_counter' => 'Fasilitas', 'prev_facility' => 'Sebelumnya', 'next_facility' => 'Berikutnya', 'ask_facility' => 'Tanya tentang fasilitas ini', 'back_facilities' => 'Semua fasilitas', 'open_facility' => 'Selengkapnya', 'ask_facility_q' => 'Ceritakan lebih banyak tentang :name.',
                'loading' => 'Menyiapkan pengalaman hotel Anda…',
                'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati',
                'room_scene' => 'Detail kamar', 'room_counter' => 'Kamar', 'gallery' => 'Galeri foto', 'photo' => 'Foto',
                'no_photo' => 'Foto segera tersedia', 'prev_room' => 'Kamar sebelumnya', 'next_room' => 'Kamar berikutnya',
                'ask_room' => 'Tanya tentang kamar ini', 'reserve_room' => 'Ajukan reservasi', 'breakfast_excluded' => 'Tanpa sarapan', 'breakfast' => 'Sarapan',
                'size' => 'Ukuran', 'bed' => 'Tempat tidur', 'guests' => 'Kapasitas', 'view' => 'Pemandangan', 'extra_bed' => 'Extra bed',
                'extra_bed_yes' => 'Tersedia', 'extra_bed_no' => 'Tidak tersedia', 'amenities' => 'Fasilitas kamar',
                'availability_note' => 'Ketersediaan dan tarif final dikonfirmasi oleh staf hotel.',
                'ask_room_q' => 'Ceritakan lebih banyak tentang :name.',
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
                'chat_error' => "I'm having trouble responding right now. Please try again or contact hotel staff.", 'chat_slow' => 'You are sending messages very quickly. Please wait a moment and try again.', 'chat_retry' => 'Retry',
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
                'chat_error' => '現在うまくお答えできません。もう一度お試しいただくか、スタッフにご連絡ください。', 'chat_slow' => 'メッセージが多すぎます。少し待ってからもう一度お試しください。', 'chat_retry' => '再試行',
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
                'chat_error' => 'Saya sedang kesulitan menjawab. Silakan coba lagi atau hubungi staf hotel.', 'chat_slow' => 'Pesan terlalu cepat. Mohon tunggu sebentar lalu coba lagi.', 'chat_retry' => 'Coba lagi',
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
