<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The approved knowledge base the AI Concierge retrieves from. This is the
 * ONLY place hotel facts may come from — the model is never allowed to
 * answer a hotel-knowledge question from its own memory.
 */
#[Fillable([
    'hotel_id',
    'category',
    'title',
    'body',
    'translations',
    'tags',
    'image_url',
    'is_active',
    'sort_order',
])]
class HotelKnowledgeItem extends Model
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_FACILITIES = 'facilities';

    public const CATEGORY_POLICIES = 'policies';

    public const CATEGORY_DINING = 'dining';

    public const CATEGORY_TRANSPORT = 'transport';

    public const CATEGORY_FAQ = 'faq';

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * A short icon name for this entry, taken from its tags or title so the
     * facilities cards can show a matching symbol without another column.
     */
    public function iconName(): string
    {
        $haystack = Str::lower($this->title.' '.implode(' ', $this->tags ?? []));

        foreach ([
            'pool' => ['pool', 'swim', 'kolam'],
            'gym' => ['gym', 'fitness', 'spa', 'kebugaran'],
            'parking' => ['parking', 'parkir', 'car'],
            'dining' => ['breakfast', 'sarapan', 'restaurant', 'restoran', 'dining', 'makan'],
            'transport' => ['airport', 'bandara', 'transfer', 'shuttle', 'antar-jemput'],
            'place' => ['nearby', 'terdekat', 'attraction', 'atraksi', 'beach', 'pantai'],
            'wifi' => ['wifi', 'wi-fi', 'internet'],
        ] as $icon => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($haystack, $needle)) {
                    return $icon;
                }
            }
        }

        return 'star';
    }

    public function translatedTitle(string $locale): string
    {
        $value = $this->translations[$locale]['title'] ?? null;

        return filled($value) ? $value : $this->title;
    }

    public function translatedBody(string $locale): string
    {
        $value = $this->translations[$locale]['body'] ?? null;

        return filled($value) ? $value : $this->body;
    }
}
