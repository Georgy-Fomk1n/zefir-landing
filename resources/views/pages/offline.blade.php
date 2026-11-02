@extends('layouts.app')
@section('title', 'Нет соединения — Зефир и пряник')
@section('robots', 'noindex')

@section('content')
<div class="g" style="padding-block:64px 120px">
    <div class="w" style="display:flex;flex-direction:column;gap:24px;align-items:flex-start">
        <img src="/images/logo-240.webp" alt="" width="140" height="114">
        <h1 class="d1b">Нет <em>соединения</em></h1>
        <p class="lead">Страница не загрузилась: пропал интернет. Уже открытые страницы доступны, а оформить заказ получится, когда связь вернётся. Или позвоните нам: <a href="{{ config('shop.phone_href') }}" style="color:var(--accent)">{{ config('shop.phone') }}</a>.</p>
        <button class="btn btn-main" type="button" onclick="location.reload()">Попробовать снова</button>
    </div>
</div>
@endsection
