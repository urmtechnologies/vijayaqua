<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class PartnerAttachment
{
    public const MAX_IMAGES = 10;

    public static function saveMany(array $files): array
    {
        $paths = [];
        try {
            foreach ($files as $file) $paths[] = self::save($file);
        } catch (\Throwable $error) {
            foreach ($paths as $path) self::delete($path);
            throw $error;
        }
        return $paths;
    }

    public static function save(UploadedFile $file): string
    {
        $details = @getimagesize($file->getRealPath());
        if (! $details || ! in_array($details[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $details[0] * $details[1] > 25_000_000 || ! function_exists('imagewebp')) {
            throw ValidationException::withMessages(['attachments' => 'Upload JPG, PNG or WebP images up to 25 megapixels (GD WebP support required).']);
        }
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) throw ValidationException::withMessages(['attachments' => 'Image could not be read.']);
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
            throw ValidationException::withMessages(['attachments' => 'Upload directory is not writable.']);
        }
        $relative = 'uploads/partners/'.bin2hex(random_bytes(16)).'.webp';
        $ok = @imagewebp($target, public_path($relative), 76);
        imagedestroy($target);
        if (! $ok) {
            self::delete($relative);
            throw ValidationException::withMessages(['attachments' => 'Could not save the image.']);
        }

        return $relative;
    }

    public static function delete(?string $path): void
    {
        if ($path && preg_match('~^uploads/partners/[a-zA-Z0-9_-]+\.webp$~D', $path)) @unlink(public_path($path));
    }
}
