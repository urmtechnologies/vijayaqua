<?php

namespace App\Support;

use App\Models\SaleItem;
use App\Models\StockEntryItem;
use App\Models\VehicleEntryItem;

final class StockBalance
{
    public static function received(int $productId): int
    {
        return (int) StockEntryItem::query()->where('product_id', $productId)->whereHas('entry')->sum('cartons');
    }

    public static function sold(int $productId): int
    {
        return (int) SaleItem::query()->where('product_id', $productId)->whereHas('sale')->sum('cartons');
    }

    public static function available(int $productId): int
    {
        return self::received($productId) - self::sold($productId) - self::dispatched($productId);
    }

    public static function dispatched(int $productId): int
    {
        return (int) VehicleEntryItem::query()->where('product_id', $productId)->whereHas('entry')->sum('cartons');
    }
}
