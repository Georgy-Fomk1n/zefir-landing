<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Support\Img;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->getStateUsing(fn (Product $p) => Img::src($p->image, 320))->square()->size(56),
                TextColumn::make('name')->label('Название')->searchable()->description(fn (Product $p) => $p->short),
                TextColumn::make('category')->label('Раздел')->badge()->formatStateUsing(fn ($state) => Product::CATEGORIES[$state] ?? $state),
                TextColumn::make('price')->label('Цена')->state(fn (Product $p) => $p->priceText())->sortable(),
                ToggleColumn::make('out_of_stock')->label('Нет в наличии'),
                ToggleColumn::make('active')->label('На сайте'),
            ])
            ->filters([
                SelectFilter::make('category')->label('Раздел')->options(collect(Product::CATEGORIES)->except('all', 'pp')->all()),
            ])
            ->recordActions([EditAction::make()->label('Изменить')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->emptyStateHeading('Товаров пока нет');
    }
}
