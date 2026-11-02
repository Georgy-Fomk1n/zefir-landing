@extends('layouts.app')
@section('title', 'Доставка и контакты — Зефир и пряник, Москва, Базовская, 17')
@section('description', 'Самовывоз с ул. Базовская, 17 и доставка по Москве. Часы работы: вторник–воскресенье, 10:00–21:00. Телефон, Telegram, ВКонтакте, Flowwow.')

@section('content')
<div class="g" style="padding-block:24px 120px">
        <div class="w" style="display:flex;flex-direction:column;gap:72px">
            <div style="display:flex;flex-direction:column;gap:16px">
                <nav class="crumbs" aria-label="Навигация"><a href="{{ route('home') }}">Главная</a><span aria-hidden="true">/</span><span>Доставка и контакты</span></nav>
                <h1 class="d1b">Доставка <em>и контакты</em></h1>
            </div>

            <div class="cols3">
                <section>
                    <span class="v">0 ₽</span>
                    <h2>Самовывоз</h2>
                    <p style="color:var(--choc-2)">ул. Базовская, 17. Соберём утром в день выдачи, забрать можно в выбранный час.</p>
                    <p class="small">Вт–Вс, 10:00–21:00, понедельник выходной. Вход рядом со вторым подъездом.</p>
                    <span class="status" data-status role="status"><span class="dot"></span><span data-status-text></span></span>
                </section>
                <section>
                    <span class="v">390 ₽</span>
                    <h2>Курьером</h2>
                    <p style="color:var(--choc-2)">По Москве в пределах МКАД. Везём с хладоэлементом.</p>
                    <p class="small">Ещё нас можно заказать через <a href="{{ config('shop.flowwow') }}" style="color:var(--accent)">Flowwow</a>. Доставку за МКАД уточняйте по телефону.</p>
                </section>
                <section>
                    <span class="v">Карта · наличные</span>
                    <h2>Оплата</h2>
                    <p style="color:var(--choc-2)">В кондитерской картой или наличными. Онлайн-оплату на сайте подключаем, пока уточняйте способ у нас.</p>
                </section>
            </div>

            <div class="map-row" id="contacts">
                <div class="map"><iframe src="https://yandex.ru/map-widget/v1/?ll=37.511840%2C55.880223&mode=search&oid=69698350171&ol=biz&z=16.62" title="Карта: Зефир и пряник, ул. Базовская, 17" loading="lazy" allowfullscreen></iframe></div>
                <section class="contacts" aria-label="Контакты">
                    <h2>Связаться</h2>
                    <a class="contact" href="{{ config('shop.phone_href') }}"><span class="ic"><svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="stroke-width:1.5"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" /></svg></span><span style="flex:1"><span class="nm">Позвонить</span><small class="tn">{{ config('shop.phone') }}</small></span></a>
                    <a class="contact" href="{{ config('shop.telegram') }}" target="_blank" rel="noopener"><span class="ic"><svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="stroke-width:1.5"><path d="M21 4L3 11l6 2.5L11 20l3-4.5 5 3.5z" /></svg></span><span style="flex:1"><span class="nm">Написать в Telegram</span><small>@Krasolda_Tihonovna</small></span></a>
                    <a class="contact" href="{{ config('shop.flowwow') }}" target="_blank" rel="noopener"><span class="ic"><svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="stroke-width:1.5"><path d="M12 20c-4-3-7-6-7-9.5A4 4 0 0 1 12 8a4 4 0 0 1 7 2.5C19 14 16 17 12 20z" /></svg></span><span style="flex:1"><span class="nm">Магазин на Flowwow</span><small>flowwow.com/shop/zefir-i-pryanik</small></span></a>
                    <a class="contact" href="{{ config('shop.vk') }}" target="_blank" rel="noopener"><span class="ic">VK</span><span style="flex:1"><span class="nm">ВКонтакте</span><small>vk.ru/zefir_i_pryanik.moscow</small></span></a>
                    <a class="contact" href="{{ config('shop.max') }}" target="_blank" rel="noopener"><span class="ic" style="font-size:11px">MAX</span><span style="flex:1"><span class="nm">Мы в MAX</span><small>max.ru/id772442265466_biz</small></span></a>
                </section>
            </div>

            <section class="faq-w" aria-label="Частые вопросы">
                <h2>Частые <em style="color:var(--choc-2)">вопросы</em></h2>
                <div class="faq">
                    <details open><summary>Можно заказать к определённому часу?<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></summary><p>Да. При оформлении выберите день и часовой слот.</p></details>
                    <details><summary>Сколько хранятся пирожные?<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></summary><p>72 часа в холодильнике, в закрытой коробке.</p></details>
                    <details><summary>Есть десерты без сахара?<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></summary><p>Да. ПП-пирожные на шоколаде без сахара: банан, черника и другие вкусы. Зефир делаем с пониженным содержанием сахара.</p></details>
                    <details><summary>Сколько готовится зефирный букет?<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></summary><p>Зефир готовится около двух суток, время зависит от влажности. Закажите букет заранее.</p></details>
                    <details><summary>Можно выбрать декор и надпись?<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></summary><p>Да, доступно индивидуальное оформление, надпись и персональный декор. Открытку от руки добавим бесплатно.</p></details>
                    <details><summary>За какой срок оформить торт или заказ для компании?<svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg></summary><p>Рекомендуем минимум за 3 дня. Корпоративные заказы и брендированное оформление обсуждаем индивидуально, напишите или позвоните нам.</p></details>
                </div>
            </section>
        </div>
    </div>
@endsection
