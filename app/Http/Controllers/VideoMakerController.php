<?php

namespace App\Http\Controllers;

use App\Console\Commands\GenerateVideo;
use App\Models\Keyword;
use App\Models\Product;
use Exception;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Revolution\Amazon\ProductAdvertising\Facades\AmazonProduct;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class VideoMakerController extends Controller
{

    public static function getItems($keyword)
    {
        $keywordModel = Keyword::create([
            'keyword' => $keyword
        ]);
        $response = AmazonProduct::search('All', $keyword, 1);
        $items = $response['SearchResult']['Items'];
        return ["items" => $items, "keyword" => $keywordModel];
    }

    public static function saveProducts($items, $keyword, $count)
    {
        $products = [];
        for ($i = 0; $i < 10 && count($products) < $count; $i++) {
            try {
                //add image url to array
                $image_urls = [];
                foreach ($items[$i]["Images"]["Variants"] as $image) {
                    $image_urls[] = $image["Large"]["URL"];
                }

                $product = Product::create([
                    'title' => $items[$i]["ItemInfo"]["Title"]["DisplayValue"],
                    'url' => $items[$i]["DetailPageURL"],
                    'features' => $items[$i]["ItemInfo"]["Features"]["DisplayValues"],
                    'primary_image_url' => $items[$i]["Images"]["Primary"]["Large"]["URL"],
                    'image_urls' => $image_urls,
                    'price' => $items[$i]["Offers"]["Listings"][0]["Price"]["DisplayAmount"],
                    'keyword_id' => $keyword->id,
                    'asin' => $items[$i]["ASIN"],
                ]);

                //generate scripts
                $product->scripts = self::generateScript($product->title, $product->features, $product->price);
                $product->save();

            } catch (Exception $e) {
                Log::error($e);
                continue;
            }
            $products[] = $product;
        }
        return $products;
    }

    public static function generateIntro(Keyword $keyword)
    {
        $image = Image::make(storage_path('app/template/title-template.jpg'));
        $title = "Top Five Best $keyword->keyword";
        $image->text(strtoupper(wordwrap($title, 50)), 500, 300, function ($font) use ($title) {
            $font->file(storage_path("app/fonts/title-font.ttf"));
            $font->size(1400 / strlen($title));
            $font->align('center');
            $font->valign('center');
            $font->color('#FFFFFF');
        });

        //generate unique name for image
        $image_path = 'app/images/' . Str::uuid() . ".jpg";
        $image->resize(1280, 720);
        $image->save(storage_path($image_path));

        //save attachment to database
        $keyword->attachments()->create([
            'name' => $image_path,
            'type' => 'intro_image'
        ]);

        //generate audio
        $audio_path = "app/audio/" . Str::uuid() . ".mp3";
        self::generateAudio($title, $audio_path);

        //save attachment to database
        $keyword->attachments()->create([
            'name' => $audio_path,
            'type' => 'intro_audio'
        ]);


        $video_path = "app/videos/" . Str::uuid() . ".mp4";

        $ffmpegCommand = env('FFMPEG_BINARIES') . " -loop 1 -i " . storage_path($image_path) . " -i " . storage_path($audio_path) . " -c:v libx264 -tune stillimage -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -shortest -fflags shortest -max_interleave_delta 100M " . storage_path($video_path);

        $process = Process::fromShellCommandline($ffmpegCommand);
        $process->setTimeout(1000000);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        //save attachment to database
        $keyword->attachments()->create([
            'name' => $video_path,
            'type' => 'intro_video'
        ]);

        echo $process->getOutput();
    }

    public static function generatePrimaryImage(Product $product)
    {
        $primaryTemplate = Image::make(storage_path('app/template/primary_image.jpg'));
        $primaryImage = Image::make($product->primary_image_url);

        $primaryImage->resize(null, 400, function ($constraint) {
            $constraint->aspectRatio();
        });

        $primaryTemplate->insert($primaryImage, 'top-left', 150, 30);
        $primaryTemplate->text(wordwrap($product->title, 70), 100, 500, function ($font) {
            $font->file(storage_path("app/fonts/ArchivoBlack-Regular.ttf"));
            $font->size(20);
            $font->color('#008037');
        });

        $primaryTemplate->text($product->price, 775, 355, function ($font) use ($product) {
            $font->file(storage_path("app/fonts/ChangaOne-Regular.ttf"));
            $font->size(220 / strlen($product->price));
            $font->color('#00C2CB');
        });

        $primaryTemplate->resize(1280, 720);

        //generate unique name for image
        $image_path = 'app/images/' . Str::uuid() . ".jpg";

        $primaryTemplate->save(storage_path($image_path));

        //save attachment to database
        $product->attachments()->create([
            'name' => $image_path,
            'type' => 'primary_image'
        ]);
    }

    public static function generateImagesFromProduct(Product $product)
    {
        //iterate through large array of images or scripts
        $script_count = count($product->scripts);
        $image_count = count($product->image_urls);

        foreach ($product->scripts as $index => $script) {
            $template = Image::make(storage_path('app/template/product_image.jpg'));

            $image = Image::make($product->image_urls[$index % $image_count]);

            $image->resize(400, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            $template->insert($image, 'left', 50, 0);

            $template->text(wordwrap($script, 25), 550, 120, function ($font) {
                $font->file(storage_path("app/fonts/ABeeZee-Regular.ttf"));
                $font->size(25);
                $font->color('#008037');
            });

            $template->resize(1280, 720);
            //generate unique name for image
            $image_path = 'app/images/' . Str::uuid() . ".jpg";
            $template->save(storage_path($image_path));

            //save attachment to database
            $product->attachments()->create([
                'name' => $image_path,
                'type' => 'image'
            ]);
        }

    }

    public static function generateAudioScript(Product $product)
    {
        //title audio
        $title_audio_path = 'app/audio/' . Str::uuid() . ".mp3";
        self::generateAudio($product->title . " in just " . $product->price, $title_audio_path);
        $product->attachments()->create([
            'name' => $title_audio_path,
            'type' => 'title_audio'
        ]);

        foreach ($product->scripts as $index => $script) {
            $audio_path = 'app/audio/' . Str::uuid() . ".mp3";
            self::generateAudio($script, $audio_path);
            $product->attachments()->create([
                'name' => $audio_path,
                'type' => 'audio'
            ]);
        }
    }

    public static function generateVideoForProduct(Product $product)
    {

        $audios = $product->attachments()->whereIn('type', ['title_audio', 'audio'])->get();
        $images = $product->attachments()->whereIn('type', ['primary_image', 'image'])->get();
        $video_attachments = [];

        for ($i = 0; $i < count($audios); $i++) {

            $video_path = 'app/videos/' . Str::uuid() . ".mp4";
            $ffmpegCommand = env('FFMPEG_BINARIES') . " -loop 1 -i " . storage_path($images[$i]->name) . " -i " . storage_path($audios[$i]->name) . " -c:v libx264 -tune stillimage -c:a aac -strict experimental -b:a 192k -pix_fmt yuv420p -shortest -fflags shortest -max_interleave_delta 100M " . storage_path($video_path);

            Log::info($ffmpegCommand);
            $process = Process::fromShellCommandline($ffmpegCommand);
            $process->setTimeout(36000);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $product->attachments()->create([
                'name' => $video_path,
                'type' => 'video'
            ]);

            $video_attachments[] = $video_path;
        }

        //merge videos
        $video_path = "app/videos/" . Str::uuid() . ".mp4";
        $ffmpegCommand = env('FFMPEG_BINARIES');

        foreach ($video_attachments as $video_attachment) {
            $ffmpegCommand .= " -i " . storage_path($video_attachment);
        }

        $ffmpegCommand .= " -filter_complex \"";
        for ($i = 0; $i < count($video_attachments); $i++) {
            $ffmpegCommand .= "[" . $i . ":v] [" . $i . ":a] ";
        }

        $ffmpegCommand .= "concat=n=" . count($video_attachments) . ":v=1:a=1 [v] [a]\" -map \"[v]\" -map \"[a]\" " . storage_path($video_path);

        Log::info($ffmpegCommand);
        $process = Process::fromShellCommandline($ffmpegCommand);
        $process->setTimeout(36000);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $product_video = $product->attachments()->create([
            'name' => $video_path,
            'type' => 'product_video'
        ]);

        return $product_video;
    }

    public static function mergeProductsVideo($productList, Keyword $keyword)
    {
        $input_counter = 0;
        $ffmpegCommand = env('FFMPEG_BINARIES');
        $ffmpegCommand .= " -i " . storage_path("app/template/intro.mp4");
        $input_counter++;
        //get intro title video
        $intro_title_video_path = $keyword->attachments()->where('type', 'intro_video')->first()->name;
        $ffmpegCommand .= " -i " . storage_path($intro_title_video_path);
        $input_counter++;

        for ($i = count($productList) - 1; $i >= 0; $i--) {
            $ffmpegCommand .= " -i " . storage_path("app/template/" . ($i + 1) . ".mp4");
            $input_counter++;
            $product_video = $productList[$i]->attachments()->where('type', 'product_video')->first();
            $ffmpegCommand .= " -i " . storage_path($product_video->name);
            $input_counter++;
        }
        $ffmpegCommand .= " -i " . storage_path("app/template/outro.mp4");
        $input_counter++;

        $video_path = "app/videos/" . Str::uuid() . ".mp4";
        $ffmpegCommand .= " -filter_complex \"concat=n=" . ($input_counter) . ":v=1:a=1\" -vsync 2 -y " . escapeshellarg(storage_path($video_path));

        Log::info($ffmpegCommand);
        $process = Process::fromShellCommandline($ffmpegCommand);
        $process->setTimeout(36000);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $keyword->attachments()->create([
            'name' => $video_path,
            'type' => 'output_video'
        ]);

    }

    public static function mergeBackGroundAudio(Keyword $keyword)
    {
        $background_audio_path = "app/template/audio.mp3";
        $video = $keyword->attachments()->where('type', 'output_video')->first();

        //generate folder if not exist
        $folder = self::generateOutputFolder($keyword);

        //uppercase first letter of each word
        $keyword_uppercase = ucwords($keyword->keyword);

        $video_path = "$folder/Top 5 Best " . $keyword_uppercase . ".mp4";

        $ffmpegCommand = env('FFMPEG_BINARIES') . " -i " . storage_path($video->name) . " -i " . storage_path($background_audio_path) . " -filter_complex \"[0:a]volume=1[a1];[1:a]volume=0.2[a2];[a1][a2]amix=inputs=2[a]\" -map 0:v -map \"[a]\"  -c:v copy -c:a aac -strict experimental -shortest " . escapeshellarg(storage_path($video_path));

        Log::info($ffmpegCommand);
        $process = Process::fromShellCommandline($ffmpegCommand);
        $process->setTimeout(36000);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $keyword->attachments()->create([
            'name' => $video_path,
            'type' => 'final_video'
        ]);
    }

    public static function generateThumbnail(Keyword $keyword)
    {
        $text = strtoupper("Top 5 Best \n" . wordwrap($keyword->keyword, 12, "\n", true));
        $bg_layer = Image::canvas(1000, 600, '#FFFFFF');
        $image = Image::make(storage_path('app/template/thumb-layer.png'));

        $primaryImage = Image::make($keyword->products()->first()->primary_image_url);

        $primaryImage->resize(350, 350, function ($constraint) {
            $constraint->aspectRatio();
        });

        $bg_layer->insert($primaryImage, 'center-right', 100, 0);

        $image->text($text, 100, 200, function ($font) use ($text) {
            $font->file(storage_path("app/fonts/Bangers-Regular.ttf"));
            $fontSize = (4000 / strlen($text));
            $font->size(min($fontSize, 80));
            $font->color('#FFFFFF');
        });

        $bg_layer->insert($image, 'center-left', 0, 0);


        $bg_layer->resize(1280, 720);

        //generate folder if not exist
        $folder = self::generateOutputFolder($keyword);


        //generate unique name for image
        $image_path = "$folder/thumbnail.jpg";

        $bg_layer->save(storage_path($image_path));

    }

    public static function generateOnlyDescriptionFile(GenerateVideo $commandHandler){
        //get keywords from text file or from command line option
        //each line one keyword
        $file_path = $commandHandler->option("file");
        //if file exist
        if (File::exists($file_path)) {
            $keywords = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            //trim all keywords
            $keywords = array_map('trim', $keywords);

            //remove "Best" "best" from beginning of keywords
            $keywords = array_map(function ($keyword) {
                return preg_replace('/^best\s/i', '', $keyword);
            }, $keywords);

        } else {
            throw new \Exception("File not found");
        }

        foreach ($keywords as $keywordText) {
            try {

                $commandHandler->comment("Generating description file for keyword: " . $keywordText);

                $commandHandler->comment("Deleting old temp files.");

                //delete all attached files
                $attachmentList = \App\Models\Attachment::query()->where("type", "!=", "final_video")->get();
                foreach ($attachmentList as $attachment) {
                    $realPath = storage_path($attachment->name);
                    if (File::exists($realPath)) {
                        File::delete($realPath);
                    }
                }

                $commandHandler->comment("Getting amazon products.");

                $data = VideoMakerController::getItems($keywordText);
                VideoMakerController::saveProducts($data['items'], $data['keyword'], 5);

                VideoMakerController::generateLinksFile($data['keyword']);

                $commandHandler->info("Description generated successfully.");

            } catch (\Exception $exception) {
                $commandHandler->error($exception->getMessage());
            }
        }
    }

    public function generateVideo(GenerateVideo $commandHandler)
    {
        //get keywords from text file or from command line option
        //each line one keyword
        $file_path = $commandHandler->option("file");
        //if file exist
        if (File::exists($file_path)) {
            $keywords = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            //trim all keywords
            $keywords = array_map('trim', $keywords);

            //remove "Best" "best" from beginning of keywords
            $keywords = array_map(function ($keyword) {
                return preg_replace('/^best\s/i', '', $keyword);
            }, $keywords);

        } else {
            throw new \Exception("File not found");
        }

        foreach ($keywords as $keywordText) {
            try {

                $commandHandler->comment("Generating video for keyword: " . $keywordText);

                $commandHandler->comment("Deleting old temp files.");

                //delete all attached files
                $attachmentList = \App\Models\Attachment::query()->where("type", "!=", "final_video")->get();
                foreach ($attachmentList as $attachment) {
                    $realPath = storage_path($attachment->name);
                    if (File::exists($realPath)) {
                        File::delete($realPath);
                    }
                }

                $commandHandler->comment("Getting amazon products.");

                $data = VideoMakerController::getItems($keywordText);
                $products = VideoMakerController::saveProducts($data['items'], $data['keyword'], 5);

                $commandHandler->comment("Generating product videos.");

                VideoMakerController::generateIntro($data['keyword']);

                foreach ($products as $product) {
                    VideoMakerController::generatePrimaryImage($product);
                    VideoMakerController::generateImagesFromProduct($product);
                    VideoMakerController::generateAudioScript($product);
                    VideoMakerController::generateVideoForProduct($product);
                }

                VideoMakerController::mergeProductsVideo($products, $data['keyword']);
                VideoMakerController::mergeBackGroundAudio($data['keyword']);

                VideoMakerController::generateLinksFile($data['keyword']);
                VideoMakerController::generateThumbnail($data['keyword']);

                $commandHandler->info("Video generated successfully.");
            } catch (\Exception $exception) {
                $commandHandler->error($exception->getMessage());
            }
        }
    }

    public static function generateTitle($keyword)
    {
        $image = Image::make(storage_path('app/template/title-template.jpg'));
        $title = "Top Five Best $keyword";
        $image->text(strtoupper($title), 500, 300, function ($font) use ($title) {
            $font->file(storage_path("app/fonts/title-font.ttf"));
            $font->size(1400 / strlen($title));
            $font->align('center');
            $font->valign('center');
            $font->color('#FFFFFF');
        });

        $image_path = storage_path('app/images/0_title_image.jpg');
        $image->resize(1280, 720);
        $image->save($image_path);


        $ffmpegCommand = env('FFMPEG_BINARIES') . " -y -framerate 1/5 -i $image_path -r 30 -c:v libx264 -pix_fmt yuv420p " . escapeshellarg(storage_path('app/videos/0_title_video.mp4'));

        $process = Process::fromShellCommandline($ffmpegCommand);
        $process->setTimeout(1000000);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();
    }

    public static function generateLinksFile(Keyword $keyword)
    {
        $links = [];
        foreach ($keyword->products as $product) {
            $links[] = self::generateAmazonLink($product);
        }

        $keywordHashTag = str_replace(" ", "_", $keyword->keyword);

        $text = <<<EOT
Links to the best $keyword->keyword 2023. We've researched the best $keyword->keyword 2023 on Amazon and make a top five list to save your time and money.

EOT;
        foreach ($links as $index => $link) {
            $text .= "\n" . ($index + 1) . " $link";
        }


        $text .= <<<EOT


Disclaimer: Portions of footage found in this video is not the original content produced by this channel owner .

Portions of stock footage of products were gathered from multiple sources including, manufacturers, fellow creators, and various other sources.

If something belongs to you, and you want it to be removed, please do not hesitate to contact us through channel email address


Amazon Affiliate Disclosure Notice:
It is important to also note that [Channel Name] is a participant in the Amazon Services LLC Associates Program,
an affiliate advertising program designed to provide a means for website owners to earn advertising fees by advertising and linking to amazon.com.


#Best_$keywordHashTag
#Top_5_Best_$keywordHashTag
EOT;

        //generate folder if not exist
        $folder = self::generateOutputFolder($keyword);

        $file = fopen(storage_path("$folder/$keyword->keyword.txt"), "w");
        fwrite($file, $text . "\n");
        fclose($file);
    }

    //generate amazon affiliate link from asin
    public static function generateAmazonLink(Product $product)
    {
        //short url using tinnyurl
        $url = self::generateAmazonURLWithAsin($product->asin, env('OUR_AMAZON_TRACKING_ID'));

        //short using our own domain
        $domain =env('SHORT_URL_DOMAIN');
        $shorten = Http::post("$domain/api/shorten", [
            "url" => $url,
            "product" => [
                "name" => $product->title,
                "price" => $product->price,
                //array to string features
                "description" => implode("\n", $product->features),
                "images" => $product->image_urls,
            ]
        ])->json(['shortened_url']);

        $url = "$domain/j/$shorten";

        return file_get_contents("https://tinyurl.com/api-create.php?url=" . $url);
    }

    public static function generateAmazonURLWithAsin($asin, $trackingId) {
        $baseURL = "https://www.amazon.com/dp/";
        $queryParameters = array(
            'tag' => $trackingId
        );
        $url = $baseURL . $asin . '?' . http_build_query($queryParameters);
        return $url;
    }

    public static function generateScript($productName, $productFeatures, $price)
    {
        $inputText = "Generate a product description script in exact five sentence using the below details.\n";
        $inputText .= "Product Name: $productName \n";
        $inputText .= "Product Price: $price \n";
        $inputText .= "Product Features: \n";
        foreach ($productFeatures as $index => $productFeature) {
            $inputText .= ($index + 1) . "$productFeature \n";
        }

        $apiKey = env('OPENAI_API_KEY');
        $url = "https://api.openai.com/v1/completions";
        $model = "text-davinci-003";
//        $model = "text-curie-001";

        $data = [
            "model" => $model,
            "prompt" => $inputText,
            "max_tokens" => 600,
            "temperature" => 0.9,
            "n" => 1,
            "stream" => false
        ];

        $response = Http::withoutVerifying()
            ->withHeaders([
                "Content-Type" => "application/json",
                "Authorization" => "Bearer $apiKey"
            ])
            ->post($url, $data)
            ->json();
        try {
            $text = $response["choices"][0]["text"];
            $text = str_replace("\n", "", $text);
            $array = preg_split('/[.!?]\s+/', $text);
            $array = array_map('trim', $array);
        } catch (\Exception $exception) {
            $array = $productFeatures;
        }
        return $array;
    }

    public static function generateAudio($text, $audioPath)
    {
        $keyFilePath = storage_path('app/credentials/private-key.json');

        $projectId = 'text-to-speach-372916';

        $credentials = new ServiceAccountCredentials("https://www.googleapis.com/auth/cloud-platform", $keyFilePath);

        $client = new TextToSpeechClient([
            'credentials' => $credentials,
            'projectId' => $projectId,
        ]);

        $synthesisInputText = (new SynthesisInput())
            ->setText($text);

        $voice = (new VoiceSelectionParams())
            ->setLanguageCode('en-US')
            ->setName('en-US-Studio-O');

        $audioConfig = (new AudioConfig())
            ->setSpeakingRate(1.04)
            ->setPitch(20.00)
            ->setEffectsProfileId(['handset-class-device'])
            ->setAudioEncoding(AudioEncoding::MP3);

        $response = $client->synthesizeSpeech($synthesisInputText, $voice, $audioConfig);
        $audioContent = $response->getAudioContent();

        $fileName = storage_path($audioPath);
        file_put_contents($fileName, $audioContent);
    }

    public static function generateOutputFolder(Keyword $keyword)
    {
        $folder = "app/output/Best " . $keyword->keyword;
        if (!file_exists(storage_path($folder))) {
            mkdir(storage_path($folder));
        }

        return $folder;
    }
}
