<?php

namespace App\Support;

use App\Models\SaleItem;
use App\Models\StockEntryItem;

final class StockBalance
{
    public static function received(int $productId): int
    {
        return (int) StockEntryItem::query()->where('product_id', $productId)->sum('cartons');
    }

    public static function sold(int $productId): int
    {
        return (int) SaleItem::query()->where('product_id', $productId)->sum('cartons');
    }

    public static function available(int $productId): int
    {
        return self::received($productId) - self::sold($productId);
    }
}
