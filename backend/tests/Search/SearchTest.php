<?php

// Поиск в отдельном наборе: тесты здесь коммитят данные (DatabaseTruncation), потому что FULLTEXT-индекс
// InnoDB не видит строк из незакоммиченной транзакции RefreshDatabase. На SQLite проверяется ветка с LIKE.

beforeEach(fn () => seedCatalog());

// На MySQL данные закоммичены: чистим, чтобы следующий тест с RefreshDatabase в этом процессе начал с пустой базы
afterEach(function () {
    if (method_exists($this, 'truncateDatabaseTables')) {
        $this->truncateDatabaseTables();
    }
});

it('ищет товары по началу слова в названии и описании', function () {
    $this->getJson('/api/catalog/products?q=фисташ')->assertOk()->assertJsonPath('data.0.slug', 'fistashka');
    $this->getJson('/api/catalog/products?q=Фисташка')->assertOk()->assertJsonPath('data.0.slug', 'fistashka');
    $this->getJson('/api/catalog/products?q=маскарпоне')->assertOk()->assertJsonPath('data.0.slug', 'kofe');
});

it('не находит лишнего и не падает на спецсимволах', function () {
    $this->getJson('/api/catalog/products?q=несуществующий')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/catalog/products?q='.urlencode('фисташ* +(")'))->assertOk()->assertJsonPath('data.0.slug', 'fistashka');
});
