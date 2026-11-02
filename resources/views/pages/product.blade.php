@extends('layouts.app')
@section('title', $product->name . ' — Зефир и пряник')
@section('description', $product->name . ': ' . ($product->long ?: $product->short) . ' Кондитерская «Зефир и пряник», Москва.')
@section('canonical', $product->url())

@php
    $variants = $product->variantList();
    $buyable = $product->isBuyable();
    $props = ['id' => $product->slug, 'name' => $product->name, 'variants' => $variants, 'cake' => $product->category === 'cake'];
@endphp

@php
    $ld = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'description' => $product->long ?: $product->short,
        'image' => $product->image ? url(\App\Support\Img::src($product->image, 720)) : null,
        'brand' => ['@type' => 'Brand', 'name' => 'Зефир и пряник'],
        'offers' => $buyable ? ['@type' => 'Offer', 'priceCurrency' => 'RUB', 'price' => $product->price, 'availability' => 'https://schema.org/InStock', 'url' => $product->url()] : null,
    ]);
@endphp
@push('head')
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<div class="g" style="padding-block:24px 120px">
    <div class="w" style="display:flex;flex-direction:column;gap:32px">
        <nav class="crumbs" aria-label="Навигация"><a href="{{ route('catalog') }}">Каталог</a><span aria-hidden="true">/</span><span>{{ $product->name }}</span></nav>

        <div class="pdp">
            <div class="gal"><div class="photo">
                {{ \App\Support\Img::picture($product->image, $product->name, '(min-width: 900px) 55vw, 94vw', true, '', 'p-'.$product->slug) }}
                <span class="badges">
                    @if($product->is_pp)<span class="badge pp">Без сахара</span>@endif
                    @if($product->on_order)<span class="badge ord">На заказ</span>@endif
                </span>
            </div></div>

            <div class="info">
                <div style="display:flex;flex-direction:column;gap:14px">
                    @if($product->is_new)<span class="label">Новинка</span>@endif
                    <h1>{{ $product->name }}</h1>
                    <p class="lead" style="font-size:17px;line-height:28px">{{ $product->long ?: $product->short }}</p>
                </div>

                @if($buyable)
                    <div data-island="product-buy" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE) }}">
                        {{-- Заготовка до загрузки скриптов: те же размеры, что у интерактивной версии --}}
                        @if(count($variants) > 1)
                            <fieldset class="opts"><legend>Формат</legend>
                                @foreach($variants as $i => $v)
                                    <label class="opt"><input type="radio" name="fmt" @checked($i === 0)><span class="t">{{ $v['label'] }}@if($v['sub'])<small>{{ $v['sub'] }}</small>@endif</span><span class="pr">{{ rub($v['price']) }}</span></label>
                                @endforeach
                            </fieldset>
                        @endif
                        <button class="note-toggle" type="button" style="margin-top:20px" tabindex="-1"><span class="pl" aria-hidden="true"><svg class="i" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12M12 6v12"/></svg></span>Открытка с подписью от руки, бесплатно</button>
                        <div class="buyrow" style="margin-top:20px"><div class="qty lg"><button type="button" tabindex="-1" aria-label="Меньше"><svg class="i" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12"/></svg></button><output class="tn">1</output><button type="button" tabindex="-1" aria-label="Больше"><svg class="i" viewBox="0 0 24 24" style="width:18px;height:18px"><path d="M6 12h12M12 6v12"/></svg></button></div><button class="btn btn-main" type="button" data-primary>В корзину · {{ rub($variants[0]['price']) }}</button></div>
                    </div>
                @else
                    <div style="display:flex;align-items:baseline;gap:16px;flex-wrap:wrap"><span class="price" style="font-size:22px">{{ $product->priceText() }}</span></div>
                    <p class="small">{{ $product->out_of_stock ? 'Сейчас нет в наличии. Напишите нам, и мы сообщим о поступлении.' : ($product->on_order ? 'Делаем на заказ. Цену и сроки назовём после обсуждения, заказывайте минимум за 3 дня.' : 'Цену назовём по запросу.') }}</p>
                    <div class="buyrow"><a class="btn btn-main" href="{{ route('delivery') }}#contacts">{{ $product->out_of_stock ? 'Сообщить о поступлении' : ($product->on_order ? 'Обсудить заказ' : 'Уточнить цену') }}</a></div>
                @endif

                @if($product->allergens)<dl class="dl"><dt>Аллергены</dt><dd>{{ $product->allergens }}</dd></dl>@endif
            </div>
        </div>
    </div>
</div>
@endsection
