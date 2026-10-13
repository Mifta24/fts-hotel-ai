<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores a photo the hotel uploaded: shrunk to a size that suits a phone
 * screen and saved as WebP, so a 12 MB camera original never reaches a guest.
 */
class ImageUploader
{
    public const MAX_WIDTH = 1600;

    public const MAX_KILOBYTES = 8192;

    private const DISK = 'public';

    /**
     * @return string the path on the public disk, e.g. rooms/3f2c….webp
     *
     * @throws RuntimeException when the file cannot be read as an image
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            throw new RuntimeException('That file could not be read as an image.');
        }

        $image = $this->upright($image, $file);
        $image = $this->shrink($image);

        ob_start();
        imagewebp($image, null, 82);
        $bytes = (string) ob_get_clean();

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        Storage::disk(self::DISK)->put($path, $bytes);

        return $path;
    }

    public function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }

    public function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function shrink(\GdImage $image): \GdImage
    {
        $width = imagesx($image);

        if ($width <= self::MAX_WIDTH) {
            return $image;
        }

        return imagescale($image, self::MAX_WIDTH) ?: $image;
    }

    /**
     * Phone photos carry their rotation in EXIF; apply it so the picture is not sideways.
     */
    private function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle !== 0 ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
