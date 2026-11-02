<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основное')->columns(2)->schema([
                TextInput::make('name')->label('Название')->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set('slug', Str::slug($state))),
                TextInput::make('slug')->label('Адрес страницы')->helperText('Латиницей, например pistachio. Не меняйте у готового товара: сломаются ссылки.')
                    ->required()->unique(ignoreRecord: true)->alphaDash()->maxLength(80),
                Select::make('category')->label('Раздел')->required()->options(collect(Product::CATEGORIES)->except('all', 'pp')->all()),
                TextInput::make('short')->label('Короткое описание')->helperText('Одна строка под названием в каталоге.')->maxLength(200),
                Textarea::make('long')->label('Полное описание')->helperText('Показывается на странице товара. Если пусто, берётся короткое.')->rows(3)->columnSpanFull(),
                TextInput::make('allergens')->label('Аллергены')->maxLength(200),
            ]),

            Section::make('Цена')->description('Пусто = «цена по запросу»: товар виден, но в корзину не кладётся.')->columns(3)->schema([
                TextInput::make('price')->label('Цена, ₽')->numeric()->minValue(0)->suffix('₽'),
                Toggle::make('price_from')->label('Показывать «от»')->inline(false),
                TextInput::make('price_note')->label('Подпись вместо цены')->helperText('Например «2 700 ₽/кг». Заменяет цену на витрине.'),
                Repeater::make('variants')->label('Форматы (необязательно)')->relationship()->columnSpanFull()
                    ->helperText('Одно пирожное / набор из четырёх. Если форматов нет, продаётся по цене выше.')
                    ->orderColumn('sort')->reorderable()->collapsible()->addActionLabel('Добавить формат')
                    ->schema([
                        TextInput::make('label')->label('Название')->required()->maxLength(80),
                        TextInput::make('sub')->label('Пояснение')->maxLength(80),
                        TextInput::make('price')->label('Цена, ₽')->required()->numeric()->minValue(1)->suffix('₽'),
                    ])->columns(3)->defaultItems(0),
            ]),

            Section::make('Витрина')->columns(3)->schema([
                Toggle::make('active')->label('Показывать на сайте')->default(true),
                Toggle::make('is_new')->label('Метка «Новинка»'),
                Toggle::make('is_pp')->label('Без сахара'),
                Toggle::make('out_of_stock')->label('Нет в наличии'),
                Toggle::make('on_order')->label('Только на заказ'),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0)->helperText('Меньше = выше в каталоге.'),
            ]),

            Section::make('Фото')->schema([
                FileUpload::make('upload')->label('Фотография')->image()->disk('uploads')->directory('products')->visibility('public')
                    ->maxSize(12288)->helperText('Лучше вертикальная 3:4. Сайт сам сделает лёгкие версии для телефона и компьютера. Если не загружать новое, останется текущее фото.'),
            ]),
        ]);
    }
}
