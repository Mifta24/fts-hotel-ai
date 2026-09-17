<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'room_type_id',
    'image_path',
    'image_url',
    'tags',
    'alt_text',
    'sort_order',
])]
class RoomImage extends Model
{
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function getImageSourceAttribute(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? [], true);
    }
}
