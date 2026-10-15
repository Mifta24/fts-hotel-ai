<?php

namespace Database\Seeders;

use App\Core\Member\Models\Member;
use App\Core\Member\Models\MemberPoint;
use App\Core\Member\Services\PointsLedger;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\HotelKnowledgeItem;
use App\Models\HotelUser;
use App\Models\User;
use App\Services\Reservation\BookingPoints;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Three more hotels, each showing the member add-ons differently:
 *
 *  - Cakrawala Business Hotel (Jakarta): Member Plus with its own points rate and three tiers.
 *  - Omah Kasturi (Yogyakarta): the member add-on only, so no points, tiers or discounts.
 *  - Kamogawa Machiya Stay (Kyoto): Member Plus on the starter program, priced in yen.
 *
 * The FTS Hotel AI demo hotel keeps no add-ons, so the four of them cover every combination.
 * Safe to run again: everything is matched on slug, email or booking reference.
 */
class MemberDemoHotelsSeeder extends DemoHotelSeeder
{
    public function run(): void
    {
        foreach ($this->hotelDefinitions() as $definition) {
            $hotel = Hotel::updateOrCreate(['slug' => $definition['hotel']['slug']], $definition['hotel']);

            $this->seedStaff($hotel, $definition['staff']);
            $this->seedRoomTypes($hotel, $definition['room_types']);
            $this->seedKnowledge($hotel, $definition['knowledge']);
            $this->seedMembers($hotel, $definition['members']);
        }

        $this->command?->info('Demo logins (password: password): owner@cakrawala.test, owner@omahkasturi.test, owner@kamogawa.test');
    }

