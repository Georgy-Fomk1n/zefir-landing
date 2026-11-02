@extends('layouts.app')
@section('title', 'Оформление заказа — Зефир и пряник')
@section('robots', 'noindex')
@section('canonical', route('checkout'))

@section('content')
<div class="g" style="padding-block:40px 120px">
    <div data-island="checkout" data-lazy="eager" data-props="{{ json_encode(['phone' => config('shop.phone'), 'address' => config('shop.address'), 'fee' => config('shop.courier_fee')], JSON_UNESCAPED_UNICODE) }}">
        <div class="w"><h1 class="d1b" style="font-size:clamp(44px,5vw,72px)">Оформление <em>без регистрации</em></h1><p class="lead" style="margin-top:24px">Загружаем корзину…</p></div>
    </div>
</div>
@endsection
