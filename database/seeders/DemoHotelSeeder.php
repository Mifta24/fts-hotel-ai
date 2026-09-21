<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\HotelKnowledgeItem;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoHotelSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@samudrabali.test'],
            [
                'name' => 'Pemilik Samudra Bali Resort',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        Hotel::where('name', 'Samudra Bali Resort')->update(['name' => 'FTS Hotel AI', 'slug' => 'fts-hotel-ai']);

        $hotel = Hotel::firstOrCreate(
            ['name' => 'FTS Hotel AI'],
            [
                'slug' => Hotel::generateUniqueSlug('FTS Hotel AI'),
                'description' => 'Resort tepi pantai di Nusa Dua, Bali, dengan kolam renang infinity dan akses langsung ke pantai.',
                'translations' => [
                    'id' => [
                        'description' => 'Resort tepi pantai di Nusa Dua, Bali, dengan kolam renang infinity dan akses langsung ke pantai.',
                    ],
                    'en' => [
                        'description' => 'A beachfront resort in Nusa Dua, Bali, with an infinity pool and direct beach access.',
                    ],
                    'ja' => [
                        'description' => 'バリ島ヌサドゥアにあるビーチフロントリゾート。インフィニティプールとビーチへの直接アクセスが自慢です。',
                    ],
                ],
                'address' => 'Jl. Pantai Mengiat No. 8, Nusa Dua',
                'city' => 'Bali',
                'country' => 'Indonesia',
                'latitude' => -8.8008,
                'longitude' => 115.2317,
                'phone' => '+62 361 771234',
                'whatsapp' => '6281234567890',
                'email' => 'reservation@samudrabali.test',
                'timezone' => 'Asia/Makassar',
                'currency' => 'IDR',
                'default_locale' => 'id',
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'cover_path' => 'https://images.unsplash.com/photo-1582610116397-edb318620f90?auto=format&fit=crop&w=1920&q=80',
                'public_status' => 'published',
            ]
        );

        $hotel->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        $roomTypes = $this->roomTypeDefinitions();

        $sort = 0;
        foreach ($roomTypes as $definition) {
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
                    [
                        'tags' => $image['tags'],
                        'alt_text' => $image['alt'],
                        'sort_order' => $imageSort++,
                    ]
                );
            }

            $this->seedInventory($roomType, $totalUnits);
        }

        $this->seedKnowledgeBase($hotel);

        $this->command?->info('Demo login: owner@samudrabali.test — password: password');
    }

    private function roomTypeDefinitions(): array
    {
        return [
            [
                'slug' => 'deluxe-garden-view',
                'name' => 'Deluxe Garden View',
                'description' => 'Kamar nyaman dengan pemandangan taman tropis, cocok untuk pasangan atau keluarga kecil.',
                'translations' => [
                    'id' => ['name' => 'Deluxe Garden View', 'description' => 'Kamar nyaman dengan pemandangan taman tropis, cocok untuk pasangan atau keluarga kecil.'],
                    'en' => ['name' => 'Deluxe Garden View', 'description' => 'A comfortable room overlooking the tropical garden, ideal for couples or small families.'],
                    'ja' => ['name' => 'デラックス ガーデンビュー', 'description' => '熱帯庭園を望む快適な客室で、カップルや小さなご家族に最適です。'],
                ],
                'size_sqm' => 32,
                'max_adults' => 2,
                'max_children' => 1,
                'bed_config' => [['type' => 'king', 'count' => 1]],
                'view_type' => 'garden',
                'breakfast_included' => true,
                'extra_bed_available' => true,
                'extra_bed_price' => 250000,
                'base_price' => 950000,
                'amenities' => ['air_conditioning', 'wifi', 'minibar', 'safe_deposit_box', 'balcony', 'coffee_maker'],
                'total_units' => 10,
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => 'Tempat tidur king Deluxe Garden View'],
                    ['url' => 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bathroom'], 'alt' => 'Kamar mandi Deluxe Garden View'],
                    ['url' => 'https://images.unsplash.com/photo-1591088398332-8a7791972843?auto=format&fit=crop&w=1200&q=80', 'tags' => ['view', 'balcony'], 'alt' => 'Pemandangan taman dari balkon'],
                ],
            ],
            [
                'slug' => 'deluxe-ocean-view',
                'name' => 'Deluxe Ocean View',
                'description' => 'Kamar dengan pemandangan laut lepas langsung dari balkon pribadi.',
                'translations' => [
                    'id' => ['name' => 'Deluxe Ocean View', 'description' => 'Kamar dengan pemandangan laut lepas langsung dari balkon pribadi.'],
                    'en' => ['name' => 'Deluxe Ocean View', 'description' => 'A room with an open ocean view straight from your private balcony.'],
                    'ja' => ['name' => 'デラックス オーシャンビュー', 'description' => 'プライベートバルコニーから広がる海の景色をお楽しみいただけます。'],
                ],
                'size_sqm' => 36,
                'max_adults' => 2,
                'max_children' => 1,
                'bed_config' => [['type' => 'king', 'count' => 1]],
                'view_type' => 'ocean',
                'breakfast_included' => true,
                'extra_bed_available' => true,
                'extra_bed_price' => 250000,
                'base_price' => 1450000,
                'amenities' => ['air_conditioning', 'wifi', 'minibar', 'safe_deposit_box', 'bathtub', 'balcony', 'coffee_maker'],
                'total_units' => 8,
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => 'Tempat tidur Deluxe Ocean View'],
                    ['url' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bathroom', 'bathtub'], 'alt' => 'Kamar mandi dengan bathtub'],
                    ['url' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=1200&q=80', 'tags' => ['view', 'balcony'], 'alt' => 'Pemandangan laut dari balkon'],
                ],
            ],
            [
                'slug' => 'family-suite',
                'name' => 'Family Suite',
                'description' => 'Suite luas dengan ruang tamu terpisah, ideal untuk keluarga dengan anak.',
                'translations' => [
                    'id' => ['name' => 'Family Suite', 'description' => 'Suite luas dengan ruang tamu terpisah, ideal untuk keluarga dengan anak.'],
                    'en' => ['name' => 'Family Suite', 'description' => 'A spacious suite with a separate living area, ideal for families with children.'],
                    'ja' => ['name' => 'ファミリースイート', 'description' => '独立したリビングエリアを備えた広々としたスイートで、お子様連れのご家族に最適です。'],
                ],
                'size_sqm' => 55,
                'max_adults' => 2,
                'max_children' => 2,
                'bed_config' => [['type' => 'king', 'count' => 1], ['type' => 'twin', 'count' => 2]],
                'view_type' => 'pool',
                'breakfast_included' => true,
                'extra_bed_available' => true,
                'extra_bed_price' => 300000,
                'base_price' => 2200000,
                'amenities' => ['air_conditioning', 'wifi', 'minibar', 'safe_deposit_box', 'bathtub', 'balcony', 'living_room', 'coffee_maker'],
                'total_units' => 5,
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1590073844006-33379778ae09?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => 'Kamar tidur utama Family Suite'],
                    ['url' => 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&w=1200&q=80', 'tags' => ['living_room'], 'alt' => 'Ruang tamu Family Suite'],
                    ['url' => 'https://images.unsplash.com/photo-1552321554-5fefe8c9ef14?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bathroom', 'bathtub'], 'alt' => 'Kamar mandi Family Suite'],
                ],
            ],
            [
                'slug' => 'honeymoon-pool-villa',
                'name' => 'Honeymoon Pool Villa',
                'description' => 'Vila privat dengan kolam renang pribadi, dirancang khusus untuk pasangan.',
                'translations' => [
                    'id' => ['name' => 'Honeymoon Pool Villa', 'description' => 'Vila privat dengan kolam renang pribadi, dirancang khusus untuk pasangan.'],
                    'en' => ['name' => 'Honeymoon Pool Villa', 'description' => 'A private villa with its own pool, designed exclusively for couples.'],
                    'ja' => ['name' => 'ハネムーン プールヴィラ', 'description' => 'プライベートプール付きのヴィラで、カップル専用に設計されています。'],
                ],
                'size_sqm' => 70,
                'max_adults' => 2,
                'max_children' => 0,
                'bed_config' => [['type' => 'king', 'count' => 1]],
                'view_type' => 'garden',
                'breakfast_included' => true,
                'extra_bed_available' => false,
                'extra_bed_price' => null,
                'base_price' => 3200000,
                'amenities' => ['air_conditioning', 'wifi', 'minibar', 'safe_deposit_box', 'bathtub', 'private_pool', 'coffee_maker'],
                'total_units' => 3,
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=1200&q=80', 'tags' => ['pool', 'view'], 'alt' => 'Kolam renang pribadi Honeymoon Villa'],
                    ['url' => 'https://images.unsplash.com/photo-1615874959474-d609969a20ed?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bedroom'], 'alt' => 'Kamar tidur Honeymoon Villa'],
                    ['url' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=1200&q=80', 'tags' => ['bathroom', 'bathtub'], 'alt' => 'Kamar mandi dengan bathtub Honeymoon Villa'],
                ],
            ],
        ];
    }

    private function seedInventory(RoomType $roomType, int $totalUnits): void
    {
        $start = now()->startOfDay();

        for ($i = 0; $i < 120; $i++) {
            $date = $start->copy()->addDays($i);
            $isWeekend = $date->isFriday() || $date->isSaturday();
            $price = $isWeekend
                ? (float) $roomType->base_price * 1.15
                : (float) $roomType->base_price;

            // Deterministic pseudo-occupancy so the demo shows realistic
            // partial availability instead of every date being wide open.
            $booked = ($i * 7 + $roomType->id * 3) % ($totalUnits + 2);
            $booked = min($booked, $totalUnits);

            RoomInventory::updateOrCreate(
                ['room_type_id' => $roomType->id, 'stay_date' => $date->toDateString()],
                [
                    'total_units' => $totalUnits,
                    'booked_units' => $booked,
                    'price' => round($price, -3),
                ]
            );
        }
    }

    private function seedKnowledgeBase(Hotel $hotel): void
    {
        $items = [
            [
                'category' => HotelKnowledgeItem::CATEGORY_GENERAL,
                'title' => 'Tentang Samudra Bali Resort',
                'body' => 'Samudra Bali Resort adalah resort tepi pantai di Nusa Dua yang menawarkan 26 kamar dan vila, kolam renang infinity menghadap laut, serta akses jalan kaki langsung ke pantai pasir putih.',
                'translations' => [
                    'en' => ['title' => 'About Samudra Bali Resort', 'body' => 'Samudra Bali Resort is a beachfront resort in Nusa Dua with 26 rooms and villas, an ocean-facing infinity pool, and direct walking access to the white-sand beach.'],
                    'ja' => ['title' => 'サムドラ・バリ・リゾートについて', 'body' => 'サムドラ・バリ・リゾートはヌサドゥアにあるビーチフロントリゾートで、26の客室とヴィラ、海に面したインフィニティプール、白砂のビーチへの徒歩アクセスを備えています。'],
                ],
                'tags' => ['overview', 'introduction'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Jam check-in dan check-out',
                'body' => 'Check-in standar mulai pukul 14:00 dan check-out pukul 12:00. Early check-in dan late check-out dapat diajukan dan bergantung pada ketersediaan kamar; silakan konfirmasi ke staf kami H-1 sebelum kedatangan.',
                'translations' => [
                    'en' => ['title' => 'Check-in and check-out times', 'body' => 'Standard check-in is from 2:00 PM and check-out is at 12:00 PM. Early check-in and late check-out are subject to room availability; please confirm with our staff one day before arrival.'],
                    'ja' => ['title' => 'チェックイン・チェックアウト時間', 'body' => '標準チェックインは14:00から、チェックアウトは12:00です。アーリーチェックインとレイトチェックアウトは空室状況により対応可能です。到着前日にスタッフまでご確認ください。'],
                ],
                'tags' => ['check-in', 'check-out', 'early check-in', 'late check-out'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Late check-in setelah tengah malam',
                'body' => 'Resepsionis kami buka 24 jam, jadi tamu dengan penerbangan yang tiba larut malam tetap dapat check-in kapan saja. Mohon informasikan perkiraan waktu tiba agar kamar sudah siap.',
                'translations' => [
                    'en' => ['title' => 'Late-night check-in', 'body' => 'Our front desk is open 24 hours, so guests arriving on a late flight can still check in at any time. Please let us know your estimated arrival time so your room is ready.'],
                    'ja' => ['title' => '深夜チェックインについて', 'body' => 'フロントデスクは24時間対応しておりますので、深夜到着の便をご利用のお客様もいつでもチェックインいただけます。到着予定時刻を事前にお知らせください。'],
                ],
                'tags' => ['late check-in', 'flight', 'midnight', 'front desk'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Kolam renang',
                'body' => 'Kolam renang infinity menghadap laut buka setiap hari pukul 07:00–19:00. Tidak beroperasi di malam hari untuk alasan keamanan. Handuk kolam disediakan gratis di pool bar.',
                'translations' => [
                    'en' => ['title' => 'Swimming pool', 'body' => 'The ocean-facing infinity pool is open daily from 7:00 AM to 7:00 PM. It does not operate at night for safety reasons. Pool towels are provided free of charge at the pool bar.'],
                    'ja' => ['title' => 'スイミングプール', 'body' => '海に面したインフィニティプールは毎日7:00〜19:00に営業しています。安全上の理由により夜間は営業しておりません。プールタオルはプールバーで無料でご利用いただけます。'],
                ],
                'tags' => ['pool', 'swimming pool', 'hours'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Gym, spa, dan area bisnis',
                'body' => 'Pusat kebugaran buka 24 jam untuk tamu menginap. Spa buka pukul 10:00–21:00 dengan reservasi terlebih dahulu. Area bisnis dengan Wi-Fi cepat tersedia di lobi lantai 1.',
                'translations' => [
                    'en' => ['title' => 'Gym, spa, and business area', 'body' => 'The fitness center is open 24 hours for in-house guests. The spa is open from 10:00 AM to 9:00 PM by advance reservation. A business area with fast Wi-Fi is available in the ground-floor lobby.'],
                    'ja' => ['title' => 'ジム・スパ・ビジネスエリア', 'body' => 'フィットネスセンターは宿泊のお客様に24時間ご利用いただけます。スパは事前予約制で10:00〜21:00に営業しています。高速Wi-Fiを備えたビジネスエリアが1階ロビーにございます。'],
                ],
                'tags' => ['gym', 'spa', 'business center', 'wifi'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Parkir dan Wi-Fi',
                'body' => 'Parkir mobil dan motor tersedia gratis bagi tamu menginap. Wi-Fi gratis tersedia di seluruh area kamar dan fasilitas umum dengan kecepatan yang memadai untuk video call.',
                'translations' => [
                    'en' => ['title' => 'Parking and Wi-Fi', 'body' => 'Free car and motorbike parking is available for in-house guests. Complimentary Wi-Fi covers all rooms and public areas, fast enough for video calls.'],
                    'ja' => ['title' => '駐車場とWi-Fi', 'body' => '宿泊のお客様は車・バイクの駐車場を無料でご利用いただけます。全客室および共用エリアで無料Wi-Fiをご利用いただけ、ビデオ通話にも十分な速度です。'],
                ],
                'tags' => ['parking', 'wifi'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_DINING,
                'title' => 'Sarapan',
                'body' => 'Sarapan prasmanan disajikan di Restoran Samudra pukul 06:30–10:30, mencakup menu Indonesia dan internasional. Sarapan sudah termasuk pada semua tipe kamar kecuali disebutkan lain.',
                'translations' => [
                    'en' => ['title' => 'Breakfast', 'body' => 'Buffet breakfast is served at Samudra Restaurant from 6:30 AM to 10:30 AM, with both Indonesian and international dishes. Breakfast is included with every room type unless stated otherwise.'],
                    'ja' => ['title' => '朝食について', 'body' => 'ビュッフェ形式の朝食はサムドラ・レストランにて6:30〜10:30に提供され、インドネシア料理と洋食の両方をお楽しみいただけます。特に記載がない限り、全ての客室タイプに朝食が含まれます。'],
                ],
                'tags' => ['breakfast', 'restaurant'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Kebijakan anak dan tempat tidur tambahan',
                'body' => 'Anak di bawah 5 tahun menginap gratis tanpa tempat tidur tambahan menggunakan fasilitas yang ada. Tempat tidur tambahan (extra bed) tersedia dengan biaya tambahan per malam, tergantung tipe kamar, dan harus dipesan sebelum kedatangan karena jumlahnya terbatas.',
                'translations' => [
                    'en' => ['title' => 'Children and extra bed policy', 'body' => 'Children under 5 stay free using existing bedding. An extra bed is available for an additional nightly fee depending on room type, and should be requested before arrival as availability is limited.'],
                    'ja' => ['title' => 'お子様・エキストラベッドについて', 'body' => '5歳未満のお子様は既存の寝具を利用する場合、無料でご宿泊いただけます。エキストラベッドは客室タイプに応じて追加料金でご利用いただけますが、数に限りがあるため到着前のご予約をお願いします。'],
                ],
                'tags' => ['children', 'extra bed', 'kids'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Kebijakan pembatalan',
                'body' => 'Pembatalan gratis hingga 3 hari sebelum tanggal check-in. Pembatalan dalam 3 hari terakhir dikenakan biaya satu malam pertama. Tidak hadir tanpa pemberitahuan (no-show) dikenakan biaya penuh sesuai lama menginap yang dipesan.',
                'translations' => [
                    'en' => ['title' => 'Cancellation policy', 'body' => 'Free cancellation up to 3 days before check-in. Cancellations within 3 days of check-in are charged the first night. A no-show is charged the full booked stay.'],
                    'ja' => ['title' => 'キャンセルポリシー', 'body' => 'チェックインの3日前まで無料でキャンセルいただけます。3日以内のキャンセルは1泊分の料金が発生します。無連絡不泊（ノーショー）の場合は、ご予約いただいた宿泊期間分の全額が請求されます。'],
                ],
                'tags' => ['cancellation', 'refund', 'no-show'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_TRANSPORT,
                'title' => 'Antar-jemput bandara',
                'body' => 'Layanan antar-jemput dari dan ke Bandara Internasional Ngurah Rai tersedia dengan biaya tambahan, sekitar 30–40 menit perjalanan. Pemesanan harus dilakukan minimal 24 jam sebelum kedatangan dengan mengirimkan nomor penerbangan.',
                'translations' => [
                    'en' => ['title' => 'Airport transfer', 'body' => 'Airport transfer to and from Ngurah Rai International Airport is available for an extra fee, about a 30-40 minute drive. Please book at least 24 hours in advance and share your flight number.'],
                    'ja' => ['title' => '空港送迎', 'body' => 'ングラライ国際空港との送迎サービスを追加料金にてご利用いただけます（所要時間約30〜40分）。到着の24時間前までにフライト番号とあわせてご予約ください。'],
                ],
                'tags' => ['airport transfer', 'ngurah rai', 'transport'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_TRANSPORT,
                'title' => 'Atraksi terdekat',
                'body' => 'Pantai Nusa Dua dapat dicapai dengan berjalan kaki 2 menit. Water Blow Nusa Dua sekitar 5 menit berkendara, dan Pura Uluwatu berjarak sekitar 45 menit berkendara ke arah selatan.',
                'translations' => [
                    'en' => ['title' => 'Nearby attractions', 'body' => 'Nusa Dua Beach is a 2-minute walk away. Water Blow Nusa Dua is about a 5-minute drive, and Uluwatu Temple is roughly a 45-minute drive to the south.'],
                    'ja' => ['title' => '近隣の観光スポット', 'body' => 'ヌサドゥア・ビーチまで徒歩2分。ウォーターブロウ・ヌサドゥアまで車で約5分、ウルワツ寺院までは南へ車で約45分です。'],
                ],
                'tags' => ['attractions', 'nusa dua beach', 'uluwatu', 'water blow'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Apakah semua kamar memiliki bathtub?',
                'body' => 'Tidak semua. Deluxe Garden View menggunakan shower, sedangkan Deluxe Ocean View, Family Suite, dan Honeymoon Pool Villa dilengkapi bathtub.',
                'translations' => [
                    'en' => ['title' => 'Do all rooms have a bathtub?', 'body' => 'Not all of them. Deluxe Garden View has a shower only, while Deluxe Ocean View, Family Suite, and Honeymoon Pool Villa come with a bathtub.'],
                    'ja' => ['title' => '全ての客室にバスタブはありますか？', 'body' => '全室ではありません。デラックス ガーデンビューはシャワーのみですが、デラックス オーシャンビュー、ファミリースイート、ハネムーン プールヴィラにはバスタブが付いています。'],
                ],
                'tags' => ['bathtub', 'bathroom', 'faq'],
            ],
            [
                'category' => HotelKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Apakah hotel menerima hewan peliharaan?',
                'body' => 'Untuk saat ini kami belum dapat menerima hewan peliharaan, kecuali hewan pemandu bagi penyandang disabilitas.',
                'translations' => [
                    'en' => ['title' => 'Are pets allowed?', 'body' => 'We are currently unable to accommodate pets, with the exception of service animals for guests with disabilities.'],
                    'ja' => ['title' => 'ペットは同伴できますか？', 'body' => '現在、障がいをお持ちのお客様の補助犬を除き、ペットの同伴はご遠慮いただいております。'],
                ],
                'tags' => ['pets', 'faq'],
            ],
        ];

        $sort = 0;
        foreach ($items as $item) {
            $hotel->knowledgeItems()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'category' => $item['category'],
                    'body' => $item['body'],
                    'translations' => $item['translations'],
                    'tags' => $item['tags'],
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );
        }
    }
}
