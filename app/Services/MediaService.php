<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores uploaded media on the configured disk (Supabase Storage in production).
 *
 * Images are decoded and re-encoded with GD as WebP. Re-encoding strips EXIF
 * (including GPS data) and anything smuggled inside the original file, and
 * normalises dimensions so pages stay fast.
 */
class MediaService
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public const DOCUMENT_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    /** @return array{path:string, disk:string, width:int, height:int, size:int} */
    public function storeImage(UploadedFile $file, string $directory, int $maxWidth = 1920, int $maxHeight = 1920, int $quality = 82): array
    {
        $mime = $file->getMimeType();
        if (! in_array($mime, self::IMAGE_MIMES, true)) {
            throw new RuntimeException('Unsupported image type.');
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
        };

        if (! $source) {
            throw new RuntimeException('The image could not be processed.');
        }

        if ($mime === 'image/jpeg') {
            $source = $this->applyExifOrientation($source, $file->getRealPath());
        }

        [$w, $h] = [imagesx($source), imagesy($source)];
        $ratio = min(1, $maxWidth / $w, $maxHeight / $h);
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $canvas = imagecreatetruecolor($nw, $nh);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagewebp($canvas, null, $quality);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        $disk = (string) config('filesystems.media_disk');
        $path = trim($directory, '/').'/'.Str::lower((string) Str::ulid()).'.webp';
        Storage::disk($disk)->put($path, $binary, ['mimetype' => 'image/webp', 'visibility' => 'public']);

        return ['path' => $path, 'disk' => $disk, 'width' => $nw, 'height' => $nh, 'size' => strlen($binary)];
    }

    /** Stores a private document (verification / claim proof). Returns [disk, path]. */
    public function storePrivateDocument(UploadedFile $file, string $directory): array
    {
        $mime = $file->getMimeType();
        if (! in_array($mime, self::DOCUMENT_MIMES, true)) {
            throw new RuntimeException('Unsupported document type.');
        }

        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };

        $disk = (string) config('filesystems.private_media_disk');
        $path = trim($directory, '/').'/'.Str::lower((string) Str::ulid()).'.'.$ext;
        Storage::disk($disk)->put($path, (string) file_get_contents($file->getRealPath()), ['mimetype' => $mime, 'visibility' => 'private']);

        return [$disk, $path];
    }

    /** Short-lived URL for a private document. */
    public function temporaryUrl(string $disk, string $path, int $minutes = 10): string
    {
        $storage = Storage::disk($disk);

        return $storage->providesTemporaryUrls()
            ? $storage->temporaryUrl($path, now()->addMinutes($minutes))
            : $storage->url($path);
    }

    public function delete(?string $path, ?string $disk = null): void
    {
        if ($path && ! str_starts_with($path, 'brand/') && ! str_starts_with($path, 'http')) {
            try {
                Storage::disk($disk ?? (string) config('filesystems.media_disk'))->delete($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        return $rotated ?: $image;
    }
}