    /**
     * @param  list<array{name: string, email: string, role: string}>  $staff
     */
    private function seedStaff(Hotel $hotel, array $staff): void
    {
        foreach ($staff as $person) {
            $user = User::firstOrCreate(
                ['email' => $person['email']],
                ['name' => $person['name'], 'password' => Hash::make('password')]
            );

            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $hotel->users()->syncWithoutDetaching([$user->id => ['role' => $person['role'], 'status' => 'active']]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function seedRoomTypes(Hotel $hotel, array $definitions): void
    {
        $sort = 0;

        foreach ($definitions as $definition) {
            $images = $definition['images'];
            $totalUnits = $definition['total_units'];
            unset($definition['images'], $definition['total_units']);

            $roomType = $hotel->roomTypes()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'sort_order' => $sort++, 'is_active' => true]
            );

            $imageSort = 0;
            foreach ($images as $image) {
                $roomType->images()->updateOrCreate(
                    ['image_url' => $image['url']],
                    ['tags' => $image['tags'], 'alt_text' => $image['alt'], 'sort_order' => $imageSort++]
                );
            }

            $this->seedInventory($roomType, $totalUnits);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function seedKnowledge(Hotel $hotel, array $items): void
    {
        $sort = 0;

        foreach ($items as $item) {
            $hotel->knowledgeItems()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'category' => $item['category'],
                    'body' => $item['body'],
                    'translations' => $item['translations'],
                    'tags' => $item['tags'],
                    'image_url' => $item['image_url'] ?? null,
                    'details' => $item['details'] ?? null,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );
        }
    }

    /**
     * Members and their stays. Points are never written by hand: finished stays go through
     * the same BookingPoints job the nightly schedule runs, so balances and tiers are the real thing.
     *
     * @param  list<array<string, mixed>>  $members
     */
    private function seedMembers(Hotel $hotel, array $members): void
    {
        $program = $hotel->loyaltyProgram();
        $roomTypes = $hotel->roomTypes()->get()->keyBy('slug');
        $cancelledAfterStay = [];

        foreach ($members as $definition) {
            $member = $hotel->members()->updateOrCreate(
                ['email' => $definition['email']],
                ['name' => $definition['name'], 'phone' => $definition['phone'] ?? null, 'locale' => $definition['locale']]
            );

            $member->forceFill([
                'email_verified_at' => now(),
                'last_login_at' => now()->subDays($definition['last_login_days_ago'] ?? 2),
            ])->save();

            $stays = collect($definition['stays'])
                ->map(fn (array $stay, int $index) => [...$stay, 'index' => $index, 'check_in' => $this->checkIn($stay)])
                ->sortBy('check_in');

            $running = 0;

            foreach ($stays as $stay) {
                $roomType = $roomTypes[$stay['room']];
                $rooms = $stay['rooms'] ?? 1;
                $subtotal = (float) $roomType->base_price * $stay['nights'] * $rooms;
                $discountPercent = $program?->discountFor($running) ?? 0.0;
                $discountAmount = round($subtotal * $discountPercent / 100, 2);
                $total = $subtotal - $discountAmount;
                $checkIn = $stay['check_in'];
                $status = $this->statusFor($stay);

                $booking = Booking::updateOrCreate(
                    ['reference' => 'BK-'.strtoupper(substr(md5($hotel->slug.'|'.$member->email.'|'.$stay['index']), 0, 6))],
                    [
                        'hotel_id' => $hotel->id,
                        'room_type_id' => $roomType->id,
                        'member_id' => $member->id,
                        'guest_name' => $member->displayName(),
                        'guest_email' => $member->email,
                        'guest_phone' => $member->phone,
                        'contact_type' => 'email',
                        'check_in' => $checkIn,
                        'check_out' => $checkIn->copy()->addDays($stay['nights']),
                        'adults' => $stay['adults'] ?? 2,
                        'children' => $stay['children'] ?? 0,
                        'room_count' => $rooms,
                        'extra_bed' => false,
                        'total_price' => $total,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount,
                        'status' => $status,
                        'notes' => $stay['notes'] ?? null,
                    ]
                );

                if ($status === Booking::STATUS_COMPLETED) {
                    $running += $program?->pointsFor($total) ?? 0;
                }

                if ($stay['cancelled_later'] ?? false) {
                    $cancelledAfterStay[] = $booking;
                }
            }

            if ($program && isset($definition['bonus'])) {
                $this->giveBonus($member, $definition['bonus']);
            }
        }

        if (! $program) {
            return;
        }

        $points = app(BookingPoints::class);
        $points->settle($hotel);

        foreach ($cancelledAfterStay as $booking) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
        }

        $points->settle($hotel);
    }

    /**
     * A stay that ended before today, or one that starts in the future.
     *
     * @param  array<string, mixed>  $stay
     */
    private function checkIn(array $stay): Carbon
    {
        return isset($stay['ended_days_ago'])
            ? now()->startOfDay()->subDays($stay['ended_days_ago'] + $stay['nights'])
            : now()->startOfDay()->addDays($stay['starts_in_days']);
    }

    /**
     * A stay that cancels after it was paid out is first booked as completed,
     * so the points exist before they are taken back.
     *
     * @param  array<string, mixed>  $stay
     */
    private function statusFor(array $stay): string
    {
        if (isset($stay['ended_days_ago'])) {
            return Booking::STATUS_COMPLETED;
        }

        return $stay['status'] ?? Booking::STATUS_CONFIRMED;
    }

    /**
     * @param  array{points: int, note: string}  $bonus
     */
    private function giveBonus(Member $member, array $bonus): void
    {
        $alreadyGiven = $member->points()->where('type', MemberPoint::TYPE_ADJUST)->where('note', $bonus['note'])->exists();

        if (! $alreadyGiven) {
            app(PointsLedger::class)->adjust($member, $bonus['points'], $bonus['note'], 'Front desk');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hotelDefinitions(): array
    {
        return [
            $this->cakrawala(),
            $this->omahKasturi(),
            $this->kamogawa(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cakrawala(): array
    {
        $pool = 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=800&q=80';

        return [
            'hotel' => [
                'name' => 'Cakrawala Business Hotel',
                'slug' => 'cakrawala-business-hotel',
                'description' => 'Hotel bisnis di kawasan Sudirman, Jakarta, dengan ruang rapat, Wi-Fi cepat, dan akses langsung ke stasiun MRT.',
                'translations' => [
                    'id' => ['description' => 'Hotel bisnis di kawasan Sudirman, Jakarta, dengan ruang rapat, Wi-Fi cepat, dan akses langsung ke stasiun MRT.'],
                    'en' => ['description' => 'A business hotel in Jakarta\'s Sudirman district with meeting rooms, fast Wi-Fi, and direct access to the MRT station.'],
                ],
                'address' => 'Jl. Jenderal Sudirman Kav. 52, Tanah Abang',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'latitude' => -6.2297,
                'longitude' => 106.8081,
                'phone' => '+62 21 5551234',
                'whatsapp' => '6281211223344',
                'email' => 'reservation@cakrawala.test',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'default_locale' => 'id',
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'cover_path' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1920&q=80',
                'public_status' => 'published',
                'features' => [Hotel::FEATURE_MEMBER, Hotel::FEATURE_MEMBER_PLUS],
                'settings' => [
                    'loyalty' => [
                        'spend_per_point' => 10000,
                        'tiers' => [
                            ['name' => 'Silver', 'min_points' => 250, 'discount_percent' => 5],
                            ['name' => 'Gold', 'min_points' => 1000, 'discount_percent' => 10],
                            ['name' => 'Platinum', 'min_points' => 2500, 'discount_percent' => 15],
                        ],
                    ],
                ],
            ],
            'staff' => [
                ['name' => 'Hendra Kusuma', 'email' => 'owner@cakrawala.test', 'role' => HotelUser::ROLE_OWNER],
                ['name' => 'Dewi Anggraini', 'email' => 'frontdesk@cakrawala.test', 'role' => HotelUser::ROLE_FRONT_DESK],
            ],
            'room_types' => [
                [
                    'slug' => 'superior-city-view',
                    'name' => 'Superior City View',
                    'description' => 'Kamar efisien dengan meja kerja dan pemandangan gedung-gedung Sudirman.',
                    'translations' => [
                        'id' => ['name' => 'Superior City View', 'description' => 'Kamar efisien dengan meja kerja dan pemandangan gedung-gedung Sudirman.'],
                        'en' => ['name' => 'Superior City View', 'description' => 'An efficient room with a work desk and a view of the Sudirman skyline.'],
                    ],
                    'size_sqm' => 28,
                    'max_adults' => 2,
                    'max_children' => 1,
                    'bed_config' => [['type' => 'king', 'count' => 1]],
                    'view_type' => 'city',
                    'breakfast_included' => true,
                    'extra_bed_available' => true,
                    'extra_bed_price' => 300000,
                    'base_price' => 950000,
                    'amenities' => ['air_conditioning', 'wifi', 'minibar', 'safe_deposit_box', 'coffee_maker'],
                    'total_units' => 24,
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom', 'view'], 'alt' => 'Kamar Superior City View dengan pemandangan kota'],
                        ['url' => 'https://images.unsplash.com/photo-1540961403310-79825242906e?auto=format&fit=crop&w=1200&q=80', 'tags' => ['minibar'], 'alt' => 'Minibar Superior City View'],
                    ],
                ],
                [
                    'slug' => 'executive-king',
                    'name' => 'Executive King',
                    'description' => 'Kamar lebih luas dengan akses executive lounge, sarapan, dan happy hour sore hari.',
                    'translations' => [
                        'id' => ['name' => 'Executive King', 'description' => 'Kamar lebih luas dengan akses executive lounge, sarapan, dan happy hour sore hari.'],
                        'en' => ['name' => 'Executive King', 'description' => 'A larger room with executive lounge access, breakfast, and evening happy hour.'],
                    ],
                    'size_sqm' => 36,
                    'max_adults' => 2,
                    'max_children' => 1,
                    'bed_config' => [['type' => 'king', 'count' => 1]],
                    'view_type' => 'city',
                    'breakfast_included' => true,
                    'extra_bed_available' => true,
                    'extra_bed_price' => 350000,
                    'base_price' => 1450000,
                    'amenities' => ['air_conditioning', 'wifi', 'minibar', 'safe_deposit_box', 'bathtub', 'coffee_maker'],
                    'total_units' => 14,
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => 'Tempat tidur king Executive King'],
                        ['url' => 'https://images.unsplash.com/photo-1759264244749-a45833759810?auto=format&fit=crop&w=1200&q=80', 'tags' => ['coffee_maker'], 'alt' => 'Mesin kopi dan teh Executive King'],
                    ],
                ],
                [
                    'slug' => 'executive-suite',
                    'name' => 'Executive Suite',
                    'description' => 'Suite dengan ruang tamu terpisah, cocok untuk tamu yang menggabungkan kerja dan istirahat atau menginap bersama keluarga.',
                    'translations' => [
                        'id' => ['name' => 'Executive Suite', 'description' => 'Suite dengan ruang tamu terpisah, cocok untuk tamu yang menggabungkan kerja dan istirahat atau menginap bersama keluarga.'],
                        'en' => ['name' => 'Executive Suite', 'description' => 'A suite with a separate living room, suited to guests who mix work and rest or travel with family.'],
                    ],
                    'size_sqm' => 58,
                    'max_adults' => 3,
                    'max_children' => 1,
                    'bed_config' => [['type' => 'king', 'count' => 1], ['type' => 'single', 'count' => 1]],
                    'view_type' => 'city',
                    'breakfast_included' => true,
                    'extra_bed_available' => true,
                    'extra_bed_price' => 400000,
                    'base_price' => 2600000,
                    'amenities' => ['air_conditioning', 'wifi', 'minibar', 'safe_deposit_box', 'bathtub', 'living_room', 'coffee_maker'],
                    'total_units' => 4,
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => 'Kamar tidur Executive Suite'],
                        ['url' => 'https://images.unsplash.com/photo-1762341123870-d706f257a12e?auto=format&fit=crop&w=1200&q=80', 'tags' => ['air_conditioning'], 'alt' => 'AC Executive Suite'],
                    ],
                ],
            ],
            'knowledge' => [
                [
                    'category' => HotelKnowledgeItem::CATEGORY_GENERAL,
                    'title' => 'Tentang Cakrawala Business Hotel',
                    'body' => 'Cakrawala Business Hotel berada di Jl. Jenderal Sudirman, 3 menit berjalan kaki dari stasiun MRT. Hotel memiliki 42 kamar, 5 ruang rapat, dan restoran yang buka dari sarapan hingga makan malam.',
                    'translations' => [
                        'en' => ['title' => 'About Cakrawala Business Hotel', 'body' => 'Cakrawala Business Hotel is on Jl. Jenderal Sudirman, a 3-minute walk from the MRT station. It has 42 rooms, 5 meeting rooms, and a restaurant open from breakfast until dinner.'],
                    ],
                    'tags' => ['overview', 'introduction', 'mrt'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                    'title' => 'Jam check-in dan check-out',
                    'body' => 'Check-in mulai pukul 14:00 dan check-out pukul 12:00. Early check-in mulai 10:00 dikenakan biaya setengah malam bila kamar tersedia. Late check-out hingga 15:00 bisa diajukan ke resepsionis.',
                    'translations' => [
                        'en' => ['title' => 'Check-in and check-out times', 'body' => 'Check-in is from 2:00 PM and check-out is at 12:00 PM. Early check-in from 10:00 AM is charged half a night when a room is ready. Late check-out until 3:00 PM can be requested at the front desk.'],
                    ],
                    'tags' => ['check-in', 'check-out', 'early check-in', 'late check-out'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_FACILITIES,
                    'title' => 'Ruang rapat dan business center',
                    'image_url' => $pool,
                    'body' => 'Tersedia 5 ruang rapat untuk 10–120 orang lengkap dengan proyektor, layar, dan Wi-Fi khusus. Business center di lobi menyediakan printer dan pemindai, buka 24 jam bagi tamu menginap.',
                    'translations' => [
                        'en' => ['title' => 'Meeting rooms and business center', 'body' => 'Five meeting rooms seat 10 to 120 people with a projector, screen, and dedicated Wi-Fi. The lobby business center has a printer and scanner, open 24 hours for in-house guests.'],
                    ],
                    'details' => ['hours' => '24 jam', 'menu' => []],
                    'tags' => ['meeting room', 'business center', 'printer', 'wifi'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_DINING,
                    'title' => 'Sarapan dan Restoran Cakra',
                    'body' => 'Sarapan prasmanan 06:00–10:00 di Restoran Cakra, termasuk pada semua tipe kamar. Restoran tetap buka hingga 22:00 dengan menu a la carte, dan room service tersedia sampai 23:00.',
                    'translations' => [
                        'en' => ['title' => 'Breakfast and Cakra Restaurant', 'body' => 'Buffet breakfast is served 6:00–10:00 AM at Cakra Restaurant and is included with every room type. The restaurant stays open until 10:00 PM with an a la carte menu, and room service runs until 11:00 PM.'],
                    ],
                    'details' => [
                        'hours' => '06:00–22:00',
                        'menu' => [
                            ['name' => 'Soto Betawi', 'description' => 'Soto daging sapi berkuah santan dengan emping dan acar', 'price' => null],
                            ['name' => 'Nasi goreng Cakra', 'description' => 'Nasi goreng dengan sate ayam dan telur mata sapi', 'price' => null],
                            ['name' => 'Club sandwich', 'description' => 'Ayam panggang, bacon, dan kentang goreng', 'price' => null],
                        ],
                    ],
                    'tags' => ['breakfast', 'restaurant', 'room service'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                    'title' => 'Kebijakan pembatalan',
                    'body' => 'Pembatalan gratis hingga 24 jam sebelum check-in. Pembatalan setelahnya dikenakan biaya satu malam pertama. No-show dikenakan biaya satu malam.',
                    'translations' => [
                        'en' => ['title' => 'Cancellation policy', 'body' => 'Free cancellation up to 24 hours before check-in. Later cancellations are charged the first night. A no-show is charged one night.'],
                    ],
                    'tags' => ['cancellation', 'refund', 'no-show'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_TRANSPORT,
                    'title' => 'Dari bandara dan stasiun',
                    'body' => 'Dari Bandara Soekarno-Hatta naik kereta bandara ke Stasiun BNI City lalu MRT, atau taksi sekitar 60 menit tergantung lalu lintas. Antar-jemput bandara tersedia dengan biaya tambahan, pesan 24 jam sebelumnya.',
                    'translations' => [
                        'en' => ['title' => 'From the airport and stations', 'body' => 'From Soekarno-Hatta Airport take the airport train to BNI City Station and then the MRT, or a taxi of about 60 minutes depending on traffic. An airport transfer is available for an extra fee; book 24 hours ahead.'],
                    ],
                    'tags' => ['airport', 'transfer', 'mrt', 'train'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_FAQ,
                    'title' => 'Apakah ada program member?',
                    'body' => 'Ya. Daftar member gratis dengan email, kumpulkan 1 poin untuk setiap Rp10.000 dari menginap, dan naik tier Silver, Gold, hingga Platinum untuk mendapat diskon kamar 5–15%.',
                    'translations' => [
                        'en' => ['title' => 'Is there a member program?', 'body' => 'Yes. Join for free with your email, earn 1 point for every Rp10,000 spent on a stay, and move up from Silver to Gold and Platinum for 5–15% off room rates.'],
                    ],
                    'tags' => ['member', 'loyalty', 'points', 'discount'],
                ],
            ],
            'members' => [
                [
                    'name' => 'Rina Wulandari',
                    'email' => 'rina.wulandari@example.test',
                    'phone' => '+62 812 5550 1101',
                    'locale' => 'id',
                    'bonus' => ['points' => 200, 'note' => 'Bonus ulang tahun member'],
                    'stays' => [
                        ['room' => 'superior-city-view', 'nights' => 3, 'ended_days_ago' => 320],
                        ['room' => 'executive-king', 'nights' => 3, 'ended_days_ago' => 250],
                        ['room' => 'executive-king', 'nights' => 4, 'ended_days_ago' => 190],
                        ['room' => 'executive-king', 'nights' => 3, 'ended_days_ago' => 120],
                        ['room' => 'executive-suite', 'nights' => 3, 'ended_days_ago' => 40, 'adults' => 3],
                        ['room' => 'executive-king', 'nights' => 2, 'starts_in_days' => 21],
                    ],
                ],
                [
                    'name' => 'Budi Santoso',
                    'email' => 'budi.santoso@example.test',
                    'phone' => '+62 813 5550 2202',
                    'locale' => 'id',
                    'stays' => [
                        ['room' => 'superior-city-view', 'nights' => 3, 'ended_days_ago' => 280],
                        ['room' => 'executive-king', 'nights' => 3, 'ended_days_ago' => 200],
                        ['room' => 'executive-king', 'nights' => 3, 'ended_days_ago' => 140],
                        ['room' => 'superior-city-view', 'nights' => 4, 'ended_days_ago' => 80],
                        ['room' => 'executive-king', 'nights' => 2, 'ended_days_ago' => 25],
                    ],
                ],
                [
                    'name' => 'Maya Pratiwi',
                    'email' => 'maya.pratiwi@example.test',
                    'phone' => '+62 857 5550 3303',
                    'locale' => 'id',
                    'stays' => [
                        ['room' => 'superior-city-view', 'nights' => 3, 'ended_days_ago' => 150],
                        ['room' => 'superior-city-view', 'nights' => 2, 'ended_days_ago' => 90],
                        ['room' => 'superior-city-view', 'nights' => 2, 'ended_days_ago' => 30],
                        ['room' => 'superior-city-view', 'nights' => 2, 'starts_in_days' => 10, 'status' => Booking::STATUS_PENDING],
                    ],
                ],
                [
                    'name' => 'Sinta Dewi',
                    'email' => 'sinta.dewi@example.test',
                    'phone' => null,
                    'locale' => 'id',
                    'stays' => [
                        ['room' => 'executive-king', 'nights' => 2, 'ended_days_ago' => 60, 'cancelled_later' => true],
                        ['room' => 'superior-city-view', 'nights' => 2, 'ended_days_ago' => 20],
                        ['room' => 'executive-king', 'nights' => 2, 'starts_in_days' => 30, 'status' => Booking::STATUS_CANCELLED],
                    ],
                ],
                [
                    'name' => null,
                    'email' => 'ahmad.fauzi@example.test',
                    'phone' => null,
                    'locale' => 'id',
                    'last_login_days_ago' => 0,
                    'stays' => [
                        ['room' => 'superior-city-view', 'nights' => 1, 'starts_in_days' => 5, 'status' => Booking::STATUS_PENDING, 'adults' => 1],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function omahKasturi(): array
    {
        $pool = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80';

        return [
            'hotel' => [
                'name' => 'Omah Kasturi Boutique Hotel',
                'slug' => 'omah-kasturi',
                'description' => 'Hotel butik bergaya joglo di kawasan Prawirotaman, Yogyakarta, dengan pendopo, kolam kecil, dan sarapan masakan Jawa.',
                'translations' => [
                    'id' => ['description' => 'Hotel butik bergaya joglo di kawasan Prawirotaman, Yogyakarta, dengan pendopo, kolam kecil, dan sarapan masakan Jawa.'],
                    'en' => ['description' => 'A joglo-style boutique hotel in Yogyakarta\'s Prawirotaman area with a pendopo pavilion, a small pool, and a Javanese breakfast.'],
                ],
                'address' => 'Jl. Prawirotaman II No. 15, Brontokusuman',
                'city' => 'Yogyakarta',
                'country' => 'Indonesia',
                'latitude' => -7.8186,
                'longitude' => 110.3692,
                'phone' => '+62 274 555123',
                'whatsapp' => '6281399887766',
                'email' => 'halo@omahkasturi.test',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'default_locale' => 'id',
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'cover_path' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1920&q=80',
                'public_status' => 'published',
                'features' => [Hotel::FEATURE_MEMBER],
                'settings' => null,
            ],
            'staff' => [
                ['name' => 'Laksmi Hapsari', 'email' => 'owner@omahkasturi.test', 'role' => HotelUser::ROLE_OWNER],
                ['name' => 'Yoga Prasetya', 'email' => 'frontdesk@omahkasturi.test', 'role' => HotelUser::ROLE_FRONT_DESK],
            ],
            'room_types' => [
                [
                    'slug' => 'kamar-taman',
                    'name' => 'Kamar Taman',
                    'description' => 'Kamar sederhana dan sejuk menghadap taman dalam, dengan ranjang kayu jati.',
                    'translations' => [
                        'id' => ['name' => 'Kamar Taman', 'description' => 'Kamar sederhana dan sejuk menghadap taman dalam, dengan ranjang kayu jati.'],
                        'en' => ['name' => 'Garden Room', 'description' => 'A simple, cool room facing the inner garden, with a teak bed.'],
                    ],
                    'size_sqm' => 24,
                    'max_adults' => 2,
                    'max_children' => 1,
                    'bed_config' => [['type' => 'queen', 'count' => 1]],
                    'view_type' => 'garden',
                    'breakfast_included' => true,
                    'extra_bed_available' => true,
                    'extra_bed_price' => 150000,
                    'base_price' => 650000,
                    'amenities' => ['air_conditioning', 'wifi', 'safe_deposit_box', 'coffee_maker'],
                    'total_units' => 8,
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => 'Kamar tidur Kamar Taman'],
                        ['url' => 'https://images.unsplash.com/photo-1759264244749-a45833759810?auto=format&fit=crop&w=1200&q=80', 'tags' => ['coffee_maker'], 'alt' => 'Kopi dan teh di Kamar Taman'],
                    ],
                ],
                [
                    'slug' => 'joglo-suite',
                    'name' => 'Joglo Suite',
                    'description' => 'Suite di bawah atap joglo dengan ruang duduk, bak mandi, dan teras pribadi menghadap kolam.',
                    'translations' => [
                        'id' => ['name' => 'Joglo Suite', 'description' => 'Suite di bawah atap joglo dengan ruang duduk, bak mandi, dan teras pribadi menghadap kolam.'],
                        'en' => ['name' => 'Joglo Suite', 'description' => 'A suite under a joglo roof with a sitting area, bathtub, and a private terrace facing the pool.'],
                    ],
                    'size_sqm' => 48,
                    'max_adults' => 2,
                    'max_children' => 1,
                    'bed_config' => [['type' => 'king', 'count' => 1]],
                    'view_type' => 'pool',
                    'breakfast_included' => true,
                    'extra_bed_available' => true,
                    'extra_bed_price' => 200000,
                    'base_price' => 1350000,
                    'amenities' => ['air_conditioning', 'wifi', 'minibar', 'safe_deposit_box', 'bathtub', 'balcony', 'living_room', 'coffee_maker'],
                    'total_units' => 3,
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom', 'living_room'], 'alt' => 'Kamar tidur dan ruang duduk Joglo Suite'],
                        ['url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80', 'tags' => ['balcony', 'view'], 'alt' => 'Teras Joglo Suite menghadap kolam'],
                    ],
                ],
                [
                    'slug' => 'pendopo-family-room',
                    'name' => 'Pendopo Family Room',
                    'description' => 'Kamar keluarga di dekat pendopo, dengan satu ranjang queen dan dua ranjang single.',
                    'translations' => [
                        'id' => ['name' => 'Pendopo Family Room', 'description' => 'Kamar keluarga di dekat pendopo, dengan satu ranjang queen dan dua ranjang single.'],
                        'en' => ['name' => 'Pendopo Family Room', 'description' => 'A family room next to the pendopo pavilion, with one queen bed and two single beds.'],
                    ],
                    'size_sqm' => 40,
                    'max_adults' => 2,
                    'max_children' => 2,
                    'bed_config' => [['type' => 'queen', 'count' => 1], ['type' => 'single', 'count' => 2]],
                    'view_type' => 'garden',
                    'breakfast_included' => true,
                    'extra_bed_available' => false,
                    'extra_bed_price' => null,
                    'base_price' => 1050000,
                    'amenities' => ['air_conditioning', 'wifi', 'safe_deposit_box', 'coffee_maker'],
                    'total_units' => 4,
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => 'Kamar tidur Pendopo Family Room'],
                    ],
                ],
            ],
            'knowledge' => [
                [
                    'category' => HotelKnowledgeItem::CATEGORY_GENERAL,
                    'title' => 'Tentang Omah Kasturi',
                    'body' => 'Omah Kasturi adalah hotel butik dengan 15 kamar di Prawirotaman, kawasan yang dikenal dengan kafe dan restoran. Bangunan utama berupa joglo kayu jati dengan pendopo terbuka untuk bersantai.',
                    'translations' => [
                        'en' => ['title' => 'About Omah Kasturi', 'body' => 'Omah Kasturi is a 15-room boutique hotel in Prawirotaman, an area known for cafes and restaurants. The main building is a teak joglo with an open pendopo for relaxing.'],
                    ],
                    'tags' => ['overview', 'introduction', 'prawirotaman'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_FACILITIES,
                    'title' => 'Kolam renang dan pendopo',
                    'image_url' => $pool,
                    'body' => 'Kolam renang buka 07:00–18:00. Pendopo terbuka untuk tamu sepanjang hari, dan setiap Sabtu sore ada pertunjukan gamelan singkat.',
                    'translations' => [
                        'en' => ['title' => 'Pool and pendopo', 'body' => 'The pool is open 7:00 AM to 6:00 PM. The pendopo is open to guests all day, and there is a short gamelan performance every Saturday afternoon.'],
                    ],
                    'details' => ['hours' => '07:00–18:00', 'menu' => []],
                    'tags' => ['pool', 'pendopo', 'gamelan'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_DINING,
                    'title' => 'Sarapan masakan Jawa',
                    'body' => 'Sarapan disajikan di pendopo pukul 07:00–10:00: pilihan gudeg, nasi liwet, atau menu barat. Sarapan sudah termasuk di semua tipe kamar.',
                    'translations' => [
                        'en' => ['title' => 'Javanese breakfast', 'body' => 'Breakfast is served in the pendopo from 7:00 to 10:00 AM: choose gudeg, nasi liwet, or a western plate. Breakfast is included with every room type.'],
                    ],
                    'details' => [
                        'hours' => '07:00–10:00',
                        'menu' => [
                            ['name' => 'Gudeg komplit', 'description' => 'Gudeg nangka, ayam, telur pindang, dan krecek', 'price' => null],
                            ['name' => 'Nasi liwet', 'description' => 'Nasi gurih dengan suwiran ayam dan sambal', 'price' => null],
                        ],
                    ],
                    'tags' => ['breakfast', 'gudeg', 'javanese'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                    'title' => 'Kebijakan pembatalan',
                    'body' => 'Pembatalan gratis hingga 2 hari sebelum check-in. Pembatalan setelahnya dikenakan biaya satu malam pertama.',
                    'translations' => [
                        'en' => ['title' => 'Cancellation policy', 'body' => 'Free cancellation up to 2 days before check-in. Later cancellations are charged the first night.'],
                    ],
                    'tags' => ['cancellation', 'refund'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_TRANSPORT,
                    'title' => 'Menuju Keraton dan Malioboro',
                    'body' => 'Keraton Yogyakarta berjarak sekitar 10 menit berkendara, dan Malioboro sekitar 15 menit. Kami dapat mengatur becak, taksi, atau mobil sewa dengan sopir. Bandara YIA berjarak sekitar 75 menit.',
                    'translations' => [
                        'en' => ['title' => 'Getting to the Kraton and Malioboro', 'body' => 'The Yogyakarta Kraton is about a 10-minute drive and Malioboro about 15 minutes. We can arrange a becak, taxi, or car with a driver. YIA Airport is around 75 minutes away.'],
                    ],
                    'tags' => ['keraton', 'malioboro', 'airport', 'transport'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_FAQ,
                    'title' => 'Apakah ada program member?',
                    'body' => 'Ya. Anda dapat masuk dengan email untuk melihat dan mengubah reservasi dengan cepat tanpa mengisi data berulang. Program poin belum tersedia.',
                    'translations' => [
                        'en' => ['title' => 'Is there a member program?', 'body' => 'Yes. Sign in with your email to see and change your reservations quickly without re-entering your details. A points program is not available yet.'],
                    ],
                    'tags' => ['member', 'account'],
                ],
            ],
            'members' => [
                [
                    'name' => 'Dimas Aryo',
                    'email' => 'dimas.aryo@example.test',
                    'phone' => '+62 811 5550 4404',
                    'locale' => 'id',
                    'stays' => [
                        ['room' => 'kamar-taman', 'nights' => 2, 'ended_days_ago' => 70],
                        ['room' => 'joglo-suite', 'nights' => 2, 'ended_days_ago' => 15],
                        ['room' => 'kamar-taman', 'nights' => 3, 'starts_in_days' => 40],
                    ],
                ],
                [
                    'name' => 'Laras Setiawati',
                    'email' => 'laras.setiawati@example.test',
                    'phone' => '+62 878 5550 5505',
                    'locale' => 'id',
                    'stays' => [
                        ['room' => 'pendopo-family-room', 'nights' => 3, 'starts_in_days' => 14, 'adults' => 2, 'children' => 2],
                    ],
                ],
                [
                    'name' => 'Thomas Weber',
                    'email' => 'thomas.weber@example.test',
                    'phone' => null,
                    'locale' => 'en',
                    'stays' => [
                        ['room' => 'joglo-suite', 'nights' => 4, 'ended_days_ago' => 110],
                        ['room' => 'kamar-taman', 'nights' => 2, 'starts_in_days' => 6, 'status' => Booking::STATUS_PENDING],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kamogawa(): array
    {
        return [
            'hotel' => [
                'name' => 'Kamogawa Machiya Stay',
                'slug' => 'kamogawa-machiya-stay',
                'description' => '鴨川のほとりにある町家を改装した小さな宿。畳の客室と坪庭で、京都の暮らしを体験できます。',
                'translations' => [
                    'ja' => ['description' => '鴨川のほとりにある町家を改装した小さな宿。畳の客室と坪庭で、京都の暮らしを体験できます。'],
                    'en' => ['description' => 'A small inn in a renovated machiya townhouse by the Kamo River, with tatami rooms and a courtyard garden.'],
                ],
                'address' => '京都市東山区 祇園町南側',
                'city' => 'Kyoto',
                'country' => 'Japan',
                'latitude' => 35.0036,
                'longitude' => 135.7753,
                'phone' => '+81 75 555 0123',
                'whatsapp' => null,
                'email' => 'stay@kamogawa.test',
                'timezone' => 'Asia/Tokyo',
                'currency' => 'JPY',
                'default_locale' => 'ja',
                'check_in_time' => '15:00',
                'check_out_time' => '10:00',
                'cover_path' => 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=1920&q=80',
                'public_status' => 'published',
                'features' => [Hotel::FEATURE_MEMBER, Hotel::FEATURE_MEMBER_PLUS],
                'settings' => null,
            ],
            'staff' => [
                ['name' => 'Haruka Mori', 'email' => 'owner@kamogawa.test', 'role' => HotelUser::ROLE_OWNER],
                ['name' => 'Ren Ito', 'email' => 'frontdesk@kamogawa.test', 'role' => HotelUser::ROLE_FRONT_DESK],
            ],
            'room_types' => [
                [
                    'slug' => 'machiya-standard-twin',
                    'name' => 'Machiya Standard Twin',
                    'description' => '畳の上にベッドを置いた、コンパクトで落ち着くツインルーム。',
                    'translations' => [
                        'ja' => ['name' => '町家スタンダードツイン', 'description' => '畳の上にベッドを置いた、コンパクトで落ち着くツインルーム。'],
                        'en' => ['name' => 'Machiya Standard Twin', 'description' => 'A compact, calm twin room with beds set on tatami.'],
                    ],
                    'size_sqm' => 22,
                    'max_adults' => 2,
                    'max_children' => 0,
                    'bed_config' => [['type' => 'twin', 'count' => 2]],
                    'view_type' => 'garden',
                    'breakfast_included' => false,
                    'extra_bed_available' => false,
                    'extra_bed_price' => null,
                    'base_price' => 24000,
                    'amenities' => ['air_conditioning', 'wifi', 'safe_deposit_box'],
                    'total_units' => 5,
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => '町家スタンダードツインの客室'],
                    ],
                ],
                [
                    'slug' => 'machiya-garden-suite',
                    'name' => 'Machiya Garden Suite',
                    'description' => '坪庭に面した広めの客室。檜の浴槽と朝食付き。',
                    'translations' => [
                        'ja' => ['name' => '町家ガーデンスイート', 'description' => '坪庭に面した広めの客室。檜の浴槽と朝食付き。'],
                        'en' => ['name' => 'Machiya Garden Suite', 'description' => 'A larger room facing the courtyard garden, with a cypress bath and breakfast included.'],
                    ],
                    'size_sqm' => 38,
                    'max_adults' => 3,
                    'max_children' => 1,
                    'bed_config' => [['type' => 'double', 'count' => 1], ['type' => 'single', 'count' => 1]],
                    'view_type' => 'garden',
                    'breakfast_included' => true,
                    'extra_bed_available' => true,
                    'extra_bed_price' => 6000,
                    'base_price' => 42000,
                    'amenities' => ['air_conditioning', 'wifi', 'safe_deposit_box', 'bathtub', 'coffee_maker'],
                    'total_units' => 3,
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => '町家ガーデンスイートの客室'],
                    ],
                ],
            ],
            'knowledge' => [
                [
                    'category' => HotelKnowledgeItem::CATEGORY_GENERAL,
                    'title' => 'カモガワ町家ステイについて',
                    'body' => '築100年の町家を改装した8室の小さな宿です。祇園から徒歩5分、鴨川まで徒歩2分。畳の客室と坪庭で、静かな京都の時間をお過ごしいただけます。',
                    'translations' => [
                        'en' => ['title' => 'About Kamogawa Machiya Stay', 'body' => 'A small inn of 8 rooms in a renovated 100-year-old machiya. Gion is a 5-minute walk and the Kamo River 2 minutes, with tatami rooms and a courtyard garden for a quiet stay in Kyoto.'],
                    ],
                    'tags' => ['overview', 'machiya', 'gion'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                    'title' => 'チェックイン・チェックアウト',
                    'body' => 'チェックインは15:00から20:00まで、チェックアウトは10:00です。20時以降に到着される場合は事前にご連絡ください。フロントは常駐していないため、遅い到着の際は暗証番号でご案内します。',
                    'translations' => [
                        'en' => ['title' => 'Check-in and check-out', 'body' => 'Check-in is from 3:00 PM to 8:00 PM and check-out is at 10:00 AM. Please tell us in advance if you arrive after 8 PM. The desk is not staffed late, so we guide late arrivals with a door code.'],
                    ],
                    'tags' => ['check-in', 'check-out', 'late arrival'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_DINING,
                    'title' => '朝食',
                    'body' => '朝食は8:00〜9:30に1階の座敷でご提供します。ガーデンスイートは朝食付き、スタンダードツインは1,800円で追加できます。',
                    'translations' => [
                        'en' => ['title' => 'Breakfast', 'body' => 'Breakfast is served from 8:00 to 9:30 AM in the ground-floor tatami room. It is included with the Garden Suite and can be added to the Standard Twin for ¥1,800.'],
                    ],
                    'details' => [
                        'hours' => '08:00–09:30',
                        'menu' => [
                            ['name' => '和朝食', 'description' => '焼き魚、だし巻き卵、京漬物、味噌汁、ご飯', 'price' => null],
                        ],
                    ],
                    'tags' => ['breakfast', 'japanese'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                    'title' => 'キャンセルポリシー',
                    'body' => 'チェックインの7日前まで無料でキャンセルできます。6日前から前日までは宿泊料金の50%、当日および無連絡不泊は100%をいただきます。',
                    'translations' => [
                        'en' => ['title' => 'Cancellation policy', 'body' => 'Free cancellation up to 7 days before check-in. From 6 days to the day before, 50% of the room rate applies; on the day and for no-shows, 100%.'],
                    ],
                    'tags' => ['cancellation', 'refund', 'no-show'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_TRANSPORT,
                    'title' => '近隣の観光スポット',
                    'image_url' => 'https://images.unsplash.com/photo-1528360983277-13d401cdc186?auto=format&fit=crop&w=800&q=80',
                    'body' => '八坂神社と祇園は徒歩5分、清水寺へは徒歩20分です。京都駅からは市バスまたは電車で約20分。',
                    'translations' => [
                        'en' => ['title' => 'Nearby attractions', 'body' => 'Yasaka Shrine and Gion are a 5-minute walk, Kiyomizu-dera is 20 minutes on foot. Kyoto Station is about 20 minutes by city bus or train.'],
                    ],
                    'tags' => ['attractions', 'gion', 'kiyomizu', 'station'],
                ],
                [
                    'category' => HotelKnowledgeItem::CATEGORY_FAQ,
                    'title' => '会員プログラムはありますか？',
                    'body' => 'はい。メールアドレスで無料登録でき、1,000円のご宿泊ごとに1ポイントが貯まります。ポイントが貯まるとシルバー、ゴールドの会員ランクに上がり、宿泊料金が割引になります。',
                    'translations' => [
                        'en' => ['title' => 'Is there a member program?', 'body' => 'Yes. Register for free with your email and earn 1 point for every ¥1,000 spent on a stay. As points build up you reach Silver and Gold, which discount your room rate.'],
                    ],
                    'tags' => ['member', 'points', 'discount'],
                ],
            ],
            'members' => [
                [
                    'name' => 'Emi Tanaka',
                    'email' => 'emi.tanaka@example.test',
                    'phone' => '+81 90 5550 6606',
                    'locale' => 'ja',
                    'stays' => [
                        ['room' => 'machiya-standard-twin', 'nights' => 2, 'ended_days_ago' => 200],
                        ['room' => 'machiya-garden-suite', 'nights' => 3, 'ended_days_ago' => 140, 'adults' => 3],
                        ['room' => 'machiya-garden-suite', 'nights' => 3, 'ended_days_ago' => 80, 'adults' => 3],
                        ['room' => 'machiya-garden-suite', 'nights' => 2, 'ended_days_ago' => 30],
                    ],
                ],
                [
                    'name' => 'Kenji Sato',
                    'email' => 'kenji.sato@example.test',
                    'phone' => '+81 80 5550 7707',
                    'locale' => 'ja',
                    'stays' => [
                        ['room' => 'machiya-garden-suite', 'nights' => 5, 'ended_days_ago' => 360],
                        ['room' => 'machiya-garden-suite', 'nights' => 5, 'ended_days_ago' => 300],
                        ['room' => 'machiya-garden-suite', 'nights' => 5, 'ended_days_ago' => 240],
                        ['room' => 'machiya-garden-suite', 'nights' => 6, 'ended_days_ago' => 180],
                        ['room' => 'machiya-garden-suite', 'nights' => 6, 'ended_days_ago' => 100],
                        ['room' => 'machiya-garden-suite', 'nights' => 4, 'ended_days_ago' => 40],
                        ['room' => 'machiya-garden-suite', 'nights' => 3, 'starts_in_days' => 28],
                    ],
                ],
                [
                    'name' => 'Oliver Grant',
                    'email' => 'oliver.grant@example.test',
                    'phone' => null,
                    'locale' => 'en',
                    'stays' => [
                        ['room' => 'machiya-standard-twin', 'nights' => 3, 'starts_in_days' => 12, 'status' => Booking::STATUS_PENDING],
                    ],
                ],
            ],
        ];
    }
}
