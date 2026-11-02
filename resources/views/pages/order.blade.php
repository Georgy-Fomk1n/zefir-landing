@extends('layouts.app')
@section('title', 'Заказ оформлен — Зефир и пряник')
@section('robots', 'noindex')
@section('canonical', route('order', $order->token))

@section('content')
<div class="g" style="padding-block:48px 120px">
    <div class="done">
        <div class="a">
            <img src="/images/logo-240.webp" alt="Зефир и пряник" width="160" height="130">
            <span class="stamp"><svg class="i" aria-hidden="true" viewBox="0 0 24 24" style="width:16px;height:16px;stroke-width:2"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Заказ принят</span>
            <h1 class="d1b" style="font-size:clamp(44px,5.5vw,76px)">Спасибо, {{ $order->name }}! <em>Заказ принят.</em></h1>
            <p class="lead">Номер заказа <b class="tn" style="color:var(--choc);font-weight:600">№&nbsp;{{ $order->number() }}</b>. Свяжемся с вами по номеру <span class="tn" style="white-space:nowrap">{{ $order->phone }}</span>, чтобы подтвердить заказ. Оплата при получении.</p>

            <div data-island="push-order" data-lazy="idle" data-props="{{ json_encode(['token' => $order->token, 'vapid' => config('shop.vapid.public')]) }}"></div>

            <div class="facts-row">
                <div><span class="small" style="letter-spacing:.06em;text-transform:uppercase">Когда</span><b>{{ $order->slot_date->translatedFormat('j F') }}</b><span class="tn">{{ $order->slot_hour }}:00–{{ $order->slot_hour + 1 }}:00</span></div>
                <div><span class="small" style="letter-spacing:.06em;text-transform:uppercase">Где</span><b>{{ $order->shipping === 'courier' ? 'Курьером' : 'Самовывоз' }}</b><span>{{ $order->address }}</span></div>
            </div>
            <div class="acts" style="margin-top:8px">
                <a class="btn btn-main" href="{{ route('catalog') }}">Вернуться в каталог</a>
                <a class="btn btn-text" href="{{ route('delivery') }}">Как нас найти <svg class="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            </div>
        </div>
        <aside class="b" aria-label="Состав заказа">
            <h2 style="font:500 28px/1.1 var(--display);margin-bottom:4px">Ваш заказ</h2>
            <div style="display:flex;flex-direction:column;gap:14px">
                @foreach($order->items as $it)
                    <div class="thumb-line"><img src="{{ \App\Support\Img::src($it->image, 320) }}" alt="" width="52" height="64"><span>{{ $it->name }} × {{ $it->qty }}</span><span class="tn">{{ rub($it->sum) }}</span></div>
                @endforeach
            </div>
            @if($order->card_text)<div class="sline" style="padding-top:14px;border-top:1px solid var(--line)"><span>Открытка от руки</span><span style="color:var(--muted)">Бесплатно</span></div>@endif
            @if($order->fee)<div class="sline"><span>Доставка</span><span>{{ rub($order->fee) }}</span></div>@endif
            <div class="sum tot" style="align-items:baseline"><span>Итого</span><span>{{ rub($order->total) }}</span></div>
        </aside>
    </div>
</div>
@endsection
