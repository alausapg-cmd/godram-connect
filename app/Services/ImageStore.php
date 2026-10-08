<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploads privately with safe generated names. Photos are re-encoded
 * to WebP and resized, which strips hidden payloads and saves bandwidth.
 */
class ImageStore
{
    public function storeImage(UploadedFile $file, string $folder, int $maxWidth = 1600): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw new \RuntimeException('That file is not a photo we can read.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width > $maxWidth) {
            $newHeight = (int) round($height * $maxWidth / $width);
            $resized = imagecreatetruecolor($maxWidth, $newHeight);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        ob_start();
        imagewebp($source, null, 78);
        $binary = ob_get_clean();
        imagedestroy($source);

        $path = trim($folder, '/').'/'.Str::uuid().'.webp';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public function storeDocument(UploadedFile $file, string $folder): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        return $file->storeAs(trim($folder, '/'), Str::uuid().'.'.$extension, 'local');
    }
}
