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

    $response = AmazonProduct::search('All', "Electric Cooker", 1);
    $items = $response['SearchResult']['Items'];


    return \App\Http\Controllers\VideoMakerController::generateScript($items[3]["ItemInfo"]["Title"]["DisplayValue"], $items[3]["ItemInfo"]["Features"]["DisplayValues"], $items[3]["Offers"]["Listings"][0]["Price"]["DisplayAmount"]);



});
