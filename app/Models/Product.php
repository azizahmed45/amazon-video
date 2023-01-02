<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'primary_image_url',
        'features',
        'price',
        'image_urls',
        'scripts',
        'keyword_id',
        'url'
    ];

    protected $casts = [
        'features' => 'array',
        'image_urls' => 'array',
        'scripts' => 'array'
    ];

    public function keyword()
    {
        return $this->belongsTo(Keyword::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
