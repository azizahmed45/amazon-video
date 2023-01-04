<?php

use App\Http\Controllers\VideoMakerController;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SsmlVoiceGender;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Revolution\Amazon\ProductAdvertising\Facades\AmazonProduct;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;


Route::get("test1", function () {


    $keword = \App\Models\Keyword::query()->find(23);

     VideoMakerController::generateThumbnail($keword);

     return "test";
//    VideoMakerController::mergeProductsVideo($keword->products,$keword);
//
//return "done";

    $keywordText = "Stylish Women Watches";

    //delete all attached files
    $attachmentList = \App\Models\Attachment::query()->where("type", "!=", "final_video")->get();
    foreach ($attachmentList as $attachment) {
        $realPath = storage_path($attachment->name);
        if (File::exists($realPath)) {
            File::delete($realPath);
        }
    }

    $data = VideoMakerController::getItems($keywordText);
    $products = VideoMakerController::saveProducts($data['items'], $data['keyword'], 5);
    VideoMakerController::generateIntro($data['keyword']);

    foreach ($products as $product) {
        VideoMakerController::generatePrimaryImage($product);
        VideoMakerController::generateImagesFromProduct($product);
        VideoMakerController::generateAudioScript($product);
        VideoMakerController::generateVideoForProduct($product);
    }

    VideoMakerController::mergeProductsVideo($products, $data['keyword']);

    return "Done";

    return 6%5;

    $response = AmazonProduct::search('All', "Television", 1);
    $items = $response['SearchResult']['Items'];


    $string =  \App\Http\Controllers\VideoMakerController::generateScript($items[3]["ItemInfo"]["Title"]["DisplayValue"], $items[3]["ItemInfo"]["Features"]["DisplayValues"], $items[3]["Offers"]["Listings"][0]["Price"]["DisplayAmount"]);

    $array = preg_split('/[.!?]\s+/', $string);
    $array = array_map('trim', $array);

    \App\Http\Controllers\VideoMakerController::generateAudio($string);

    print_r($items[3]["ItemInfo"]["Features"]["DisplayValues"]);
    print_r($array);

    return $array;

});

Route::get("test2", function () {

});
