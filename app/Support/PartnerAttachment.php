<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class PartnerAttachment
{
    public static function save(UploadedFile $file): string
    {
        $details = @getimagesize($file->getRealPath());
        if (! $details || ! in_array($details[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $details[0] > 3000 || $details[1] > 3000 || ! function_exists('imagewebp')) {
            throw ValidationException::withMessages(['attachment' => 'Upload a valid JPG, PNG or WebP image (GD WebP support required).']);
        }
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) throw ValidationException::withMessages(['attachment' => 'Image could not be read.']);
        $width = (int) $details[0]; $height = (int) $details[1];
        $ratio = min(1, 1600 / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
        imagedestroy($source);

        $directory = public_path('uploads/partners');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            imagedestroy($target);
            throw ValidationException::withMessages(['attachment' => 'Upload directory is not writable.']);
        }
        $relative = 'uploads/partners/'.bin2hex(random_bytes(16)).'.webp';
        $ok = imagewebp($target, public_path($relative), 76);
        imagedestroy($target);
        if (! $ok) throw ValidationException::withMessages(['attachment' => 'Could not save the image.']);

        return $relative;
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/partners/')) @unlink(public_path($path));
    }
}
