<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Shrinks a photo before it is stored (settlement, voucher and bank register attachments all use it), so the disk or
 * the S3 bucket stays small however many receipts are scanned. PDFs and anything that is not a JPG, PNG or WEBP pass
 * through untouched, and so does a picture that is already small; a failure returns the original file.
 */
class AttachmentImageOptimizer
{
    private const TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    public function optimize(UploadedFile $file): UploadedFile
    {
        if (! config('attachment_images.enabled') || ! function_exists('imagecreatefromstring')) {
            return $file;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if (! in_array($extension, self::TYPES, true) || ! $path || ! is_file($path)) {
            return $file;
        }

        try {
            [$width, $height] = @getimagesize($path) ?: [0, 0];

            if ($width < 1 || $height < 1) {
                return $file;
            }

            $limit = max(200, (int) config('attachment_images.max_px'));
            $oversized = max($width, $height) > $limit;

            if (! $oversized && $file->getSize() <= (int) config('attachment_images.min_kb') * 1024) {
                return $file;
            }

            // GD holds about 5 bytes per pixel while it works. A huge picture could use up all the memory (a fatal error
            // that cannot be caught), so such a picture is stored as it is instead.
            if (! $this->fitsInMemory($width, $height)) {
                return $file;
            }

            $image = @imagecreatefromstring((string) file_get_contents($path));

            if ($image === false) {
                return $file;
            }

            $image = $this->upright($image, $extension, $path);

            if ($oversized) {
                $ratio = $limit / max($width, $height);
                $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC);
                $image = $scaled === false ? $image : $scaled;
            }

            $temporary = tempnam(sys_get_temp_dir(), 'att-img-');
            $this->save($image, $extension, $temporary);

            if (! $oversized && filesize($temporary) >= $file->getSize()) {
                @unlink($temporary);

                return $file;
            }

            return new UploadedFile($temporary, $file->getClientOriginalName(), $file->getClientMimeType(), null, true);
        } catch (\Throwable $e) {
            Log::warning('Attachment photo could not be optimised, the original is kept', ['file' => $file->getClientOriginalName(), 'error' => $e->getMessage()]);

            return $file;
        }
    }

    private function fitsInMemory(int $width, int $height): bool
    {
        $limit = $this->memoryLimitBytes();

        if ($limit === null) {
            return true;
        }

        return ($limit - memory_get_usage()) > $width * $height * 6;
    }

    /**
     * @return int|null PHP's memory limit in bytes, or null when there is none
     */
    private function memoryLimitBytes(): ?int
    {
        $value = trim((string) ini_get('memory_limit'));

        if ($value === '' || $value === '-1') {
            return null;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Phones store the way the camera was held in the file's EXIF data; apply it so the picture is not stored sideways.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function upright($image, string $extension, string $path)
    {
        if (! in_array($extension, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated === false ? $image : $rotated;
    }

    /**
     * @param  \GdImage  $image
     */
    private function save($image, string $extension, string $target): void
    {
        $quality = max(40, min(95, (int) config('attachment_images.quality')));

        if ($extension === 'png') {
            imagesavealpha($image, true);
            imagepng($image, $target, 7);
        } elseif ($extension === 'webp' && function_exists('imagewebp')) {
            imagewebp($image, $target, $quality);
        } else {
            imagejpeg($image, $target, $quality);
        }
    }
}
