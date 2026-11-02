<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'Зефир и пряник')</title>
    <meta name="description" content="@yield('description', 'Кондитерская «Зефир и пряник» в Москве: корпусные пирожные, зефир, пряники и торты на заказ. Самовывоз с ул. Базовская, 17 и доставка.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    @hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
    <meta name="theme-color" content="#f6f0e8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#161110" media="(prefers-color-scheme: dark)">
    <meta name="color-scheme" content="light dark">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Зефир и пряник">
    <meta property="og:title" content="@yield('title', 'Зефир и пряник')">
    <meta property="og:description" content="@yield('description', 'Кондитерская «Зефир и пряник» в Москве')">
    <meta property="og:image" content="{{ url('/icons/icon-512.png') }}">
    <meta property="og:locale" content="ru_RU">

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Зефир и пряник">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">

    <script>try{var t=localStorage.getItem('theme');if(t==='dark'||t==='light')document.documentElement.dataset.theme=t}catch(e){}document.documentElement.classList.add('js')</script>
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('resources/fonts/inter-cyrillic-wght-normal.woff2') }}">
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('resources/fonts/inter-latin-wght-normal.woff2') }}">
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('resources/fonts/cormorant-garamond-cyrillic-500-normal.woff2') }}">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class')">
    <a class="skip" href="#main">К содержимому</a>

    <header class="top g"><div class="bar glass">
        <a class="logo" href="{{ route('home') }}" aria-label="Зефир и пряник — на главную">
            <img src="/images/logo-120.webp" srcset="/images/logo-120.webp 1x, /images/logo-240.webp 2x" alt="" width="40" height="40"><span>Зефир <i>и</i> пряник</span>
        </a>
        <nav aria-label="Разделы">
            <a href="{{ route('catalog') }}" @if(request()->routeIs('catalog', 'product')) aria-current="page" @endif>Каталог</a>
            <a href="{{ route('home') }}#cakes">Торты на заказ</a>
            <a href="{{ route('delivery') }}" @if(request()->routeIs('delivery')) aria-current="page" @endif>Доставка и контакты</a>
        </nav>
        <div class="tools">
            <span class="open-now" data-open-now data-open="{{ config('shop.hours.open') }}" data-close="{{ config('shop.hours.close') }}" data-closed="{{ implode(',', config('shop.hours.closed_days')) }}"></span>
            <button class="ib" id="themeBtn" type="button" aria-label="Сменить тему"><svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 1 1 9.5 4 6.5 6.5 0 0 0 20 14.5z"/></svg></button>
            <button class="ib" type="button" data-open-cart aria-label="Открыть корзину"><svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 8h14l-1.2 11.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg><span class="n tn" data-island="cart-count" hidden>0</span></button>
        </div>
    </div></header>

    <main id="main" tabindex="-1">
        @yield('content')
    </main>

    <footer class="site g"><div class="w">
        <div style="display:flex;flex-direction:column;gap:10px">
            <img src="/images/logo-120.webp" srcset="/images/logo-120.webp 1x, /images/logo-240.webp 2x" alt="Зефир и пряник" width="120" height="97" loading="lazy">
            <span class="small">Кондитерская ручной работы · {{ config('shop.address') }}</span>
            <div data-island="install" data-lazy="idle"></div>
        </div>
        <nav aria-label="Мы в сети">
            <a href="{{ config('shop.telegram') }}">Telegram</a><a href="{{ config('shop.vk') }}">ВКонтакте</a><a href="{{ config('shop.max') }}">MAX</a><a href="{{ config('shop.flowwow') }}">Flowwow</a>
        </nav>
    </div>
    <div class="w legal">
        <p style="font-weight:600">ИНДИВИДУАЛЬНЫЙ ПРЕДПРИНИМАТЕЛЬ ГУБАНОВА ТАТЬЯНА СЕРГЕЕВНА</p>
        <p>ОГРНИП 323774600785438 от <a href="https://zachestnyibiznes.ru/day/2023-11-28">28.11.2023</a> · ИНН 772442265466</p>
        <p style="margin-top:8px">Сайт не является публичной офертой, цены представлены для ознакомления и могут быть изменены в одностороннем порядке</p>
        <p style="margin-top:8px">© {{ date('Y') }} Зефир и пряник. Москва. Все права защищены.</p>
    </div></footer>

    <div data-island="cart-drawer" data-lazy="idle"></div>
    <div class="toast glass" id="toast" role="status" aria-live="polite"><span class="dot"><svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="m5 12 5 5 9-10"/></svg></span><span id="toastText"></span></div>
</body>
</html>
