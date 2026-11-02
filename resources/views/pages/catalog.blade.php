@extends('layouts.app')
@section('title', 'Каталог — Зефир и пряник: пирожные, зефир, пряники, торты')
@section('description', 'Каталог кондитерской «Зефир и пряник»: корпусные пирожные, ПП-десерты без сахара, зефир на натуральном пюре, имбирные пряники, торты на заказ. Москва, ул. Базовская, 17.')
@section('canonical', $cat === 'all' ? route('catalog') : route('catalog', ['cat' => $cat]))

@section('content')
<div class="g" style="padding-block:24px 120px">
    <div class="w" style="display:flex;flex-direction:column;gap:32px">
        <nav class="crumbs" aria-label="Навигация"><a href="{{ route('home') }}">Главная</a><span aria-hidden="true">/</span><span>Каталог</span></nav>

        <div style="display:flex;flex-wrap:wrap;gap:24px 48px;align-items:flex-end;justify-content:space-between">
            <div style="display:flex;flex-direction:column;gap:16px">
                <span class="eyebrow">Витрина на Базовской, 17</span>
                <h1 class="d1b">Каталог</h1>
            </div>
            <p class="lead" style="font-size:17px;line-height:28px;max-width:44ch">Пирожные готовим небольшими партиями, чтобы не залёживались. Зефир, букеты и торты делаем на заказ.</p>
        </div>

        <div class="toolbar">
            <div class="chips" role="group" aria-label="Категория">
                @foreach(\App\Models\Product::CATEGORIES as $id => $label)
                    <a class="chip-b" href="{{ route('catalog', array_filter(['cat' => $id === 'all' ? null : $id, 'sort' => $sort === 'pop' ? null : $sort])) }}" @if($id === $cat) aria-current="true" @endif>{{ $label }}<span>{{ $counts[$id] }}</span></a>
                @endforeach
            </div>
            <form class="sort" method="get" action="{{ route('catalog') }}">
                @if($cat !== 'all')<input type="hidden" name="cat" value="{{ $cat }}">@endif
                <label for="sort">Сортировка</label>
                <select class="sel" id="sort" name="sort" data-autosubmit>
                    <option value="pop" @selected($sort === 'pop')>Сначала популярные</option>
                    <option value="asc" @selected($sort === 'asc')>Сначала дешевле</option>
                    <option value="desc" @selected($sort === 'desc')>Сначала дороже</option>
                </select>
                <noscript><button class="btn btn-line btn-sm" type="submit">Применить</button></noscript>
            </form>
        </div>

        <div class="grid">
            @forelse($products as $product)
                @include('partials.card', ['product' => $product, 'eager' => $loop->index < 2])
            @empty
                <p class="empty">В этой категории пока ничего нет.</p>
            @endforelse
        </div>

        <section class="about" aria-label="О кондитерской">
            <h2>Кондитерская <em style="color:var(--choc-2)">на Базовской</em></h2>
            <p>«Зефир и пряник» — кондитерская в Москве на ул. Базовская, 17. Делаем зефир на натуральном фруктовом пюре, корпусные и ПП-пирожные, имбирные пряники с росписью, зефирные букеты, бенто-торты и торты на заказ. Работаем со вторника по воскресенье с 10:00 до 21:00.</p>
        </section>
    </div>
</div>
@endsection
