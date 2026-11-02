<?php

namespace App\Http\Controllers;

use App\Models\Product;

class SeoController extends Controller
{
    public function robots()
    {
        $txt = "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /order/\nDisallow: /checkout\nDisallow: /offline\n\nSitemap: " . url('/sitemap.xml') . "\n";

        return response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap()
    {
        $urls = [[url('/'), null], [route('catalog'), null], [route('delivery'), null]];
        foreach (array_keys(array_diff_key(Product::CATEGORIES, ['all' => 1])) as $cat) {
            $urls[] = [route('catalog', ['cat' => $cat]), null];
        }
        foreach (Product::visible()->get() as $p) {
            $urls[] = [$p->url(), $p->updated_at?->toAtomString()];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $mod]) {
            $xml .= '  <url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . "</url>\n";
        }

        return response($xml . '</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
