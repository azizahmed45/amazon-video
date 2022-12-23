<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Revolution\Amazon\ProductAdvertising\Facades\AmazonProduct;

class VideoMakerController extends Controller
{

    public static function getItems($keyword){
        $response = AmazonProduct::search('All', $keyword, 1);
        $items = $response['SearchResult']['Items'];
        return $items;
    }
    public static function generateImagesFromItem($item)
    {

    }

    //generate amazon affiliate link from asin
    //short the link using amazon affiliate link shortener
    public static function generateAffiliateLink($asin)
    {
        $asin = "B00EOE0WKQ";
        $affiliate_id = "myaffiliateid";
        $affiliate_link = "https://amzn.to/" . $asin . "?tag=" . $affiliate_id;

        return $affiliate_link;
    }

}
