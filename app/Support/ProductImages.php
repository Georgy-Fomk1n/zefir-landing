<?php

namespace App\Support;

use App\Http\Middleware\PageCache;
use App\Models\Product;

/** Фото, загруженное в админке → лёгкие WebP-версии 320/480/720 px (нужны GD и поддержка WebP, на хостингах это обычно есть). */
class ProductImages
{
    public const WIDTHS = [320, 480, 720];

    public static function process(Product $p): void
    {
        $rel = $p->upload;
        $file = $rel ? public_path('uploads/' . $rel) : null;
        if (! $file || ! is_file($file) || ! function_exists('imagecreatefromstring')) {
            return;
        }
        $im = @imagecreatefromstring((string) file_get_contents($file));
        if (! $im) {
            return;
        }

        // Фото с телефона часто повёрнуты через EXIF
        if (function_exists('exif_read_data') && preg_match('/\.jpe?g$/i', $file)) {
            $orient = @exif_read_data($file)['Orientation'] ?? 1;
            $im = match ($orient) { 3 => imagerotate($im, 180, 0), 6 => imagerotate($im, -90, 0), 8 => imagerotate($im, 90, 0), default => $im };
        }

        $w = imagesx($im);
        $h = imagesy($im);
        $hash = pathinfo($file, PATHINFO_FILENAME);
        $dir = public_path('uploads/products');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $webp = function_exists('imagewebp');
        $ext = $webp ? 'webp' : 'jpg';
        $widths = array_values(array_unique(array_map(fn ($x) => min($x, $w), self::WIDTHS)));
        foreach ($widths as $tw) {
            $scaled = $tw === $w ? $im : imagescale($im, $tw, -1, IMG_BICUBIC);
            $out = "$dir/$hash-$tw.$ext";
            $webp ? imagewebp($scaled, $out, 80) : imagejpeg($scaled, $out, 82);
            if ($scaled !== $im) {
                imagedestroy($scaled);
            }
        }

        $p->image = ['base' => "uploads/products/$hash", 'widths' => $widths, 'w' => $w, 'h' => $h, 'avif' => false, 'ext' => $ext];
        $p->saveQuietly();
        PageCache::flush();
    }
}
