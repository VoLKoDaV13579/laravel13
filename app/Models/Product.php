<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'price', 'stock'])]
class Product extends Model
{
    use HasFactory;
    public function isInStock(int $quantity = 1): bool
    {
        return $this->stock >= $quantity;
    }
}
