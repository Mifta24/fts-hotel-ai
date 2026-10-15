<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Services\Reservation\BookingChangeService;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lets a guest look up their own reservation and move or cancel it.
 */
class GuestBookingController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /**
     * @var array<string, array<string, string>>
     */
    public const LABELS = [
        'en' => [
            'title' => 'Manage my reservation', 'intro' => 'Enter the reference from your confirmation and the phone number or email you booked with.',
            'reference' => 'Booking reference', 'contact' => 'Phone or email used for the booking', 'find' => 'Find my reservation',
            'not_found' => 'We could not find a reservation with those details. Please check the reference and contact.',
            'room' => 'Room', 'stay' => 'Stay', 'nights' => 'nights', 'total' => 'Total', 'status' => 'Status',
            'pending' => 'Waiting for the hotel to confirm', 'confirmed' => 'Confirmed', 'completed' => 'Stay completed', 'cancelled' => 'Cancelled',
            'new_dates' => 'Move to other dates', 'check_in' => 'Check-in', 'check_out' => 'Check-out', 'reschedule' => 'Move my stay',
            'cancel' => 'Cancel reservation', 'confirm_cancel' => 'Cancel this reservation? The rooms are released straight away.',
            'rescheduled' => 'Your stay was moved. The hotel will confirm the new dates.', 'cancelled_ok' => 'Your reservation was cancelled.',
            'unavailable' => 'Those dates are not available for this room. Please try other dates.',
            'not_changeable' => 'This reservation can no longer be changed online. Please contact the hotel.',
            'invalid' => 'Please check this field.', 'check_in_past' => 'Check-in cannot be in the past.', 'check_out_after' => 'Check-out must be after check-in.',
            'too_long' => 'Stays are limited to :max nights.', 'back' => 'Back to the hotel', 'error' => 'Something went wrong. Please try again.',
        ],
        'id' => [
            'title' => 'Kelola reservasi saya', 'intro' => 'Masukkan kode referensi dari konfirmasi dan nomor telepon atau email yang Anda gunakan saat memesan.',
            'reference' => 'Kode referensi', 'contact' => 'Telepon atau email saat memesan', 'find' => 'Cari reservasi saya',
            'not_found' => 'Reservasi dengan data tersebut tidak ditemukan. Mohon periksa kode referensi dan kontak.',
            'room' => 'Kamar', 'stay' => 'Menginap', 'nights' => 'malam', 'total' => 'Total', 'status' => 'Status',
            'pending' => 'Menunggu konfirmasi hotel', 'confirmed' => 'Terkonfirmasi', 'completed' => 'Menginap selesai', 'cancelled' => 'Dibatalkan',
            'new_dates' => 'Pindah ke tanggal lain', 'check_in' => 'Check-in', 'check_out' => 'Check-out', 'reschedule' => 'Pindahkan jadwal',
            'cancel' => 'Batalkan reservasi', 'confirm_cancel' => 'Batalkan reservasi ini? Kamar langsung dilepas.',
            'rescheduled' => 'Jadwal menginap dipindahkan. Hotel akan mengonfirmasi tanggal baru.', 'cancelled_ok' => 'Reservasi Anda dibatalkan.',
            'unavailable' => 'Tanggal tersebut tidak tersedia untuk kamar ini. Silakan coba tanggal lain.',
            'not_changeable' => 'Reservasi ini tidak bisa diubah lagi secara online. Silakan hubungi hotel.',
            'invalid' => 'Mohon periksa kolom ini.', 'check_in_past' => 'Tanggal check-in tidak boleh di masa lalu.', 'check_out_after' => 'Tanggal check-out harus setelah check-in.',
            'too_long' => 'Lama menginap dibatasi :max malam.', 'back' => 'Kembali ke hotel', 'error' => 'Terjadi kesalahan. Silakan coba lagi.',
        ],
        'ja' => [
            'title' => 'ご予約の確認・変更', 'intro' => '確認メッセージの予約番号と、ご予約時の電話番号またはメールアドレスを入力してください。',
            'reference' => '予約番号', 'contact' => 'ご予約時の電話番号またはメール', 'find' => '予約を検索',
            'not_found' => '該当するご予約が見つかりません。予約番号と連絡先をご確認ください。',
            'room' => '客室', 'stay' => 'ご宿泊', 'nights' => '泊', 'total' => '合計', 'status' => '状況',
            'pending' => 'ホテルの確認待ち', 'confirmed' => '確認済み', 'completed' => 'ご宿泊済み', 'cancelled' => 'キャンセル済み',
            'new_dates' => '日程を変更する', 'check_in' => 'チェックイン', 'check_out' => 'チェックアウト', 'reschedule' => '日程を変更',
            'cancel' => '予約をキャンセル', 'confirm_cancel' => 'このご予約をキャンセルしますか？客室はすぐに解放されます。',
            'rescheduled' => '日程を変更しました。ホテルが新しい日程を確認します。', 'cancelled_ok' => 'ご予約をキャンセルしました。',
            'unavailable' => 'この日程ではこの客室をご利用いただけません。別の日程をお試しください。',
            'not_changeable' => 'このご予約はオンラインでは変更できません。ホテルまでご連絡ください。',
            'invalid' => 'この項目をご確認ください。', 'check_in_past' => 'チェックイン日は過去にできません。', 'check_out_after' => 'チェックアウト日はチェックインより後にしてください。',
            'too_long' => 'ご宿泊は最大:max泊までです。', 'back' => 'ホテルに戻る', 'error' => 'エラーが発生しました。もう一度お試しください。',
        ],
    ];

    public function __construct(private readonly BookingChangeService $changes) {}

    public function page(Request $request, string $hotelSlug): View
    {
        $hotel = $this->publishedHotel($hotelSlug);
        $locale = $this->locale($request, $hotel);

        return view('hotel.booking-manage', ['hotel' => $hotel, 'locale' => $locale, 'labels' => self::LABELS[$locale]]);
    }

    public function lookup(Request $request, string $hotelSlug): JsonResponse
    {
        $hotel = $this->publishedHotel($hotelSlug);
        $locale = $this->locale($request, $hotel);
        $booking = $this->findBooking($request, $hotel, $locale);

        return $booking ? response()->json($this->summary($booking, $hotel)) : $this->notFound($locale);
    }

    public function reschedule(Request $request, string $hotelSlug): JsonResponse
    {
        $hotel = $this->publishedHotel($hotelSlug);
        $locale = $this->locale($request, $hotel);
        $labels = self::LABELS[$locale];

        $data = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now($hotel->timezone)->toDateString()],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
        ], ['required' => $labels['invalid'], 'date_format' => $labels['invalid'], 'after_or_equal' => $labels['check_in_past'], 'after' => $labels['check_out_after']]);

        $booking = $this->findBooking($request, $hotel, $locale);

        if (! $booking) {
            return $this->notFound($locale);
        }

        $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();

        if ($checkIn->diffInDays($checkOut) > ReservationService::MAX_NIGHTS) {
            return response()->json(['message' => str_replace(':max', (string) ReservationService::MAX_NIGHTS, $labels['too_long']), 'errors' => ['check_out' => [str_replace(':max', (string) ReservationService::MAX_NIGHTS, $labels['too_long'])]]], 422);
        }

        if (! $this->changes->isChangeable($booking, $hotel)) {
            return response()->json(['message' => $labels['not_changeable']], 409);
        }

        $moved = $this->changes->reschedule($booking, $hotel, $checkIn, $checkOut);

        if (! $moved) {
            return response()->json(['message' => $labels['unavailable'], 'errors' => ['check_in' => [$labels['unavailable']]]], 422);
        }

        return response()->json([...$this->summary($moved->load('roomType'), $hotel), 'message' => $labels['rescheduled']]);
    }

    public function cancel(Request $request, string $hotelSlug): JsonResponse
    {
        $hotel = $this->publishedHotel($hotelSlug);
        $locale = $this->locale($request, $hotel);
        $booking = $this->findBooking($request, $hotel, $locale);

        if (! $booking) {
            return $this->notFound($locale);
        }

        if (! $this->changes->cancel($booking, $hotel)) {
            return response()->json(['message' => self::LABELS[$locale]['not_changeable']], 409);
        }

        return response()->json([...$this->summary($booking->fresh('roomType'), $hotel), 'message' => self::LABELS[$locale]['cancelled_ok']]);
    }

    private function findBooking(Request $request, Hotel $hotel, string $locale): ?Booking
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'contact' => ['required', 'string', 'max:120'],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ], ['required' => self::LABELS[$locale]['invalid']]);

        return $this->changes->findForGuest($hotel, $data['reference'], $data['contact']);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Booking $booking, Hotel $hotel): array
    {
        return [
            'reference' => $booking->reference,
            'room' => $booking->roomType->name,
            'rooms' => $booking->room_count,
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'nights' => $booking->nights(),
            'adults' => $booking->adults,
            'children' => $booking->children,
            'total' => (float) $booking->total_price,
            'currency' => $hotel->currency,
            'status' => $booking->status,
            'changeable' => $this->changes->isChangeable($booking, $hotel),
        ];
    }

    private function notFound(string $locale): JsonResponse
    {
        return response()->json(['message' => self::LABELS[$locale]['not_found']], 404);
    }

    private function locale(Request $request, Hotel $hotel): string
    {
        $locale = $request->input('locale', $request->query('lang'));

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : $hotel->default_locale;
    }

    private function publishedHotel(string $hotelSlug): Hotel
    {
        $hotel = Hotel::where('slug', $hotelSlug)->first();

        abort_if(! $hotel || ! $hotel->isPublished(), 404);

        return $hotel;
    }
}
