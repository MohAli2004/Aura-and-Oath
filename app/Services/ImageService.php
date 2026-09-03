<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImageService
{
    public function store(UploadedFile $file, string $directory = 'products'): string
    {
        $filename = Str::uuid().'.'.$this->safeExtension($file);
        $path = $file->storeAs($directory, $filename, 'public');

        $this->maybeCreateThumbnail($path, $directory);

        return $path;
    }

    /**
     * Never trust the client filename for the stored extension: it decides
     * what the web server will do with the file. The extension is derived from
     * the detected image type, and anything we do not recognise is rejected.
     */
    protected function safeExtension(UploadedFile $file): string
    {
        $allowed = (array) config('security.uploads.allowed_mimes', []);

        $detected = @getimagesize($file->getRealPath() ?: '');
        $mime = $detected['mime'] ?? null;

        if (! $mime || ! isset($allowed[$mime])) {
            $mime = $file->getMimeType();
        }

        if (! $mime || ! isset($allowed[$mime])) {
            throw new InvalidArgumentException('Unsupported image type.');
        }

        return $allowed[$mime];
    }

    /**
     * Move a temp upload into a permanent public directory.
     */
    public function promoteTemp(?string $tempPath, string $directory = 'products'): ?string
    {
        if (! $tempPath) {
            return null;
        }

        if (! Storage::disk('public')->exists($tempPath)) {
            return null;
        }

        if (! str_contains($tempPath, '/tmp/')) {
            return $tempPath;
        }

        $dest = trim($directory, '/').'/'.basename($tempPath);

        if (Storage::disk('public')->exists($dest)) {
            $dest = trim($directory, '/').'/'.Str::uuid().'.'.pathinfo($tempPath, PATHINFO_EXTENSION);
        }

        Storage::disk('public')->move($tempPath, $dest);

        $tempThumb = $this->thumbnailPath($tempPath);
        if (Storage::disk('public')->exists($tempThumb)) {
            $destThumb = $this->thumbnailPath($dest);
            Storage::disk('public')->makeDirectory(dirname($destThumb));
            Storage::disk('public')->move($tempThumb, $destThumb);
        } else {
            $this->maybeCreateThumbnail($dest, $directory);
        }

        return $dest;
    }

    public function isTempPath(?string $path): bool
    {
        return is_string($path) && str_contains($path, '/tmp/');
    }

    public function storeFromPath(string $absolutePath, string $directory = 'products', ?string $extension = 'svg'): string
    {
        $filename = Str::uuid().'.'.$extension;
        $relative = $directory.'/'.$filename;
        Storage::disk('public')->put($relative, file_get_contents($absolutePath));

        return $relative;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
        $thumb = $this->thumbnailPath($path);
        if ($thumb) {
            Storage::disk('public')->delete($thumb);
        }
    }

    public function url(?string $path): string
    {
        if (! $path) {
            return '/images/placeholders/product.svg';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        // Root-relative URLs so icons work even when APP_URL host/port differs from the browser.
        if (str_starts_with($path, 'images/')) {
            return '/'.ltrim($path, '/');
        }

        return '/storage/'.ltrim($path, '/');
    }

    protected function maybeCreateThumbnail(string $path, string $directory): void
    {
        if (! extension_loaded('gd')) {
            return;
        }

        $full = Storage::disk('public')->path($path);
        $info = @getimagesize($full);
        if (! $info) {
            return;
        }

        [$width, $height, $type] = $info;
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($full),
            IMAGETYPE_PNG => @imagecreatefrompng($full),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($full) : false,
            default => false,
        };

        if (! $src) {
            return;
        }

        $max = 400;
        $ratio = min($max / max($width, 1), $max / max($height, 1), 1);
        $tw = (int) max(1, $width * $ratio);
        $th = (int) max(1, $height * $ratio);
        $dst = imagecreatetruecolor($tw, $th);

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $width, $height);

        $thumbRel = $this->thumbnailPath($path);
        $thumbFull = Storage::disk('public')->path($thumbRel);
        @mkdir(dirname($thumbFull), 0755, true);

        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($dst, $thumbFull, 85),
            IMAGETYPE_PNG => imagepng($dst, $thumbFull, 7),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($dst, $thumbFull, 85) : null,
            default => null,
        };

        imagedestroy($src);
        imagedestroy($dst);
    }

    protected function thumbnailPath(string $path): string
    {
        $dir = dirname($path);
        $file = basename($path);

        return ($dir === '.' ? 'thumbs' : $dir.'/thumbs').'/'.$file;
    }
}
