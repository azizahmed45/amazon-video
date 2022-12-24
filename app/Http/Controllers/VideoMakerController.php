<?php

namespace App\Http\Controllers;

use App\Console\Commands\GenerateVideo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Revolution\Amazon\ProductAdvertising\Facades\AmazonProduct;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class VideoMakerController extends Controller
{

    public static function getItems($keyword)
    {
        $response = AmazonProduct::search('All', $keyword, 1);
        $items = $response['SearchResult']['Items'];
        return $items;
    }

    public static function generateImagesFromItem($item)
    {

    }

    public function generateVideo(GenerateVideo $commandHandler)
    {
        $keyword = $commandHandler->argument("keyword");


        // get an array of all the files in the specified directory
        $files = File::glob(storage_path('app/videos/') . '*');

        foreach ($files as $file) {
            unlink($file);
        }

        $response = AmazonProduct::search('All', $keyword, 1);
        $items = $response['SearchResult']['Items'];

        $selectedItems = [];

        $commandHandler->info("Total Products Found: " . count($items));

        //generate title
        self::generateTitle($keyword);

        $productCount = 0;
        foreach ($items as $item_index => $item) {

            if ($productCount == 5) {
                break;
            }

            try {
                if (isset($item["Images"]["Variants"])) {

                    // get an array of all the files in the specified directory
                    $files = File::glob(storage_path('app/images/') . '*');

                    foreach ($files as $file) {
                        unlink($file);
                    }

                    $commandHandler->info("Image generate started for video:" . ($productCount + 1));

                    //add primary image
                    $template_path = "app/images/product_image_0.jpg";
                    $primaryTemplate = Image::make(storage_path('app/template/primary_image.jpg'));
                    $primaryImage = Image::make($item["Images"]["Primary"]['Large']['URL']);

                    $primaryImage->resize(null, 400, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $productTitle = $item["ItemInfo"]["Title"]["DisplayValue"];
                    $price = $item["Offers"]["Listings"][0]["Price"]["DisplayAmount"];

                    $primaryTemplate->insert($primaryImage, 'top-left', 150, 30);
                    $primaryTemplate->text(wordwrap($productTitle, 70), 100, 500, function ($font) {
                        $font->file(storage_path("app/fonts/ArchivoBlack-Regular.ttf"));
                        $font->size(20);
                        $font->color('#008037');
                    });
                    $primaryTemplate->text($price, 775, 355, function ($font) use ($price) {
                        $font->file(storage_path("app/fonts/ChangaOne-Regular.ttf"));
                        $font->size(220 / strlen($price));
                        $font->color('#00C2CB');
                    });

                    $primaryTemplate->resize(1280, 720);
                    $primaryTemplate->save(storage_path($template_path));

                    $images = $item["Images"]["Variants"];
                    foreach ($images as $image_index => $image) {

                        if ($image_index > 5) {
                            break;
                        }
                        $template_path = "app/images/product_image_" . ($image_index + 1) . ".jpg";

                        $template = Image::make(storage_path('app/template/product_image.jpg'));

                        $image = Image::make($image['Large']['URL']);
                        $image->resize(400, null, function ($constraint) {
                            $constraint->aspectRatio();
                        });

                        $template->insert($image, 'left', 50, 0);


                        $featureIndex = $image_index % count($item["ItemInfo"]["Features"]["DisplayValues"]);
                        $feature = $item["ItemInfo"]["Features"]["DisplayValues"][$featureIndex];
                        $template->text(wordwrap($feature, 35), 550, 120, function ($font) {
                            $font->file(storage_path("app/fonts/ABeeZee-Regular.ttf"));
                            $font->size(20);
                            $font->color('#008037');
                        });

                        $template->resize(1280, 720);
                        $template->save(storage_path($template_path));
                    }

                    $commandHandler->info("Image generate completed for video:" . ($productCount + 1));

                    $commandHandler->info("Video generate started for video:" . ($productCount + 1));
                    //generate_video
                    $outputFile = storage_path("app/videos/video_$item_index.mp4");
//                $ffmpegCommand = env('FFMPEG_BINARIES') . " -y -framerate 1/3 -i " . storage_path('app/images/product_image_%d.jpg') . " -r 25 -c:v libx264 -pix_fmt yuv420p " . escapeshellarg($outputFile);

                    $slideDuration = 7;

                    //get input images from a directory
                    $inputImages = glob(storage_path('app/images/') . '*');
                    $inputFilesCommand = "";
                    foreach ($inputImages as $inputImage) {
                        $inputFilesCommand .= " -loop 1 -t $slideDuration -i " . escapeshellarg($inputImage);
                    }

                    $ffmpegCommand = env('FFMPEG_BINARIES') . " $inputFilesCommand ";

                    //add complex filter for animation
                    $ffmpegCommand .= " -filter_complex \"";
                    foreach ($inputImages as $inputImageIndex => $inputImage) {
                        if ($inputImageIndex == 0) {
                            $ffmpegCommand .= "[$inputImageIndex:v]scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,setsar=1,fade=t=out:st=" . ($slideDuration - 1) . ":d=1[v$inputImageIndex];";
                        } else {
                            $ffmpegCommand .= "[$inputImageIndex:v]scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,setsar=1,fade=t=in:st=0:d=1,fade=t=out:st=" . ($slideDuration - 1) . ":d=1[v$inputImageIndex];";
                        }
                    }


                    foreach ($inputImages as $inputImageIndex => $inputImage) {
                        $ffmpegCommand .= "[v$inputImageIndex]";
                    }

                    $ffmpegCommand .= "concat=n=" . count($inputImages) . ":v=1:a=0,format=yuv420p[v]\" -map \"[v]\" " . escapeshellarg($outputFile);

                    $process = Process::fromShellCommandline($ffmpegCommand);
                    $process->setTimeout(1000000);
                    $process->run();

                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }

                    echo $process->getOutput();

                    $commandHandler->info("Video generate completed for video:" . ($productCount + 1));

                    $selectedItems[] = $item;
                    $productCount++;

                }
            } catch (Exception $exception) {
                \Illuminate\Support\Facades\Log::info($exception->getMessage());
                continue;
            }
        }


        //add all video
        // get an array of all the files in the specified directory
        $inputFiles = glob(storage_path('app/videos/video_') . '*');

        $commandHandler->info("Video merge started");

        // build the command string
        $command = env('FFMPEG_BINARIES');
        $command .= " -i " . escapeshellarg(storage_path('app/template/intro.mp4'));
        $command .= " -i " . escapeshellarg(storage_path('app/videos/0_title_video.mp4'));
        for ($i = count($inputFiles) - 1; $i >= 0; $i--) {
            $inputFile = $inputFiles[$i];

            $command .= " -i " . escapeshellarg(storage_path('app/template/' . ($i + 1) . '.mp4'));
            $command .= " -i " . escapeshellarg($inputFile);
        }
        $command .= " -i " . escapeshellarg(storage_path('app/template/outro.mp4'));
//    $command .= " -i " . escapeshellarg(storage_path('app/template/audio.mp3'));
        $outputFile = storage_path('app/output/video.mp4');
        $command .= ' -filter_complex "concat=n=' . (count($inputFiles) + 8) . ':v=1:a=0" -vsync 2 -y ' . escapeshellarg($outputFile);


//    return $command;
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(1000000);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }


        echo $process->getOutput();

        $commandHandler->info("Video merge completed");

        $commandHandler->info("Audio merge started");

        //add audio with video
        $command = env('FFMPEG_BINARIES');
        $command .= " -i " . escapeshellarg($outputFile);
        $command .= " -i " . escapeshellarg(storage_path('app/template/audio.mp3'));
        $outputFile = storage_path('app/output/video_with_audio.mp4');
        $command .= ' -c:v copy -c:a aac -strict experimental -map 0:v:0 -map 1:a:0 -shortest -y ' . escapeshellarg($outputFile);

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(1000000);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();

        //move output file
        $video_name = $keyword . "_video_" . time() . ".mp4";
        File::move(storage_path('app/output/video_with_audio.mp4'), base_path('AllVideos/' . $video_name . '.mp4'));

        $commandHandler->info("Audio merge completed");

        //generate links
        self::generateLinksFile($selectedItems, $keyword);

        $commandHandler->info("Video generate completed");
        $commandHandler->info("Video path: " . base_path('AllVideos/' . $video_name));

    }

    public static function generateTitle($keyword)
    {
        $image = Image::make(storage_path('app/template/title-template.jpg'));
        $title = "Top Five Best $keyword";
        $image->text(strtoupper($title), 500, 300, function ($font) use($title) {
            $font->file(storage_path("app/fonts/title-font.ttf"));
            $font->size(1400/strlen($title));
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

    public function generateLinksFile($selectedItems, $keyword)
    {
        $links = [];
        foreach ($selectedItems as $selectedItem) {
            $links[] = self::generateAmazonLink($selectedItem);
        }

        $keywordHashTag = str_replace(" ", "_", $keyword);

        $text = <<<EOT
Links to the best $keyword 2023. We've researched the best $keyword 2023 on Amazon and make a top five list to save your time and money.

EOT;
        foreach ($links as $index => $link) {
            $text .= "\n" . ($index + 1) . " $link";
        }


        $text .= <<<EOT


Disclaimer: Portions of footage found in this video is not the original content produced by this channel owner .

Portions of stock footage of products were gathered from multiple sources including, manufacturers, fellow creators, and various other sources.

If something belongs to you, and you want it to be removed, please do not hesitate to contact us through channel email address



#Best_$keywordHashTag
#Top_5_Best_$keywordHashTag
EOT;


        $file = fopen(base_path("AllVideos/$keyword.txt"), "w");
        fwrite($file, $text . "\n");
        fclose($file);
    }

    //generate amazon affiliate link from asin
    public static function generateAmazonLink($item)
    {
        //short url using tinnyurl
        $url = $item["DetailPageURL"];
        return file_get_contents("https://tinyurl.com/api-create.php?url=" . $url);
    }
}
