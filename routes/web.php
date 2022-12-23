<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Revolution\Amazon\ProductAdvertising\Facades\AmazonProduct;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

Route::get('/', function (Request $request) {

    // get an array of all the files in the specified directory
    $files = File::glob(storage_path('app/videos/') . '*');

    foreach ($files as $file) {
        unlink($file);
    }

    $response = AmazonProduct::search('All', $request->query("keyword"), 1);
    $items = $response['SearchResult']['Items'];

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

                $primaryImage->resize(1280, 720);
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

                //generate_video
                $outputFile = storage_path("app/videos/video_$item_index.mp4");
//                $ffmpegCommand = env('FFMPEG_BINARIES') . " -y -framerate 1/3 -i " . storage_path('app/images/product_image_%d.jpg') . " -r 25 -c:v libx264 -pix_fmt yuv420p " . escapeshellarg($outputFile);

                $slideDuration = 10;

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

                $productCount++;

            }
        } catch (Exception $exception) {
            \Illuminate\Support\Facades\Log::info($exception->getMessage());
            continue;
        }
    }


    //add all video
    // get an array of all the files in the specified directory
    $inputFiles = glob(storage_path('app/videos/') . '*');

    // build the command string
    $command = env('FFMPEG_BINARIES');
    $command .= " -i " . escapeshellarg(storage_path('app/template/intro.mp4'));
    for ($i = count($inputFiles) -1; $i >= 0; $i--) {
        $inputFile = $inputFiles[$i];

        $command .= " -i " . escapeshellarg(storage_path('app/template/' . ($i + 1) . '.mp4'));
        $command .= " -i " . escapeshellarg($inputFile);
    }
    $command .= " -i " . escapeshellarg(storage_path('app/template/outro.mp4'));
//    $command .= " -i " . escapeshellarg(storage_path('app/template/audio.mp3'));
    $outputFile = storage_path('app/output/video.mp4');
    $command .= ' -filter_complex "concat=n=' . (count($inputFiles) + 7) . ':v=1:a=0" -vsync 2 -y ' . escapeshellarg($outputFile);


//    return $command;
    $process = Process::fromShellCommandline($command);
    $process->setTimeout(1000000);
    $process->run();

    if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
    }

    echo $process->getOutput();

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

});


Route::get("test/{asin}", [\App\Http\Controllers\VideoMakerController::class, "generateAffiliateLink"]);

Route::get("test1", function () {
    $outputFile = storage_path('app/output/video.mp4');

    //add audio with video
    $command = env('FFMPEG_BINARIES');
    $command .= " -i " . escapeshellarg($outputFile);
    $command .= " -i " . escapeshellarg(storage_path('app/template/audio.mp3'));
    $outputFile = storage_path('app/output/video_with_audio.mp4');
    $command .= ' -c:v copy -c:a aac -strict experimental -shortest ' . escapeshellarg($outputFile);

    $process = Process::fromShellCommandline($command);
    $process->setTimeout(1000000);
    $process->run();

    if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
    }
});
