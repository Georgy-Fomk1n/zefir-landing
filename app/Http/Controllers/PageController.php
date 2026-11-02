<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Schedule;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()
    {
        $featured = Product::visible()->with('variants')->whereIn('slug', ['pear', 'potato', 'zefir-berry', 'eclair', 'tart', 'nuts'])->get()
            ->sortBy(fn ($p) => array_search($p->slug, ['pear', 'potato', 'zefir-berry', 'eclair', 'tart', 'nuts']))->values();

        return view('pages.home', compact('featured'));
    }

    public function catalog(Request $request)
    {
        $cat = array_key_exists($request->query('cat'), Product::CATEGORIES) ? $request->query('cat') : 'all';
        $sort = in_array($request->query('sort'), ['asc', 'desc'], true) ? $request->query('sort') : 'pop';

        $counts = [];
        foreach (array_keys(Product::CATEGORIES) as $c) {
            $counts[$c] = Product::visible()->inCategory($c)->count();
        }
        $products = Product::visible()->with('variants')->inCategory($cat)->get();
        if ($sort !== 'pop') {
            $dir = $sort === 'asc' ? 1 : -1;
            $products = $products->values()->map(fn ($p, $i) => [$p, $i])->sort(function ($a, $b) use ($dir) {
                [$x, $y] = [$a[0]->price, $b[0]->price];
                if ($x === null && $y === null) {
                    return $a[1] <=> $b[1];
                }
                if ($x === null) {
                    return 1;
                }
                if ($y === null) {
                    return -1;
                }

                return (($x <=> $y) * $dir) ?: ($a[1] <=> $b[1]);
            })->map(fn ($t) => $t[0])->values();
        }

        return view('pages.catalog', compact('products', 'cat', 'sort', 'counts'));
    }

    public function product(string $slug)
    {
        $product = Product::visible()->with('variants')->where('slug', $slug)->firstOrFail();

        return view('pages.product', compact('product'));
    }

    public function delivery()
    {
        return view('pages.delivery');
    }

    public function checkout()
    {
        return view('pages.checkout');
    }

    public function offline()
    {
        return view('pages.offline');
    }
}
