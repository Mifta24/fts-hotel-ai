<?php

namespace App\Services\Concierge;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Hotel;
use App\Models\HotelKnowledgeItem;
use App\Models\RoomType;
use App\Notifications\NewHandoverRequest;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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

    private const MAX_FACILITY_CARDS = 6;

    private const FACILITY_CATEGORIES = ['facilities', 'dining', 'transport'];

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
                    'name' => 'show_facility_photos',
                    'description' => 'Show the hotel\'s facilities as tappable photo cards on screen. Use this when the guest wants to SEE a facility or the facilities (e.g. "tunjukkan foto kolam renang", "show me the gym", "can I see the restaurant", "lihat fasilitas"). Leave query empty to show every facility that has a photo. Call search_knowledge as well if the guest also asks a factual question about it.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Optional keywords for one facility, e.g. "pool", "gym", "restaurant". Empty shows all.'],
                        ],
                    ],
                ],
                [
                    'name' => 'show_location',
                    'description' => 'Show the hotel\'s address and a map card with "open in maps" and "get directions" buttons, plus how to get there (airport, transfers). Use this when the guest asks where the hotel is, how to get there, wants to see the location or the map, or the address.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                ],
                [
                    'name' => 'show_room_photos',
                    'description' => 'Show a photo gallery of rooms. Use this when the guest wants to SEE a specific part of the rooms — the bathroom, the view, the bed, the balcony — without naming one room, or wants a photo set of one room. Pass image_tag (e.g. "bathroom", "bathtub", "view", "balcony", "bedroom") and/or room_type_slug.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'image_tag' => ['type' => 'string', 'description' => 'What to see, e.g. "bathroom", "view", "bedroom"'],
                            'room_type_slug' => ['type' => 'string', 'description' => 'Optional: only this room'],
                        ],
                    ],
                ],
                [
                    'name' => 'show_menu',
                    'description' => 'Show the restaurant / dining card with opening hours, photos and the menu the hotel listed. Use this when the guest wants to see the menu, the restaurant, breakfast or what food is available. Leave query empty for every dining entry.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Optional keywords, e.g. "breakfast", "restaurant"'],
                        ],
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
                            'rooms' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of rooms, default 1'],
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
     * @return array{text: string, ui: array|null, miss?: bool}
     */
    public function dispatch(string $name, array $input): array
    {
        try {
            return $this->run($name, $input);
        } catch (\Throwable $e) {
            Log::warning('Concierge tool call failed', ['tool' => $name, 'input' => $input, 'error' => $e->getMessage()]);

            return [
                'text' => "The {$name} call was rejected because its arguments were missing or invalid (dates must be YYYY-MM-DD, slugs must come from the room list). Do not guess: call it again with corrected arguments, or ask the guest for what is missing.",
                'ui' => null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null}
     */
    private function run(string $name, array $input): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($input),
            'show_facility_photos' => $this->showFacilityPhotos($input),
            'show_location' => $this->showLocation(),
            'show_room_photos' => $this->showRoomPhotos($input),
            'show_menu' => $this->showMenu($input),
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
        $items = $this->matchingKnowledge((string) ($input['query'] ?? ''), $input['category'] ?? null)->take(5);

        if ($items->isEmpty()) {
            return [
                'text' => 'No matching knowledge base entry was found. Do not guess the answer — tell the guest you will check with the team, or call request_human_handover.',
                'ui' => null,
                'miss' => true,
            ];
        }

        $results = $items->map(fn (HotelKnowledgeItem $item) => [
            'category' => $item->category,
            'title' => $item->translatedTitle($this->locale),
            'body' => $item->translatedBody($this->locale),
        ])->values()->all();

        return ['text' => json_encode($results, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    /**
     * Active knowledge entries whose title, body or tags mention the query.
     *
     * @return Collection<int, HotelKnowledgeItem>
     */
    private function matchingKnowledge(string $query, ?string $category = null): Collection
    {
        $query = trim($query);
        $words = collect(preg_split('/\s+/', Str::lower($query)))->filter(fn ($word) => mb_strlen($word) >= 3);

        return $this->hotel->knowledgeItems()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('sort_order')
            ->get()
            ->filter(function (HotelKnowledgeItem $item) use ($query, $words) {
                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower($item->title.' '.$item->body.' '.implode(' ', $item->tags ?? []));

                return Str::contains($haystack, Str::lower($query))
                    || $words->contains(fn ($word) => Str::contains($haystack, $word));
            })
            ->values();
    }

    private function showFacilityPhotos(array $input): array
    {
        $facilities = $this->matchingKnowledge((string) ($input['query'] ?? ''))
            ->filter(fn (HotelKnowledgeItem $item) => in_array($item->category, self::FACILITY_CATEGORIES, true) && $item->photoUrls() !== [])
            ->take(self::MAX_FACILITY_CARDS)
            ->map(fn (HotelKnowledgeItem $item) => [
                'id' => $item->id,
                'title' => $item->translatedTitle($this->locale),
                'body' => $item->translatedBody($this->locale),
                'image_url' => $item->photoUrls()[0],
                'images' => array_slice($item->photoUrls(), 0, self::MAX_CARD_PHOTOS + 1),
                'hours' => $item->openingHours(),
                'icon' => $item->iconName(),
            ])
            ->values()
            ->all();

        if ($facilities === []) {
            return [
                'text' => 'No facility photo matches that. Say so honestly, answer from search_knowledge if there is a text entry, or offer to show all facilities.',
                'ui' => null,
                'miss' => true,
            ];
        }

        return [
            'text' => json_encode([
                'note' => 'The facility photo cards are now on screen. Comment on them in one or two short sentences; do not list every facility again and do not invent hours or fees.',
                'facilities' => array_map(fn (array $facility) => ['title' => $facility['title'], 'body' => $facility['body']], $facilities),
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'facility_results', 'facilities' => $facilities],
        ];
    }

    private function showLocation(): array
    {
        $hotel = $this->hotel;
        $hasPoint = $hotel->latitude !== null && $hotel->longitude !== null;
        $address = collect([$hotel->address, $hotel->city, $hotel->country])->filter()->implode(', ');

        if (! $hasPoint && $address === '') {
            return [
                'text' => 'The hotel has no address or map point on file. Say so honestly and call request_human_handover if the guest needs directions.',
                'ui' => null,
            ];
        }

        $target = $hasPoint ? (float) $hotel->latitude.','.(float) $hotel->longitude : $address;
        $getting = $this->matchingKnowledge('', HotelKnowledgeItem::CATEGORY_TRANSPORT)
            ->take(3)
            ->map(fn (HotelKnowledgeItem $item) => [
                'title' => $item->translatedTitle($this->locale),
                'body' => $item->translatedBody($this->locale),
            ])
            ->values()
            ->all();

        $card = [
            'name' => $hotel->name,
            'address' => $address,
            'latitude' => $hasPoint ? (float) $hotel->latitude : null,
            'longitude' => $hasPoint ? (float) $hotel->longitude : null,
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($target),
            'directions_url' => 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($target),
            'phone' => $hotel->phone,
            'getting_there' => $getting,
        ];

        return [
            'text' => json_encode([
                'note' => 'A location card with the address and map buttons is now on screen. Comment on it in one short sentence; use only the facts below for how to get there.',
                'address' => $address,
                'getting_there' => $getting,
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'location', 'location' => $card],
        ];
    }

    private function showRoomPhotos(array $input): array
    {
        $tag = filled($input['image_tag'] ?? null) ? Str::lower(trim((string) $input['image_tag'])) : null;
        $slug = filled($input['room_type_slug'] ?? null) ? (string) $input['room_type_slug'] : null;

        $rooms = $slug
            ? $this->matchRoomTypes($slug)->load('images')
            : $this->hotel->roomTypes()->where('is_active', true)->orderBy('sort_order')->with('images')->get();

        $photos = $rooms->flatMap(function (RoomType $roomType) use ($tag, $slug) {
            $images = $roomType->images->filter(fn ($image) => filled($image->image_source));

            if ($tag) {
                $images = $images->filter(fn ($image) => collect($image->tags ?? [])->contains(fn ($t) => Str::lower($t) === $tag));
            } elseif (! $slug) {
                $images = $images->take(1);
            }

            return $images->map(fn ($image) => [
                'url' => $image->image_source,
                'alt' => $image->alt_text ?: $roomType->translatedName($this->locale),
                'tags' => $image->tags ?? [],
                'room_name' => $roomType->translatedName($this->locale),
                'room_type_slug' => $roomType->slug,
            ]);
        })->take(12)->values()->all();

        if ($photos === []) {
            return [
                'text' => $rooms->isEmpty() && $slug
                    ? 'No room matches that name. The rooms are: '.$this->roomDirectory().'. Call show_room_photos again with one of these slugs, or recommend_rooms.'
                    : 'No room photo matches that. Say so honestly and offer to show a room\'s details with get_room_detail or recommend_rooms.',
                'ui' => null,
            ];
        }

        return [
            'text' => json_encode([
                'note' => 'The room photo gallery is now on screen. Comment on it in one short sentence; do not list the photos.',
                'rooms' => collect($photos)->pluck('room_name')->unique()->values()->all(),
                'image_tag' => $tag,
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'room_photos', 'image_tag' => $tag, 'photos' => $photos],
        ];
    }

    private function showMenu(array $input): array
    {
        $entries = $this->matchingKnowledge((string) ($input['query'] ?? ''), HotelKnowledgeItem::CATEGORY_DINING)
            ->take(3)
            ->map(fn (HotelKnowledgeItem $item) => [
                'id' => $item->id,
                'title' => $item->translatedTitle($this->locale),
                'body' => $item->translatedBody($this->locale),
                'hours' => $item->openingHours(),
                'menu' => $item->menuEntries(),
                'images' => array_slice($item->photoUrls(), 0, self::MAX_CARD_PHOTOS + 1),
            ])
            ->values()
            ->all();

        if ($entries === []) {
            return [
                'text' => 'No dining entry matches that. Say so honestly, or answer from search_knowledge, or call request_human_handover.',
                'ui' => null,
                'miss' => true,
            ];
        }

        return [
            'text' => json_encode([
                'note' => 'The dining card is now on screen with the hours, photos and menu. Comment on it in one short sentence; use only these facts and never invent dishes, prices or hours.',
                'dining' => array_map(fn (array $entry) => ['title' => $entry['title'], 'body' => $entry['body'], 'hours' => $entry['hours'], 'menu' => $entry['menu']], $entries),
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'dining_menu', 'entries' => $entries],
        ];
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
        $roomType = $this->findRoomType($input['room_type_slug']) ?? $this->singleMatchingRoomType($input['room_type_slug']);
        if (! $roomType) {
            return ['text' => 'Room type not found. The rooms are: '.$this->roomDirectory().'.', 'ui' => null];
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
        $rooms = max(1, (int) ($input['rooms'] ?? 1));
        $stay = $this->reservations->quote($roomType, $checkIn, $checkOut, $rooms, $extraBed);

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
            'rooms' => $rooms,
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
            'guest_name' => Str::limit((string) $input['guest_name'], 100, ''),
            'guest_email' => filled($input['guest_email'] ?? null) ? Str::limit((string) $input['guest_email'], 120, '') : null,
            'guest_phone' => $input['guest_phone'],
            'notes' => filled($input['notes'] ?? null) ? Str::limit((string) $input['notes'], 500, '') : null,
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

        $handover = HandoverRequest::updateOrCreate(
            ['conversation_id' => $this->conversation->id, 'status' => HandoverRequest::STATUS_OPEN],
            ['reason' => $reason, 'summary' => $summary],
        );

        if ($handover->wasRecentlyCreated) {
            $this->reservations->notifyStaff($this->hotel, new NewHandoverRequest($handover));
        }

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
        return $this->hotel->roomTypes()->where('is_active', true)->where('slug', $slug)->first();
    }

    /**
     * The active rooms a guest's wording points at: the exact slug, else any
     * room whose slug or name (in any language) contains the term or one of
     * its words — so "suite room" finds "Family Suite".
     *
     * @return Collection<int, RoomType>
     */
    private function matchRoomTypes(string $term): Collection
    {
        $rooms = $this->hotel->roomTypes()->where('is_active', true)->orderBy('sort_order')->get();
        $term = Str::lower(trim($term));

        $exact = $rooms->where('slug', $term);
        if ($exact->isNotEmpty()) {
            return $exact->values();
        }

        $words = collect(preg_split('/[\s_-]+/u', $term))
            ->filter(fn ($word) => mb_strlen($word) >= 3 && ! in_array($word, ['room', 'rooms', 'kamar', 'the', 'type'], true))
            ->values();

        return $rooms->filter(function (RoomType $room) use ($term, $words) {
            $haystack = Str::lower(str_replace('-', ' ', $room->slug).' '.$room->name.' '.collect($room->translations ?? [])->pluck('name')->filter()->implode(' '));

            return Str::contains($haystack, str_replace('-', ' ', $term))
                || $words->contains(fn ($word) => Str::contains($haystack, $word));
        })->values();
    }

    /**
     * The one active room a guest's free text names. A full room name (in any
     * language) wins; otherwise at least two words of a room's name must be
     * found, typos tolerated, and one of them must belong to that room alone
     * — so "deluxe" or "pool" never pick a room by themselves.
     */
    public function roomNamedIn(string $text): ?RoomType
    {
        $rooms = $this->hotel->roomTypes()->where('is_active', true)->orderBy('sort_order')->get();
        $text = Str::lower($text);

        $named = $rooms->filter(fn (RoomType $room) => $this->roomNames($room)->contains(fn (string $name) => Str::contains($text, $name)));
        if ($named->count() === 1) {
            return $named->first();
        }

        $guestWords = $this->nameWords($text);
        $wordsByRoom = $rooms->mapWithKeys(fn (RoomType $room) => [$room->id => $this->roomNames($room)->flatMap(fn (string $name) => $this->nameWords($name))->unique()->values()]);
        $roomsPerWord = $wordsByRoom->flatten()->countBy();

        $scored = $rooms->map(function (RoomType $room) use ($guestWords, $wordsByRoom, $roomsPerWord) {
            $found = $wordsByRoom[$room->id]->filter(fn (string $word) => $guestWords->contains(fn (string $guestWord) => $this->isSameWord($guestWord, $word)));

            return [
                'room' => $room,
                'score' => $found->count(),
                'distinctive' => $found->contains(fn (string $word) => $roomsPerWord[$word] === 1),
            ];
        })->filter(fn (array $row) => $row['score'] >= 2 && $row['distinctive'])->sortByDesc('score')->values();

        if ($scored->isEmpty() || ($scored->count() > 1 && $scored[0]['score'] === $scored[1]['score'])) {
            return null;
        }

        return $scored[0]['room'];
    }

    /**
     * @return Collection<int, string>
     */
    private function roomNames(RoomType $room): Collection
    {
        return collect([$room->name, ...collect($room->translations ?? [])->pluck('name')->all()])
            ->filter()
            ->map(fn ($name) => Str::lower((string) $name))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function nameWords(string $text): Collection
    {
        return collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, ['room', 'rooms', 'kamar', 'the', 'type', 'with'], true))
            ->values();
    }

    private function isSameWord(string $guestWord, string $roomWord): bool
    {
        return $guestWord === $roomWord
            || (mb_strlen($guestWord) >= 5 && mb_strlen($roomWord) >= 5 && levenshtein($guestWord, $roomWord) <= 1);
    }

    private function singleMatchingRoomType(string $term): ?RoomType
    {
        $matches = $this->matchRoomTypes($term);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function roomDirectory(): string
    {
        return $this->hotel->roomTypes()->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn (RoomType $room) => "{$room->name} (slug: {$room->slug})")
            ->implode(', ');
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
