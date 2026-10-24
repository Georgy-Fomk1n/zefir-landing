<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMedia extends Model
{
    public $timestamps = false;

    protected $table = 'product_media';

    protected $fillable = ['product_id', 'type', 'name', 'alt', 'sort'];
}
