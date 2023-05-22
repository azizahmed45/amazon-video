<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FirstTest extends DuskTestCase
{
    /**
     * A Dusk test example.
     *
     * @return void
     */
    public function testExample()
    {
//        $this->browse(function (Browser $browser) {
//            $browser->visit('https://www.amazon.com/Magnetically-Attachable-Rejection-Writing-Compatible/dp/B09P1BLW9B/ref=sr_1_2_sspa?keywords=stylus%20pen%20for%20ipad&sr=8-2-spons&psc=1&spLa=ZW5jcnlwdGVkUXVhbGlmaWVyPUExWVVQVDJKWEY4SlA1JmVuY3J5cHRlZElkPUEwNzQ5NjM5M0ZJVjFMRkZWTDQ3QSZlbmNyeXB0ZWRBZElkPUEwODAzMDEwUEJGVU9CMFk5NU9DJndpZGdldE5hbWU9c3BfYXRmJmFjdGlvbj1jbGlja1JlZGlyZWN0JmRvTm90TG9nQ2xpY2s9dHJ1ZQ%3D%3D&fbclid=IwAR35tYl_gAj-i6pQMpdy4kTIDzQUr_Kmz4M6a-HIq-lH8A5InqPjlG9cIrs');
//            $source = $browser->driver->getPageSource();
//            try {
//                //generate a patter to capture string between "var obj = jQuery.parseJSON('{" and "');"
//                $pattern = '/var obj = jQuery.parseJSON\(\'\{(.*)}\'\);/';
//                preg_match($pattern, $source, $matches);
//
//                //capture only json string by regex
//                $jsonString = $matches[0];
//                $jsonString = str_replace("var obj = jQuery.parseJSON('", "", $jsonString);
//                $jsonString = str_replace("');", "", $jsonString);
//
//                //convert json string to array
//                $jsonArray = json_decode($jsonString, true);
//
//                $videos = $jsonArray['videos'];
//
//                //download video
//                foreach ($videos as $key => $video) {
//
//                    $url = $video['url'];
//                    $path = storage_path("app/videos/$key.mp4");
//                    file_put_contents($path, file_get_contents($url));
//                    Log::info("Downloaded");
//
//                }
//
//            } catch (\Exception $e) {
//                Log::info($e->getMessage());
//            }
//        });

        $this->browse(function (Browser $browser) {
            $browser->visit("https://www.amazon.com")
                ->type('field-keywords', 'iphone')
                ->press('Go')
                ->waitFor('[data-index="3"]')
                ->click('[data-index="3"]')
                ->waitForText('aaaaaaaaaaaaaa');
        });

    }
}
