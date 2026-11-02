@extends('layouts.app')
@section('title', 'Зефир и пряник — кондитерская на Базовской: пирожные, зефир, пряники, торты | Заказать онлайн')
@section('description', 'Закажите корпусные пирожные, зефир на натуральном пюре, имбирные пряники и торты в Москве. Самовывоз с ул. Базовская, 17 или доставка по Москве.')
@section('canonical', url('/'))

@push('head')
    <link rel="preload" as="image" type="image/avif" imagesrcset="/images/zefir-mushrooms-320.avif 320w, /images/zefir-mushrooms-480.avif 480w, /images/zefir-mushrooms-720.avif 720w" imagesizes="(min-width: 820px) 40vw, 90vw" fetchpriority="high">
    @include('partials.schema')
@endpush

@php
    use App\Support\Img;
    $pic = fn ($name, $alt, $sizes = '(min-width: 820px) 25vw, 45vw', $eager = false) => Img::picture(Img::named($name), $alt, $sizes, $eager);
    $tile = fn ($name, $alt, $sizes = '(min-width: 820px) 25vw, 45vw', $cls = '') => new \Illuminate\Support\HtmlString('<div class="tile ' . $cls . '">' . $pic($name, $alt, $sizes) . '</div>');
@endphp

@section('content')
<section class="hero w g">
    <div class="k">
        <img class="hero-logo" src="/images/logo-240.webp" alt="Зефир и пряник" width="210" height="171">
        <span class="eyebrow">Москва · Базовская, 17 · ручная работа</span>
        <h1 class="d1">Место, где <em class="ac">тебя ждут</em></h1>
        <p class="lead">Кондитерская на Базовской. Зефир на натуральном пюре, корпусные пирожные, имбирные пряники и торты на заказ. Заходите на кофе или заберите коробочку с собой.</p>
        <div class="acts">
            <a class="btn btn-main" href="{{ route('catalog') }}">Смотреть витрину</a>
            <a class="btn btn-text" href="#cakes">Заказать торт <svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
    <div class="mosaic">
        <div class="big">
            {{ $pic('zefir-mushrooms', 'Зефирные грибочки в корзинке', '(min-width: 820px) 40vw, 90vw', true) }}
            <video data-src="/images/mushrooms.mp4" autoplay muted loop playsinline preload="none" aria-label="Зефирные грибочки в корзинке"></video>
            <span class="vtag"><svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:12px;height:12px;fill:currentColor;stroke:none"><path d="M8 5.5v13l11-6.5z"/></svg>Зефирные грибочки</span>
        </div>
        {{ $tile('zefir', 'Розовый зефир') }}
        {{ $tile('pear', 'Корпусное пирожное Груша') }}
    </div>
</section>

<section class="g rv" aria-label="Разделы" style="padding-bottom:clamp(72px,9vw,120px)">
    <div class="w cats">
        @foreach([['cake','pear','Пирожные','Корпусные с хрустящим шоколадом, картошка, эклеры. Есть без сахара.'],['zefir','zefir-bouquet','Зефир и букеты','На натуральном пюре, сахара вдвое меньше. Букеты и чашки на заказ.'],['pryanik','gingerbread','Пряники','Имбирные, с ручной росписью, к праздникам и школе.'],['tort','cake-strawberry','Торты и бенто','На заказ, начинку выбираете вы.']] as [$cat, $img, $name, $desc])
            <a href="{{ route('catalog', ['cat' => $cat]) }}">
                {{ $tile($img, '', '(min-width: 980px) 22vw, (min-width: 620px) 45vw, 90vw') }}
                <span class="t"><b>{{ $name }}</b><svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                <span class="small">{{ $desc }}</span>
            </a>
        @endforeach
    </div>
</section>

