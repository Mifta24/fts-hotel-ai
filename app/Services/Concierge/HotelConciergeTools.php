<?php

namespace App\Services\Concierge;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Hotel;
use App\Models\HotelKnowledgeItem;
use App\Models\RoomType;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Executes the AI Concierge's tools against this hotel's controlled data.
 * Every hotel fact, room, price and availability answer must pass through
 * here — the model itself is never trusted to hold any of it.
 */
class HotelConciergeTools
{
    private const MAX_RECOMMENDATIONS = 6;

    private const MAX_CARD_PHOTOS = 4;

    public function __construct(
        private readonly Hotel $hotel,
        private readonly Conversation $conversation,
        private readonly string $locale,
        private readonly ReservationService $reservations = new ReservationService,
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
                    'name' => 'recommend_rooms',
                    'description' => 'Show the hotel\'s rooms as tappable cards, with photos, details and an indicative starting price per night. Use this when the guest asks for a recommendation or what rooms exist and has NOT given dates yet (e.g. "rekomendasi kamar", "what rooms do you have", "best room for a family"). Do not ask for dates first — show the cards, then let the guest tap a card to reserve.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'adults' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Optional: only rooms that fit this party'],
                            'children' => ['type' => 'integer', 'minimum' => 0],
                            'view_type' => ['type' => 'string', 'description' => 'e.g. ocean, garden, pool — optional preference'],
                        ],
                    ],
                ],
                [
                    'name' => 'open_booking_form',
                    'description' => 'Open a reservation form inside the chat for one room, pre-filled with whatever the guest already told you (dates, party size, name, phone). Use this when the guest wants to book but is missing details, instead of asking for each detail one by one — they fill the rest by tapping. If the guest already gave room, dates, party, name and phone in text, call create_booking_request instead.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'room_type_slug' => ['type' => 'string'],
                            'check_in' => ['type' => 'string', 'description' => 'YYYY-MM-DD, only if the guest said it'],
                            'check_out' => ['type' => 'string', 'description' => 'YYYY-MM-DD, only if the guest said it'],
                            'adults' => ['type' => 'integer', 'minimum' => 1],
                            'children' => ['type' => 'integer', 'minimum' => 0],
                            'guest_name' => ['type' => 'string'],
                            'guest_phone' => ['type' => 'string'],
                        ],
                        'required' => ['room_type_slug'],
                    ],
                ],
                [
                    'name' => 'ask_stay_details',
                    'description' => 'Show a calendar and a guest counter in the chat so the guest picks their check-in and check-out dates and party size by tapping. Use this whenever you need dates or party size that the guest has not given yet — instead of asking for them in text. Optionally name the room they are choosing for. Their choice arrives as their next message.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'room_type_slug' => ['type' => 'string', 'description' => 'Optional: the room the guest is choosing dates for'],
                            'check_in' => ['type' => 'string', 'description' => 'YYYY-MM-DD, only if the guest already said it'],
                            'check_out' => ['type' => 'string', 'description' => 'YYYY-MM-DD, only if the guest already said it'],
                            'adults' => ['type' => 'integer', 'minimum' => 1],
                            'children' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                ],
                [
                    'name' => 'start_tour',
                    'description' => 'Start a guided tour of the hotel, narrated by the concierge, that walks the guest through the rooms and facilities. Use it when the guest asks to be shown around, asks for a tour, or does not know where to start.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
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
                            'rooms' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of rooms, default 1'],
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
            'recommend_rooms' => $this->recommendRooms($input),
            'open_booking_form' => $this->openBookingForm($input),
            'ask_stay_details' => $this->askStayDetails($input),
            'start_tour' => $this->startTour(),
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
        [$checkIn, $checkOut, $nights] = $this->parseStay($input['check_in'], $input['check_out']);
        $adults = (int) $input['adults'];
        $children = (int) ($input['children'] ?? 0);
        $viewType = $input['view_type'] ?? null;

        if ($nights < 1) {
            return ['text' => 'check_out must be after check_in.', 'ui' => null];
        }

        $candidates = $this->hotel->roomTypes()
            ->where('is_active', true)
            ->get()
            ->filter(fn (RoomType $rt) => $this->reservations->fitsOccupancy($rt, $adults, $children, 1))
            ->when($viewType, fn ($c) => $c->filter(fn (RoomType $rt) => $rt->view_type === $viewType));

        $matches = [];
        foreach ($candidates as $roomType) {
            $stay = $this->reservations->quote($roomType, $checkIn, $checkOut);
            if (! $stay) {
                continue;
            }

            $matches[] = [
                ...$this->roomCard($roomType),
                'nights' => $nights,
                'total_price' => $stay['room_total'],
                'min_available_units' => $stay['min_available_units'],
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
            'text' => json_encode(array_map($this->withoutPhotos(...), $matches), JSON_UNESCAPED_UNICODE),
            'ui' => [
                'type' => 'room_results',
                'check_in' => $input['check_in'],
                'check_out' => $input['check_out'],
                'rooms' => $matches,
            ],
        ];
    }

    private function recommendRooms(array $input): array
    {
        $adults = (int) ($input['adults'] ?? 0);
        $children = (int) ($input['children'] ?? 0);
        $viewType = $input['view_type'] ?? null;

        $rooms = $this->hotel->roomTypes()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->when($adults > 0, fn ($c) => $c->filter(fn (RoomType $rt) => $this->reservations->fitsOccupancy($rt, $adults, $children, 1)))
            ->when($viewType, fn ($c) => $c->filter(fn (RoomType $rt) => $rt->view_type === $viewType))
            ->take(self::MAX_RECOMMENDATIONS)
            ->map(fn (RoomType $rt) => $this->roomCard($rt))
            ->values()
            ->all();

        if ($rooms === []) {
            return [
                'text' => 'No room type matches that preference. Say so honestly and offer to show every room, or call request_human_handover.',
                'ui' => null,
            ];
        }

        return [
            'text' => json_encode([
                'note' => 'starting_price is an indicative price per night only. The cards are on screen with a reserve button; the guest picks dates in the reservation form, which shows the exact total. Do not list the rooms again.',
                'rooms' => array_map($this->withoutPhotos(...), $rooms),
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'room_results', 'check_in' => null, 'check_out' => null, 'rooms' => $rooms],
        ];
    }

    private function askStayDetails(array $input): array
    {
        $roomType = filled($input['room_type_slug'] ?? null) ? $this->findRoomType($input['room_type_slug']) : null;

        $values = array_filter([
            'check_in' => $this->validDate($input['check_in'] ?? null),
            'check_out' => $this->validDate($input['check_out'] ?? null),
            'adults' => isset($input['adults']) ? max(1, (int) $input['adults']) : null,
            'children' => isset($input['children']) ? max(0, (int) $input['children']) : null,
        ], fn ($value) => $value !== null);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A calendar and guest counter are now on screen. Tell the guest in one short sentence to pick their dates and number of guests. Do not ask for them in text: their choice arrives as their next message.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'stay_picker', 'room' => $roomType?->is_active ? $this->roomCard($roomType) : null, 'values' => $values],
        ];
    }

    private function startTour(): array
    {
        return [
            'text' => json_encode(['ok' => true, 'message' => 'The guided tour is starting on screen. Say one short, warm sentence to introduce it; the tour narrates the rest.'], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'tour_start'],
        ];
    }

    private function openBookingForm(array $input): array
    {
        $roomType = $this->findRoomType($input['room_type_slug']);
        if (! $roomType || ! $roomType->is_active) {
            return ['text' => 'Room type not found.', 'ui' => null];
        }

        $form = [
            'room' => $this->roomCard($roomType),
            'values' => array_filter([
                'check_in' => $this->validDate($input['check_in'] ?? null),
                'check_out' => $this->validDate($input['check_out'] ?? null),
                'adults' => isset($input['adults']) ? max(1, (int) $input['adults']) : null,
                'children' => isset($input['children']) ? max(0, (int) $input['children']) : null,
                'guest_name' => filled($input['guest_name'] ?? null) ? (string) $input['guest_name'] : null,
                'contact_value' => filled($input['guest_phone'] ?? null) ? (string) $input['guest_phone'] : null,
            ], fn ($value) => $value !== null),
        ];

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A reservation form for this room is now on screen, pre-filled with what the guest told you. Tell them in one short sentence to check the details and tap the confirm button. Do not ask for the details again in text.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'booking_form', ...$form],
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

        $extraBed = (bool) ($input['extra_bed'] ?? false);
        $stay = $this->reservations->quote($roomType, $checkIn, $checkOut, extraBed: $extraBed);

        if (! $stay) {
            return [
                'text' => json_encode([
                    'available' => false,
                    'room_type_slug' => $roomType->slug,
                    'message' => 'Not every night in this range has availability for this room type.',
                ], JSON_UNESCAPED_UNICODE),
                'ui' => ['type' => 'availability', 'available' => false, 'room_type_slug' => $roomType->slug, 'room' => $this->roomCard($roomType)],
            ];
        }

        $result = [
            'available' => true,
            'room_type_slug' => $roomType->slug,
            'name' => $roomType->translatedName($this->locale),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $nights,
            'nightly_breakdown' => $stay['nightly'],
            'room_total' => $stay['room_total'],
            'extra_bed_total' => $stay['extra_bed_total'],
            'grand_total' => $stay['grand_total'],
            'currency' => $this->hotel->currency,
            'min_available_units' => $stay['min_available_units'],
        ];

        return [
            'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'availability', 'available' => true, 'quote' => $result, 'room' => $this->roomCard($roomType)],
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

        if ($checkIn->isBefore(now($this->hotel->timezone)->startOfDay()) || $nights > ReservationService::MAX_NIGHTS) {
            return ['text' => 'Check-in cannot be in the past and a stay is limited to '.ReservationService::MAX_NIGHTS.' nights. Ask the guest for valid dates, or call request_human_handover for longer stays.', 'ui' => null];
        }

        if (! preg_match(ReservationService::PHONE_PATTERN, (string) $input['guest_phone'])) {
            return ['text' => 'That phone number is not valid (it needs 6 to 20 digits). Ask the guest for a valid phone or WhatsApp number, or call open_booking_form so they can enter it themselves.', 'ui' => null];
        }

        if (filled($input['guest_email'] ?? null) && ! filter_var($input['guest_email'], FILTER_VALIDATE_EMAIL)) {
            return ['text' => 'That email address is not valid. The email is optional: leave guest_email out, or ask the guest to correct it.', 'ui' => null];
        }

        $rooms = max(1, (int) ($input['rooms'] ?? 1));

        if (! $this->reservations->fitsOccupancy($roomType, (int) $input['adults'], (int) ($input['children'] ?? 0), $rooms)) {
            return ['text' => 'This party does not fit that room type for the requested number of rooms. Suggest another room type or more rooms.', 'ui' => null];
        }

        $booking = $this->reservations->createRequest($this->hotel, $roomType, [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => (int) $input['adults'],
            'children' => (int) ($input['children'] ?? 0),
            'rooms' => $rooms,
            'extra_bed' => (bool) ($input['extra_bed'] ?? false),
            'guest_name' => $input['guest_name'],
            'guest_email' => $input['guest_email'] ?? null,
            'guest_phone' => $input['guest_phone'],
            'notes' => $input['notes'] ?? null,
        ], $this->conversation);

        if (! $booking) {
            return [
                'text' => 'Room is no longer available for these exact dates — availability may have just changed. Call check_availability again or offer alternative dates.',
                'ui' => null,
            ];
        }

        $confirmation = self::bookingConfirmation($booking, $roomType, $this->locale, $this->hotel->currency);

        return [
            'text' => json_encode($confirmation['booking'], JSON_UNESCAPED_UNICODE),
            'ui' => $confirmation,
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

    /**
     * The room as the chat renders it: enough to show a card and its details
     * without another round trip. Prices here are indicative only.
     *
     * @return array<string, mixed>
     */
    private function roomCard(RoomType $roomType): array
    {
        $images = $roomType->images->take(self::MAX_CARD_PHOTOS)->map(fn ($img) => [
            'url' => $img->image_source,
            'alt' => $img->alt_text,
        ])->values()->all();

        return [
            'room_type_slug' => $roomType->slug,
            'name' => $roomType->translatedName($this->locale),
            'description' => $roomType->translatedDescription($this->locale),
            'size_sqm' => $roomType->size_sqm,
            'view_type' => $roomType->view_type,
            'bed_config' => $roomType->bed_config,
            'amenities' => $roomType->amenities ?? [],
            'max_adults' => $roomType->max_adults,
            'max_children' => $roomType->max_children,
            'breakfast_included' => $roomType->breakfast_included,
            'extra_bed_available' => $roomType->extra_bed_available,
            'starting_price' => (float) $roomType->base_price,
            'currency' => $this->hotel->currency,
            'thumbnail_url' => $images[0]['url'] ?? null,
            'images' => $images,
        ];
    }

    /**
     * Drops what only the screen needs, so the model is not fed photo URLs.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function withoutPhotos(array $card): array
    {
        unset($card['images'], $card['thumbnail_url']);

        return $card;
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::parse($value);

        return $date->isBefore(now($this->hotel->timezone)->startOfDay()) ? null : $date->toDateString();
    }

    /**
     * What the chat shows once a booking request exists, whether the AI or the
     * guest's own taps created it.
     *
     * @return array{type: string, booking: array<string, mixed>}
     */
    public static function bookingConfirmation(Booking $booking, RoomType $roomType, string $locale, string $currency): array
    {
        return [
            'type' => 'booking_confirmation',
            'booking' => [
                'booking_reference' => $booking->reference,
                'room_type_slug' => $roomType->slug,
                'name' => $roomType->translatedName($locale),
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'nights' => (int) $booking->check_in->diffInDays($booking->check_out),
                'rooms' => $booking->room_count,
                'total_price' => (float) $booking->total_price,
                'currency' => $currency,
                'status' => 'pending_confirmation',
            ],
        ];
    }

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
}
