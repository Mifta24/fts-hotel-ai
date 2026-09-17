<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'hotel_id',
    'name',
    'slug',
    'description',
    'translations',
    'size_sqm',
    'max_adults',
    'max_children',
    'bed_config',
    'view_type',
    'breakfast_included',
    'extra_bed_available',
    'extra_bed_price',
    'base_price',
    'amenities',
    'is_active',
    'sort_order',
])]
class RoomType extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'bed_config' => 'array',
            'amenities' => 'array',
            'breakfast_included' => 'boolean',
            'extra_bed_available' => 'boolean',
            'extra_bed_price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(RoomInventory::class);
    }

    public function maxOccupancy(): int
    {
        return $this->max_adults + $this->max_children;
    }

    public function translatedName(string $locale): string
    {
        $value = $this->translations[$locale]['name'] ?? null;

        return filled($value) ? $value : $this->name;
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }
}
