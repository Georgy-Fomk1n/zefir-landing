<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Support\Img;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['slug' => 'pear', 'img' => 'pear', 'category' => 'cake', 'name' => 'Груша', 'is_new' => true, 'short' => 'Корпусное: шоколадный корпус, мусс, грушевая начинка'],
            ['slug' => 'apple', 'img' => 'apple', 'category' => 'cake', 'name' => 'Яблоко и корица', 'is_new' => true, 'short' => 'Корпусное: ганаш на яблочном пюре, яблоки с корицей'],
            ['slug' => 'pistachio', 'img' => 'pistachio', 'category' => 'cake', 'name' => 'Фисташка', 'is_new' => true, 'price' => 280,
                'short' => 'Финансье, фисташковая намелака, хрустящий слой, ганаш',
                'long' => 'Внутри фисташковый бисквит финансье, фисташковая намелака, хрустящий слой и нежнейший ганаш. Сверху тонкий шоколад, который ломается с хрустом.',
                'allergens' => 'Орехи, молоко, глютен',
                'variants' => [['Одно пирожное', '110 г', 280], ['Набор из четырёх', 'В коробке с лентой', 1050], ['Набор из шести', 'В коробке с лентой', 1550]]],
            ['slug' => 'passion', 'img' => 'passion', 'category' => 'cake', 'name' => 'Маракуйя', 'short' => 'Хрустящий корпус, сливочный крем, яркая маракуйя'],
            ['slug' => 'pp-banana', 'img' => 'pp-banana', 'category' => 'cake', 'is_pp' => true, 'name' => 'ПП-банан', 'price' => 270, 'short' => 'Шоколадный мусс, банановая начинка, корпус на шоколаде без сахара'],
            ['slug' => 'pp-blueberry', 'img' => 'pp-blueberry', 'category' => 'cake', 'is_pp' => true, 'name' => 'ПП-черника', 'out_of_stock' => true, 'short' => 'Без сахара, 209 ккал'],
            ['slug' => 'potato', 'img' => 'potato', 'category' => 'cake', 'name' => 'Картошка в шоколаде', 'price' => 210, 'short' => 'Бисквит, сливочный крем, хрустящее покрытие'],
            ['slug' => 'eclair', 'img' => 'eclair', 'category' => 'cake', 'name' => 'Эклер классический', 'price' => 200, 'short' => 'Крем Шарлотт, шоколадная глазурь'],
            ['slug' => 'tart', 'img' => 'tart', 'category' => 'cake', 'name' => 'Тарт лимонный', 'price' => 250, 'short' => 'Лимонный крем и меренга'],
            ['slug' => 'zefir-berry', 'img' => 'zefir', 'category' => 'zefir', 'name' => 'Зефир крыжовник и клубника', 'short' => 'На натуральном пюре, сахара вдвое меньше'],
            ['slug' => 'zefir-choc', 'img' => 'zefir-choc', 'category' => 'zefir', 'name' => 'Зефир в шоколаде', 'short' => 'Смородина с клубникой или клюква с черникой'],
            ['slug' => 'zefir-mushrooms', 'img' => 'zefir-mushrooms', 'category' => 'zefir', 'name' => 'Зефирные грибочки', 'short' => 'Набор в коробке'],
            ['slug' => 'zefir-bouquet', 'img' => 'zefir-bouquet', 'category' => 'zefir', 'name' => 'Зефирный букет', 'on_order' => true, 'short' => 'Готовим около двух суток'],
            ['slug' => 'gingerbread', 'img' => 'gingerbread', 'category' => 'pryanik', 'name' => 'Имбирные пряники', 'short' => 'Мягкие внутри, с глазурью и росписью'],
            ['slug' => 'nuts', 'img' => 'nuts', 'category' => 'gift', 'name' => 'Орехи в шоколаде', 'price' => 300, 'price_from' => true, 'short' => '4 шт. — 300 ₽, 6 шт. — 600 ₽, 12 шт. — 1 000 ₽',
                'variants' => [['4 штуки', '', 300], ['6 штук', '', 600], ['12 штук', '', 1000]]],
            ['slug' => 'bento', 'img' => 'bento', 'category' => 'tort', 'name' => 'Бенто-торт', 'on_order' => true, 'short' => 'Мини-торт с надписью, начинка на выбор'],
            ['slug' => 'red-velvet', 'img' => 'red-velvet', 'category' => 'tort', 'name' => 'Красный бархат', 'on_order' => true, 'price_note' => '2 700 ₽/кг', 'short' => 'Торт с клубникой, можно кусочком'],
        ];

        foreach ($items as $i => $it) {
            $variants = $it['variants'] ?? [];
            $image = Img::named($it['img']);
            unset($it['variants'], $it['img']);
            $p = Product::updateOrCreate(['slug' => $it['slug']], $it + ['image' => $image, 'sort' => $i * 10, 'active' => true]);
            $p->variants()->delete();
            foreach ($variants as $j => [$label, $sub, $price]) {
                $p->variants()->create(['label' => $label, 'sub' => $sub, 'price' => $price, 'sort' => $j]);
            }
        }
    }
}
