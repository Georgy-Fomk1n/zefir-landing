<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/** <picture> с AVIF/WebP и srcset по данным из products.image */
class Img
{
    public static function picture(?array $image, string $alt, string $sizes = '(min-width: 980px) 33vw, 50vw', bool $eager = false, string $class = '', ?string $vtName = null): HtmlString
    {
        if (! $image || empty($image['base'])) {
            return new HtmlString('');
        }
        $base = '/' . ltrim($image['base'], '/');
        $widths = $image['widths'] ?? [];
        $set = fn (string $ext) => implode(', ', array_map(fn ($w) => e("$base-$w.$ext") . " {$w}w", $widths));
        $last = end($widths);
        $style = $vtName ? ' style="view-transition-name:' . e($vtName) . '"' : '';
        $html = '<picture>';
        if (! empty($image['avif'])) {
            $html .= '<source type="image/avif" srcset="' . $set('avif') . '" sizes="' . e($sizes) . '">';
        }
        $ext = $image['ext'] ?? 'webp';
        $html .= '<source type="image/' . ($ext === 'jpg' ? 'jpeg' : 'webp') . '" srcset="' . $set($ext) . '" sizes="' . e($sizes) . '">';
        $html .= '<img src="' . e("$base-$last.$ext") . '" alt="' . e($alt) . '" width="' . (int) ($image['w'] ?? 0) . '" height="' . (int) ($image['h'] ?? 0) . '"'
            . ($eager ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"')
            . ($class ? ' class="' . e($class) . '"' : '') . $style . '>';

        return new HtmlString($html . '</picture>');
    }

    /** Данные для React-островов: только то, что нужно для <img> */
    public static function src(?array $image, int $w = 480): string
    {
        if (! $image || empty($image['base'])) {
            return '';
        }
        $widths = $image['widths'] ?? [$w];
        $pick = collect($widths)->first(fn ($x) => $x >= $w) ?? end($widths);

        return '/' . ltrim($image['base'], '/') . "-$pick." . ($image['ext'] ?? 'webp');
    }

    /** Картинка из каталога по имени исходника: images/<name> */
    public static function named(string $name): ?array
    {
        static $m = null;
        $m ??= json_decode((string) @file_get_contents(public_path('images/manifest.json')), true) ?: [];
        if (! isset($m[$name])) {
            return null;
        }

        return ['base' => "images/$name", 'widths' => $m[$name]['widths'], 'w' => $m[$name]['w'], 'h' => $m[$name]['h'], 'avif' => true];
    }
}
