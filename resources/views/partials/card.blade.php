@php
    $buyable = $product->isBuyable();
    $simple = $buyable && count($product->variantList()) === 1;
    $priced = is_int($product->price) && ! $product->out_of_stock;
@endphp
<article class="card{{ $product->out_of_stock ? ' out' : '' }}" style="--i: {{ $loop->index ?? 0 }}">
    <a class="ph" href="{{ $product->url() }}" tabindex="-1" aria-hidden="true">
        <div class="photo">
            {{ \App\Support\Img::picture($product->image, $product->name, $sizes ?? '(min-width: 980px) 28vw, (min-width: 620px) 45vw, 92vw', ! empty($eager), '', 'p-'.$product->slug) }}
            <span class="badges">
                @if($product->is_new)<span class="badge">Новинка</span>@endif
                @if($product->is_pp)<span class="badge pp">Без сахара</span>@endif
                @if($product->on_order)<span class="badge ord">На заказ</span>@endif
            </span>
        </div>
    </a>
    <div class="head">
        <h2><a href="{{ $product->url() }}">{{ $product->name }}</a></h2>
        <span class="price{{ $priced ? '' : ' muted' }}">{{ $product->priceText() }}</span>
    </div>
    <p class="comp">{{ $product->short }}</p>
    <div class="buy"
        @if($simple) data-island="buy" data-props="{{ json_encode(['id' => $product->slug, 'name' => $product->name], JSON_UNESCAPED_UNICODE) }}" @endif>
        @if($product->out_of_stock)
            <a class="btn btn-line btn-sm" href="{{ route('delivery') }}#contacts">Сообщить о поступлении</a>
        @elseif($product->on_order)
            <a class="btn btn-main btn-sm" href="{{ route('delivery') }}#contacts">Заказать</a>
        @elseif(! is_int($product->price))
            <a class="btn btn-line btn-sm" href="{{ route('delivery') }}#contacts">Уточнить цену</a>
        @elseif(! $simple)
            <a class="btn btn-main btn-sm" href="{{ $product->url() }}">Выбрать формат</a>
        @else
            <button class="btn btn-main btn-sm" type="button" data-primary>В корзину</button>
        @endif
    </div>
</article>
