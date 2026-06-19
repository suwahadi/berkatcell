<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ImageWebp
{
    public static function store(
        UploadedFile $file,
        string $folder = 'products',
        int $maxSide = 1600,
        int $quality = 82,
    ): string {
        $realPath = $file->getRealPath();

        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException('Berkas gambar tidak dapat dibaca.');
        }

        $info = @getimagesize($realPath);

        $src = match ($info['2'] ?? null) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($realPath),
            IMAGETYPE_PNG => imagecreatefrompng($realPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($realPath),
            IMAGETYPE_GIF => imagecreatefromgif($realPath),
            default => throw new RuntimeException('Format gambar tidak didukung. Gunakan JPG, PNG, atau WebP.'),
        };

        if ($src === false) {
            throw new RuntimeException('Gagal memproses gambar.');
        }

        imagepalettetotruecolor($src);

        $width = imagesx($src);
        $height = imagesy($src);
        $scale = min(1.0, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($src);

        ob_start();
        imagewebp($dst, null, $quality);
        $binary = (string) ob_get_clean();
        imagedestroy($dst);

        $disk = Storage::disk('public');
        do {
            $name = Str::lower(Str::random(15)).'.webp';
            $path = "{$folder}/{$name}";
        } while ($disk->exists($path));

        $disk->put($path, $binary);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
