<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The single place where stay prices, availability and reservation requests
 * are computed. Both the AI concierge tools and the guest-facing reservation
 * wizard go through here, so they can never disagree.
 */
class ReservationService
{
    public const MAX_NIGHTS = 30;

    /**
     * Whether the requested party fits the room type across the requested rooms.
     */
    public function fitsOccupancy(RoomType $roomType, int $adults, int $children, int $rooms): bool
    {
        return $adults <= $roomType->max_adults * $rooms
            && ($adults + $children) <= $roomType->maxOccupancy() * $rooms;
    }

    /**
     * Prices a stay across every night in range. Returns null if any night
     * lacks enough free units — this is the one place price and availability
     * truth comes from, never the model.
     *
     * @return array{nights: int, nightly: list<array{date: string, price: float}>, room_total: float, extra_bed_total: float, grand_total: float, min_available_units: int}|null
     */
    public function quote(
        RoomType $roomType,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $rooms = 1,
        bool $extraBed = false,
        bool $lockForUpdate = false,
    ): ?array {
        $nights = $checkIn->diffInDays($checkOut);

        if ($nights < 1) {
            return null;
        }

        $query = RoomInventory::where('room_type_id', $roomType->id)
            ->whereDate('stay_date', '>=', $checkIn->toDateString())
            ->whereDate('stay_date', '<=', $checkOut->subDay()->toDateString())
            ->orderBy('stay_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $inventory = $query->get();

        if ($inventory->count() < $nights) {
            return null;
        }

        $nightly = [];
        $perRoomTotal = 0.0;
        $minAvailable = PHP_INT_MAX;

        foreach ($inventory as $night) {
            if ($night->availableUnits() < $rooms) {
                return null;
            }

            $nightly[] = ['date' => $night->stay_date->toDateString(), 'price' => (float) $night->price];
            $perRoomTotal += (float) $night->price;
            $minAvailable = min($minAvailable, $night->availableUnits());
        }

        $roomTotal = $perRoomTotal * $rooms;
        $extraBedTotal = $extraBed && $roomType->extra_bed_available
            ? (float) $roomType->extra_bed_price * $nights * $rooms
            : 0.0;

        return [
            'nights' => $nights,
            'nightly' => $nightly,
            'room_total' => $roomTotal,
            'extra_bed_total' => $extraBedTotal,
            'grand_total' => $roomTotal + $extraBedTotal,
            'min_available_units' => $minAvailable,
        ];
    }

    /**
     * Creates a pending reservation request and holds the inventory for it.
     * Returns null when the room is no longer available for those nights.
     *
     * @param  array{check_in: CarbonImmutable, check_out: CarbonImmutable, adults: int, children?: int, rooms?: int, extra_bed?: bool, guest_name: string, guest_email?: ?string, guest_phone?: ?string, contact_type?: ?string, notes?: ?string}  $data
     */
    public function createRequest(Hotel $hotel, RoomType $roomType, array $data, ?Conversation $conversation = null): ?Booking
    {
        $rooms = $data['rooms'] ?? 1;
        $extraBed = (bool) ($data['extra_bed'] ?? false);

        return DB::transaction(function () use ($hotel, $roomType, $data, $conversation, $rooms, $extraBed) {
            $quote = $this->quote($roomType, $data['check_in'], $data['check_out'], $rooms, $extraBed, lockForUpdate: true);

            if (! $quote) {
                return null;
            }

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'hotel_id' => $hotel->id,
                'room_type_id' => $roomType->id,
                'conversation_id' => $conversation?->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'contact_type' => $data['contact_type'] ?? null,
                'check_in' => $data['check_in']->toDateString(),
                'check_out' => $data['check_out']->toDateString(),
                'adults' => $data['adults'],
                'children' => $data['children'] ?? 0,
                'room_count' => $rooms,
                'extra_bed' => $extraBed && $roomType->extra_bed_available,
                'total_price' => $quote['grand_total'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });
    }

    /**
     * Gives the units held by a booking back to the inventory.
     */
    public function releaseInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, -1);
    }

    private function adjustInventory(Booking $booking, int $direction): void
    {
        RoomInventory::where('room_type_id', $booking->room_type_id)
            ->whereDate('stay_date', '>=', $booking->check_in->toDateString())
            ->whereDate('stay_date', '<=', $booking->check_out->copy()->subDay()->toDateString())
            ->{$direction > 0 ? 'increment' : 'decrement'}('booked_units', $booking->room_count);
    }
}