<section class="sec g rv" id="flavors">
    <div class="w">
        <div class="sec-h">
            <div class="k"><span class="eyebrow">Витрина сегодня</span><h2 class="d2">Что есть <em>в наличии</em></h2></div>
            <a class="btn btn-text" href="{{ route('catalog') }}">Весь каталог <svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
        <div class="grid l">
            @foreach($featured as $product)
                @include('partials.card', ['product' => $product, 'sizes' => '(min-width: 980px) 30vw, (min-width: 620px) 45vw, 92vw'])
            @endforeach
        </div>
    </div>
</section>

<section class="sec alt g rv" id="cakes">
    <div class="w split">
        <div class="a">
            <div class="cake-grid">
                <div class="tall"><video data-src="/images/cake-butterflies.mp4" autoplay muted loop playsinline preload="none" aria-label="Торт с бабочками"></video></div>
                {{ $tile('cake-strawberry', 'Торт с клубникой и кремом пломбир') }}
                {{ $tile('bento', 'Бенто-торт с надписью') }}
            </div>
        </div>
        <div class="b">
            <span class="eyebrow">На заказ</span>
            <h2 class="d2">Торты и бенто <em>под ваш повод</em></h2>
            <p class="lead">Расскажите, чем увлекается именинник, а мы придумаем декор. Начинку выбираете сами:</p>
            <ul class="list-lines">
                <li>Ванильный бисквит, кремчиз, клубника</li>
                <li>Шоколадный бисквит, кремчиз, мягкая карамель</li>
                <li>Медовик классический или карамельный</li>
                <li>Красный бархат</li>
            </ul>
            <p class="small">Красный бархат и Вишнёвый шоколад — <b style="color:var(--accent);font-weight:600">2 700 ₽ за кг</b>. Цену бенто и других тортов назовём после обсуждения. Заказывайте минимум за 3 дня.</p>
            <div class="acts">
                <a class="btn btn-main" href="{{ route('delivery') }}#contacts">Обсудить торт</a>
                <span class="hand">«Смех с первого взгляда, любовь с первого кусочка»</span>
            </div>
        </div>
    </div>
</section>

<section class="sec g rv" aria-label="Отзывы">
    <div class="w" style="display:flex;flex-direction:column;gap:40px">
        <div class="k" style="display:flex;flex-direction:column;gap:18px"><span class="eyebrow">Из отзывов гостей</span><h2 class="d2">Лучше любой <em>рекламы</em></h2></div>
        <div class="quotes">
            <blockquote>«Не такой сладкий, как в магазинах, ваууу!»<footer>про зефир: сахара в нём вдвое меньше</footer></blockquote>
            <blockquote>«Торт смели, я еле урвала кусочек!»<footer>торт на день рождения</footer></blockquote>
            <blockquote>«Тортик улетел за 5 минут»<footer>из отзывов в канале</footer></blockquote>
        </div>
        <a class="btn btn-text" style="align-self:flex-start" href="https://yandex.ru/maps/org/zefir_i_pryanik/69698350171/" target="_blank" rel="noopener">Все отзывы на Яндекс Картах <svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    </div>
</section>

<section class="sec g rv" id="visit">
    <div class="w split">
        <div class="b">
            <span class="eyebrow">Кондитерская</span>
            <h2 class="d2">Заходите <em>на кофе</em></h2>
            <p class="lead">Большое окно, витрина с пирожными, картины на стенах. Можно выпить кофе с десертом, поболтать и забрать коробочку домой.</p>
            <div class="facts-row">
                <div><b>ул. Базовская, 17</b><span class="small">Вход рядом со вторым подъездом</span></div>
                <div><b>Вт–Вс, 10:00–21:00</b><span class="small">Понедельник выходной</span></div>
            </div>
            <div><a class="btn btn-line" href="{{ route('delivery') }}">Как добраться</a></div>
        </div>
        <div class="a">
            <div class="photos">
                {{ $tile('hall', 'Зал кондитерской с надписью «Место, где тебя ждут»') }}
                {{ $tile('box', 'Фирменная коробка у входа в кондитерскую вечером') }}
                {{ $tile('showcase', 'Витрина с десертами', '(min-width: 820px) 40vw, 90vw', 'wide') }}
            </div>
        </div>
    </div>
</section>
@endsection
