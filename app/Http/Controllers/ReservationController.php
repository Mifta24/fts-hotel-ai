<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Services\Reservation\ReservationHandover;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    private const PHONE_PATTERN = '/^\+?[0-9\s\-().]{6,20}$/';

    /**
     * Guest-facing validation messages, keyed by locale.
     *
     * @var array<string, array<string, string>>
     */
    public const MESSAGES = [
        'en' => [
            'invalid' => 'Please check this field.',
            'check_in_past' => 'Check-in cannot be in the past.',
            'check_out_after' => 'Check-out must be after check-in.',
            'too_long' => 'Stays are limited to :max nights. Please contact hotel staff for longer stays.',
            'room_unknown' => 'Please choose a room.',
            'capacity' => 'This room does not fit that many guests. Choose another room or add rooms.',
            'unavailable' => 'This room is not available for those dates.',
            'contact_email' => 'Please enter a valid email address.',
            'contact_phone' => 'Please enter a valid phone or WhatsApp number.',
        ],
        'id' => [
            'invalid' => 'Mohon periksa kolom ini.',
            'check_in_past' => 'Tanggal check-in tidak boleh di masa lalu.',
            'check_out_after' => 'Tanggal check-out harus setelah check-in.',
            'too_long' => 'Lama menginap dibatasi :max malam. Hubungi staf hotel untuk menginap lebih lama.',
            'room_unknown' => 'Silakan pilih kamar.',
            'capacity' => 'Kamar ini tidak cukup untuk jumlah tamu tersebut. Pilih kamar lain atau tambah kamar.',
            'unavailable' => 'Kamar ini tidak tersedia pada tanggal tersebut.',
            'contact_email' => 'Masukkan alamat email yang valid.',
            'contact_phone' => 'Masukkan nomor telepon atau WhatsApp yang valid.',
        ],
        'ja' => [
            'invalid' => 'この項目をご確認ください。',
            'check_in_past' => 'チェックイン日は過去にできません。',
            'check_out_after' => 'チェックアウト日はチェックインより後にしてください。',
            'too_long' => 'ご宿泊は最大:max泊までです。それ以上はスタッフにご相談ください。',
            'room_unknown' => '客室を選択してください。',
            'capacity' => 'この客室では人数に対応できません。別の客室を選ぶか、客室数を増やしてください。',
            'unavailable' => 'この日程では、この客室はご利用いただけません。',
            'contact_email' => '有効なメールアドレスを入力してください。',
            'contact_phone' => '有効な電話番号またはWhatsApp番号を入力してください。',
        ],
    ];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationHandover $handover,
    ) {}

    public function quote(Request $request, string $hotelSlug): JsonResponse
    {
        $hotel = $this->publishedHotel($hotelSlug);
        $locale = $this->locale($request, $hotel);

        $data = $request->validate($this->stayRules($hotel), $this->validationMessages($locale));
        [$roomType, $checkIn, $checkOut, $rooms, $extraBed] = $this->resolveStay($hotel, $data, $locale);

        $quote = $this->reservations->quote($roomType, $checkIn, $checkOut, $rooms, $extraBed);

        if (! $quote) {
            return $this->unavailable($hotel, $roomType, $data, $locale);
        }

        return response()->json([
            'available' => true,
            'nights' => $quote['nights'],
            'room_total' => $quote['room_total'],
            'extra_bed_total' => $quote['extra_bed_total'],
            'grand_total' => $quote['grand_total'],
            'currency' => $hotel->currency,
        ]);
    }

    public function store(Request $request, string $hotelSlug): JsonResponse
    {
        $hotel = $this->publishedHotel($hotelSlug);
        $locale = $this->locale($request, $hotel);
        $messages = self::MESSAGES[$locale];

        $data = $request->validate([
            ...$this->stayRules($hotel),
            'guest_name' => ['required', 'string', 'max:100'],
            'contact_type' => ['required', Rule::in(['whatsapp', 'phone', 'email'])],
            'contact_value' => ['required', 'string', 'max:120'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'guest_token' => ['nullable', 'uuid'],
        ], $this->validationMessages($locale));

        $isEmail = $data['contact_type'] === 'email';

        if ($isEmail && ! filter_var($data['contact_value'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_email']]);
        }

        if (! $isEmail && ! preg_match(self::PHONE_PATTERN, $data['contact_value'])) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_phone']]);
        }

        [$roomType, $checkIn, $checkOut, $rooms, $extraBed] = $this->resolveStay($hotel, $data, $locale);

        $conversation = isset($data['guest_token'])
            ? Conversation::where('hotel_id', $hotel->id)->where('guest_token', $data['guest_token'])->first()
            : null;

        $booking = $this->reservations->createRequest($hotel, $roomType, [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => (int) $data['adults'],
            'children' => (int) ($data['children'] ?? 0),
            'rooms' => $rooms,
            'extra_bed' => $extraBed,
            'guest_name' => $data['guest_name'],
            'guest_email' => $isEmail ? $data['contact_value'] : null,
            'guest_phone' => $isEmail ? null : $data['contact_value'],
            'contact_type' => $data['contact_type'],
            'notes' => $data['special_request'] ?? null,
        ], $conversation);

        if (! $booking) {
            return $this->unavailable($hotel, $roomType, $data, $locale);
        }

        return response()->json([
            'reference' => $booking->reference,
            'status' => $booking->status,
            'total' => (float) $booking->total_price,
            'currency' => $hotel->currency,
            'handover' => $this->handover->forBooking($hotel, $booking, $locale),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function stayRules(Hotel $hotel): array
    {
        $today = now($hotel->timezone)->toDateString();

        return [
            'room_type_slug' => ['required', 'string', 'max:120'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
            'extra_bed' => ['nullable', 'boolean'],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        return [
            'required' => $messages['invalid'],
            'date_format' => $messages['invalid'],
            'integer' => $messages['invalid'],
            'min' => $messages['invalid'],
            'max' => $messages['invalid'],
            'boolean' => $messages['invalid'],
            'in' => $messages['invalid'],
            'uuid' => $messages['invalid'],
            'after_or_equal' => $messages['check_in_past'],
            'after' => $messages['check_out_after'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: RoomType, 1: CarbonImmutable, 2: CarbonImmutable, 3: int, 4: bool}
     */
    private function resolveStay(Hotel $hotel, array $data, string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        $roomType = $hotel->roomTypes()->where('is_active', true)->where('slug', $data['room_type_slug'])->first();

        if (! $roomType) {
            throw ValidationException::withMessages(['room_type_slug' => $messages['room_unknown']]);
        }

        $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();

        if ($checkIn->diffInDays($checkOut) > ReservationService::MAX_NIGHTS) {
            throw ValidationException::withMessages([
                'check_out' => str_replace(':max', (string) ReservationService::MAX_NIGHTS, $messages['too_long']),
            ]);
        }

        $rooms = (int) $data['rooms'];

        if (! $this->reservations->fitsOccupancy($roomType, (int) $data['adults'], (int) ($data['children'] ?? 0), $rooms)) {
            throw ValidationException::withMessages(['adults' => $messages['capacity']]);
        }

        return [$roomType, $checkIn, $checkOut, $rooms, (bool) ($data['extra_bed'] ?? false)];
    }

    /**
     * The room cannot be booked for those nights: say so, and offer the room
     * types that can host the same party on the same dates.
     *
     * @param  array<string, mixed>  $data
     */
    private function unavailable(Hotel $hotel, RoomType $requested, array $data, string $locale): JsonResponse
    {
        $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();
        $rooms = (int) $data['rooms'];
        $adults = (int) $data['adults'];
        $children = (int) ($data['children'] ?? 0);

        $alternatives = $hotel->roomTypes()
            ->where('is_active', true)
            ->whereKeyNot($requested->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (RoomType $roomType) => $this->reservations->fitsOccupancy($roomType, $adults, $children, $rooms))
            ->map(function (RoomType $roomType) use ($checkIn, $checkOut, $rooms, $locale) {
                $quote = $this->reservations->quote($roomType, $checkIn, $checkOut, $rooms);

                return $quote ? [
                    'slug' => $roomType->slug,
                    'name' => $roomType->translatedName($locale),
                    'total' => $quote['grand_total'],
                ] : null;
            })
            ->filter()
            ->values();

        $message = self::MESSAGES[$locale]['unavailable'];

        return response()->json([
            'message' => $message,
            'errors' => ['room_type_slug' => [$message]],
            'alternatives' => $alternatives,
            'currency' => $hotel->currency,
        ], 422);
    }

    private function locale(Request $request, Hotel $hotel): string
    {
        return in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $hotel->default_locale;
    }

    private function publishedHotel(string $hotelSlug): Hotel
    {
        $hotel = Hotel::where('slug', $hotelSlug)->first();

        abort_if(! $hotel || ! $hotel->isPublished(), 404);

        return $hotel;
    }
}
