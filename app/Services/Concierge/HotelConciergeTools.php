<?php

namespace App\Services\Concierge;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Hotel;
use App\Models\HotelKnowledgeItem;
use App\Models\RoomInventory;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Executes the AI Concierge's tools against this hotel's controlled data.
 * Every hotel fact, room, price and availability answer must pass through
 * here — the model itself is never trusted to hold any of it.
 */
class HotelConciergeTools
{
    public function __construct(
        private readonly Hotel $hotel,
        private readonly Conversation $conversation,
        private readonly string $locale,
    ) {}

    /**
     * OpenAI-compatible function-calling schema (used by the local LM Studio
     * / Ollama endpoint via ConciergeService — see topic in that class).
     */
    public static function definitions(): array
    {
        return array_map(
            fn (array $tool) => ['type' => 'function', 'function' => $tool],
            [
                [
                    'name' => 'search_knowledge',
                    'description' => 'Search the hotel\'s approved knowledge base for facts about the hotel: policies, facilities, dining, transport, and FAQs. Always use this instead of answering hotel-fact questions from memory. Returns up to 5 matching entries.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords from the guest question, e.g. "late check-in" or "bathtub"'],
                            'category' => [
                                'type' => 'string',
                                'enum' => ['general', 'facilities', 'policies', 'dining', 'transport', 'faq'],
                                'description' => 'Optional category filter',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'search_rooms',
                    'description' => 'Search room types that fit the guest\'s dates and party size, with real-time availability and total price already checked. Use this whenever a guest describes what they want (dates, guests, view, budget) rather than naming one room by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'check_in' => ['type' => 'string', 'description' => 'Check-in date, YYYY-MM-DD'],
                            'check_out' => ['type' => 'string', 'description' => 'Check-out date, YYYY-MM-DD'],
                            'adults' => ['type' => 'integer', 'minimum' => 1],
                            'children' => ['type' => 'integer', 'minimum' => 0],
                            'view_type' => ['type' => 'string', 'description' => 'e.g. ocean, garden, pool — optional preference'],
                        ],
                        'required' => ['check_in', 'check_out', 'adults'],
                    ],
                ],
                [
                    'name' => 'get_room_detail',
                    'description' => 'Get full details and photos for one room type by its slug (from a previous search_rooms result). Use this when the guest asks to see more about, or see photos of, a specific room.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'room_type_slug' => ['type' => 'string'],
                            'image_tag' => ['type' => 'string', 'description' => 'Optional filter, e.g. "bathroom", "bathtub", "view", "pool"'],
                        ],
                        'required' => ['room_type_slug'],
                    ],
                ],
                [
                    'name' => 'check_availability',
                    'description' => 'Get the current, real-time availability and exact total price for one room type over specific dates. ALWAYS call this before confirming a price or telling a guest a room is available — never state a price or availability from memory.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'room_type_slug' => ['type' => 'string'],
                            'check_in' => ['type' => 'string'],
                            'check_out' => ['type' => 'string'],
                            'extra_bed' => ['type' => 'boolean'],
                        ],
                        'required' => ['room_type_slug', 'check_in', 'check_out'],
                    ],
                ],
                [
                    'name' => 'create_booking_request',
                    'description' => 'Create a reservation request after the guest confirms the room and dates and you have their name and phone. This re-checks availability before booking. It does not charge payment — it creates a pending reservation for the hotel to confirm.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'room_type_slug' => ['type' => 'string'],
                            'check_in' => ['type' => 'string'],
                            'check_out' => ['type' => 'string'],
                            'adults' => ['type' => 'integer'],
                            'children' => ['type' => 'integer'],
                            'extra_bed' => ['type' => 'boolean'],
                            'guest_name' => ['type' => 'string'],
                            'guest_email' => ['type' => 'string'],
                            'guest_phone' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['room_type_slug', 'check_in', 'check_out', 'adults', 'guest_name', 'guest_phone'],
                    ],
                ],
                [
                    'name' => 'request_human_handover',
                    'description' => 'Hand this conversation over to a human hotel staff member. Use this for special requests, complaints, group bookings, negotiated rates, unusual cancellations, payment problems, or any question you cannot answer confidently from the available tools. Always write a clear summary so staff do not need to ask the guest to repeat themselves.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'enum' => ['special_request', 'complaint', 'group_booking', 'negotiated_rate', 'unusual_cancellation', 'payment_issue', 'low_confidence'],
                            ],
                            'summary' => ['type' => 'string', 'description' => 'What the guest wants and the relevant context gathered so far, written for a staff member who has not seen this conversation'],
                        ],
                        'required' => ['reason', 'summary'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null}
     */
    public function dispatch(string $name, array $input): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($input),
            'search_rooms' => $this->searchRooms($input),
            'get_room_detail' => $this->getRoomDetail($input),
            'check_availability' => $this->checkAvailability($input),
            'create_booking_request' => $this->createBookingRequest($input),
            'request_human_handover' => $this->requestHumanHandover($input),
            default => ['text' => "Unknown tool: {$name}", 'ui' => null],
        };
    }

    private function searchKnowledge(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $category = $input['category'] ?? null;
        $words = collect(preg_split('/\s+/', Str::lower($query)))->filter();

        $items = $this->hotel->knowledgeItems()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get()
            ->filter(function (HotelKnowledgeItem $item) use ($query, $words) {
                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower($item->title.' '.$item->body.' '.implode(' ', $item->tags ?? []));

                return Str::contains($haystack, Str::lower($query))
                    || $words->contains(fn ($word) => Str::contains($haystack, $word));
            })
            ->take(5);

        if ($items->isEmpty()) {
            return [
                'text' => 'No matching knowledge base entry was found. Do not guess the answer — tell the guest you will check with the team, or call request_human_handover.',
                'ui' => null,
            ];
        }

        $results = $items->map(fn (HotelKnowledgeItem $item) => [
            'category' => $item->category,
            'title' => $item->translatedTitle($this->locale),
            'body' => $item->translatedBody($this->locale),
        ])->values()->all();

        return ['text' => json_encode($results, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    private function searchRooms(array $input): array
    {
        [$checkIn, , $nights] = $this->parseStay($input['check_in'], $input['check_out']);
        $adults = (int) $input['adults'];
        $children = (int) ($input['children'] ?? 0);
        $viewType = $input['view_type'] ?? null;

        if ($nights < 1) {
            return ['text' => 'check_out must be after check_in.', 'ui' => null];
        }

        $candidates = $this->hotel->roomTypes()
            ->where('is_active', true)
            ->get()
            ->filter(fn (RoomType $rt) => $rt->max_adults >= $adults && ($rt->max_adults + $rt->max_children) >= ($adults + $children))
            ->when($viewType, fn ($c) => $c->filter(fn (RoomType $rt) => $rt->view_type === $viewType));

        $matches = [];
        foreach ($candidates as $roomType) {
            $stay = $this->priceStay($roomType, $checkIn, $nights);
            if (! $stay) {
                continue;
            }

            $thumbnail = $roomType->images->first();

            $matches[] = [
                'room_type_slug' => $roomType->slug,
                'name' => $roomType->translatedName($this->locale),
                'size_sqm' => $roomType->size_sqm,
                'view_type' => $roomType->view_type,
                'max_adults' => $roomType->max_adults,
                'max_children' => $roomType->max_children,
                'breakfast_included' => $roomType->breakfast_included,
                'nights' => $nights,
                'total_price' => $stay['total_price'],
                'currency' => $this->hotel->currency,
                'min_available_units' => $stay['min_available_units'],
                'thumbnail_url' => $thumbnail?->image_source,
            ];
        }

        if (empty($matches)) {
            return [
                'text' => 'No room type has availability for every night of that stay for this party size. Tell the guest honestly and offer to check different dates, or call request_human_handover if they want to wait-list.',
                'ui' => null,
            ];
        }

        usort($matches, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

        return [
            'text' => json_encode($matches, JSON_UNESCAPED_UNICODE),
            'ui' => [
                'type' => 'room_results',
                'check_in' => $input['check_in'],
                'check_out' => $input['check_out'],
                'rooms' => $matches,
            ],
        ];
    }

    private function getRoomDetail(array $input): array
    {
        $roomType = $this->findRoomType($input['room_type_slug']);
        if (! $roomType) {
            return ['text' => 'Room type not found.', 'ui' => null];
        }

        $images = $roomType->images;
        $tag = $input['image_tag'] ?? null;
        if ($tag) {
            $images = $images->filter(fn ($img) => $img->hasTag($tag));
        }

        $detail = [
            'room_type_slug' => $roomType->slug,
            'name' => $roomType->translatedName($this->locale),
            'description' => $roomType->translatedDescription($this->locale),
            'size_sqm' => $roomType->size_sqm,
            'bed_config' => $roomType->bed_config,
            'view_type' => $roomType->view_type,
            'max_adults' => $roomType->max_adults,
            'max_children' => $roomType->max_children,
            'breakfast_included' => $roomType->breakfast_included,
            'extra_bed_available' => $roomType->extra_bed_available,
            'extra_bed_price' => $roomType->extra_bed_price,
            'amenities' => $roomType->amenities,
            'starting_price' => $roomType->base_price,
            'currency' => $this->hotel->currency,
            'note' => 'starting_price is indicative only — always call check_availability for the exact price on real dates.',
            'images' => $images->map(fn ($img) => [
                'url' => $img->image_source,
                'tags' => $img->tags,
                'alt' => $img->alt_text,
            ])->values()->all(),
        ];

        return [
            'text' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'room_detail', 'room' => $detail],
        ];
    }

    private function checkAvailability(array $input): array
    {
        $roomType = $this->findRoomType($input['room_type_slug']);
        if (! $roomType) {
            return ['text' => 'Room type not found.', 'ui' => null];
        }

        [$checkIn, $checkOut, $nights] = $this->parseStay($input['check_in'], $input['check_out']);
        if ($nights < 1) {
            return ['text' => 'check_out must be after check_in.', 'ui' => null];
        }

        $stay = $this->priceStay($roomType, $checkIn, $nights);

        if (! $stay) {
            return [
                'text' => json_encode([
                    'available' => false,
                    'room_type_slug' => $roomType->slug,
                    'message' => 'Not every night in this range has availability for this room type.',
                ], JSON_UNESCAPED_UNICODE),
                'ui' => ['type' => 'availability', 'available' => false, 'room_type_slug' => $roomType->slug],
            ];
        }

        $extraBed = (bool) ($input['extra_bed'] ?? false);
        $extraBedTotal = $extraBed && $roomType->extra_bed_available ? (float) $roomType->extra_bed_price * $nights : 0;

        $result = [
            'available' => true,
            'room_type_slug' => $roomType->slug,
            'name' => $roomType->translatedName($this->locale),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $nights,
            'nightly_breakdown' => $stay['nightly'],
            'room_total' => $stay['total_price'],
            'extra_bed_total' => $extraBedTotal,
            'grand_total' => $stay['total_price'] + $extraBedTotal,
            'currency' => $this->hotel->currency,
            'min_available_units' => $stay['min_available_units'],
        ];

        return [
            'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'availability', 'available' => true, 'quote' => $result],
        ];
    }

    private function createBookingRequest(array $input): array
    {
        $roomType = $this->findRoomType($input['room_type_slug']);
        if (! $roomType) {
            return ['text' => 'Room type not found.', 'ui' => null];
        }

        [$checkIn, $checkOut, $nights] = $this->parseStay($input['check_in'], $input['check_out']);
        if ($nights < 1) {
            return ['text' => 'check_out must be after check_in.', 'ui' => null];
        }

        $extraBed = (bool) ($input['extra_bed'] ?? false);
        $outcome = null;

        DB::transaction(function () use (&$outcome, $roomType, $checkIn, $checkOut, $nights, $input, $extraBed) {
            $stay = $this->priceStay($roomType, $checkIn, $nights, lockForUpdate: true);

            if (! $stay) {
                $outcome = [
                    'ok' => false,
                    'message' => 'Room is no longer available for these exact dates — availability may have just changed. Call check_availability again or offer alternative dates.',
                ];

                return;
            }

            $extraBedTotal = $extraBed && $roomType->extra_bed_available ? (float) $roomType->extra_bed_price * $nights : 0;

            $booking = Booking::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $roomType->id,
                'conversation_id' => $this->conversation->id,
                'guest_name' => $input['guest_name'],
                'guest_email' => $input['guest_email'] ?? null,
                'guest_phone' => $input['guest_phone'],
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'adults' => $input['adults'],
                'children' => $input['children'] ?? 0,
                'extra_bed' => $extraBed,
                'total_price' => $stay['total_price'] + $extraBedTotal,
                'status' => Booking::STATUS_PENDING,
                'notes' => $input['notes'] ?? null,
            ]);

            RoomInventory::where('room_type_id', $roomType->id)
                ->whereDate('stay_date', '>=', $checkIn->toDateString())
                ->whereDate('stay_date', '<=', $checkOut->subDay()->toDateString())
                ->increment('booked_units');

            $outcome = ['ok' => true, 'booking' => $booking];
        });

        if (! $outcome['ok']) {
            return ['text' => $outcome['message'], 'ui' => null];
        }

        $booking = $outcome['booking'];
        $payload = [
            'booking_reference' => 'BK-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
            'room_type_slug' => $roomType->slug,
            'name' => $roomType->translatedName($this->locale),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $nights,
            'total_price' => (float) $booking->total_price,
            'currency' => $this->hotel->currency,
            'status' => 'pending_confirmation',
        ];

        return [
            'text' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'booking_confirmation', 'booking' => $payload],
        ];
    }

    private function requestHumanHandover(array $input): array
    {
        $reason = $input['reason'];
        $summary = $input['summary'];

        HandoverRequest::create([
            'conversation_id' => $this->conversation->id,
            'reason' => $reason,
            'summary' => $summary,
            'status' => HandoverRequest::STATUS_OPEN,
        ]);

        $this->conversation->update([
            'status' => Conversation::STATUS_HANDED_OVER,
            'handover_summary' => $summary,
        ]);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A staff member has been notified and will join this conversation shortly.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'handover', 'reason' => $reason, 'summary' => $summary],
        ];
    }

    // --- helpers ---

    private function findRoomType(string $slug): ?RoomType
    {
        return $this->hotel->roomTypes()->where('slug', $slug)->first();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function parseStay(string $checkIn, string $checkOut): array
    {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();

        return [$in, $out, $in->diffInDays($out)];
    }

    /**
     * Prices a stay across every night in range; returns null if any night
     * lacks inventory or availability. This is the one place price and
     * availability truth comes from — never the model.
     *
     * @return array{nightly: array, total_price: float, min_available_units: int}|null
     */
    private function priceStay(RoomType $roomType, CarbonImmutable $checkIn, int $nights, bool $lockForUpdate = false): ?array
    {
        $query = RoomInventory::where('room_type_id', $roomType->id)
            ->whereDate('stay_date', '>=', $checkIn->toDateString())
            ->whereDate('stay_date', '<=', $checkIn->addDays($nights - 1)->toDateString())
            ->orderBy('stay_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $rows = $query->get();

        if ($rows->count() < $nights) {
            return null;
        }

        $nightly = [];
        $total = 0.0;
        $minAvailable = PHP_INT_MAX;

        foreach ($rows as $row) {
            if (! $row->isAvailable()) {
                return null;
            }

            $nightly[] = ['date' => $row->stay_date->toDateString(), 'price' => (float) $row->price];
            $total += (float) $row->price;
            $minAvailable = min($minAvailable, $row->availableUnits());
        }

        return [
            'nightly' => $nightly,
            'total_price' => $total,
            'min_available_units' => $minAvailable,
        ];
    }
}
