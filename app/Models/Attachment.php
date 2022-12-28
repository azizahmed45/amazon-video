<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'product_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
