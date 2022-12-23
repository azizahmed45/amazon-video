<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Revolution\Amazon\ProductAdvertising\Facades\AmazonProduct;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;


Route::get("test1", function () {
    $keyword = "Iphone";
    $image = Image::make(storage_path('app/template/title-template.jpg'));
    $image->text(strtoupper("Top Five $keyword"), 500, 300, function ($font) {
        $font->file(storage_path("app/fonts/title-font.ttf"));
        $font->size(50);
        $font->align('center');
        $font->valign('center');
        $font->color('#FFFFFF');
    });

    $image->save(storage_path('app/images/title-template.jpg'));
});
