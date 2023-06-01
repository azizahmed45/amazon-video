<?php

namespace App\Console\Commands;

use Google_Client;
use Google_Http_MediaFileUpload;
use Google_Service_YouTube;
use Google_Service_YouTube_ThumbnailDetails;
use Google_Service_YouTube_Video;
use Google_Service_YouTube_VideoSnippet;
use Google_Service_YouTube_VideoStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class UploadVideo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:video';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $this->upload_video(
                storage_path('app/template/2.mp4'),
                storage_path('app/template/primary_image.jpg'),
                "Video for my channel",
                "Test video description",
                ["abc", "video", "youtube", "api"],
                22,
                Carbon::now()->addMinutes(10)
            );
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error($e);
        }
        return 0;
    }

    public function upload_video($video_path, $thumbnail_path, string $title, string $description, array $tags, int $category_id, Carbon $schedule_time)
    {
        // Connect to the YouTube API with Oauth client
        $client = new Google_Client();
        $client->setClientId(env('YOUTUBE_CLIENT_ID'));
        $client->setClientSecret(env('YOUTUBE_CLIENT_SECRET'));
        $client->setScopes(Google_Service_YouTube::YOUTUBE_UPLOAD);
        $client->setAccessType('offline');
        $client->setRedirectUri('http://localhost/oauth2callback.php');

        //create url for verification code
        $auth_url = $client->createAuthUrl();
        // Request authorization from the user.
        $this->info("Please visit:\n\n$auth_url\n\n");

        //open browser for verification code
        exec('start chrome "'.$auth_url.'"');

        //ask code from redirect url
        $code = urldecode($this->ask('Enter verification code: '));

        // Exchange authorization code for an access token.`
        $access_token_object = $client->fetchAccessTokenWithAuthCode($code);

        //get access token from json object
        $access_token = $access_token_object['access_token'];

        $client->setAccessToken($access_token);

        $client->authorize();


        $youtube = new Google_Service_YouTube($client);

        // Create a snipet with title, description, tags and category id
        $snippet = new Google_Service_YouTube_VideoSnippet();
        $snippet->setTitle($title);
        $snippet->setDescription($description);
        $snippet->setTags($tags);
        $snippet->setCategoryId($category_id);
//        $snippet->setChannelId('UC3R651WN3TE59VXUrIaUxRQ');

        // Set the thumbnail
//        $snippet->setThumbnails(new Google_Service_YouTube_ThumbnailDetails($thumbnail_path));

        // Create a video status with privacy status. Options are "public", "private" and "unlisted".
        $status = new Google_Service_YouTube_VideoStatus();
        $status->setPrivacyStatus('private');
        //set schedule time
//        $status->setPublishAt($schedule_time);

        // Create a YouTube video with snippet and status
        $video = new Google_Service_YouTube_Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        $youtube->videos->insert('snippet,status',
            $video,
            array(
                'data' => file_get_contents($video_path),
                'mimeType' => 'application/octet-stream',
                'uploadType' => 'multipart'
            ));

        //set custom thumbnail
        $video_id = $video->id;
        $this->info("Video id: $video_id");
        $this->info("Setting custom thumbnail...");
        $youtube->thumbnails->set($video_id, [
            'mediaUpload' => [
                'maxSize' => 1024 * 1024 * 5,
                'mimeType' => 'image/jpeg'
            ],
            'mediaBody' => file_get_contents($thumbnail_path)
        ]);

        //upload video
        // Upload the video
        $chunkSizeBytes = 1 * 1024 * 1024; // 1MB chunk size (adjust as needed)
        $media = new Google_Http_MediaFileUpload(
            $client,
            $youtube->videos->insert('snippet,status', $video, ['uploadType' => 'resumable']),
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($video_path));

//        $status = false;
//        $handle = fopen($video_path, 'rb');
//        while (!$status && !feof($handle)) {
//            $chunk = fread($handle, $chunkSizeBytes);
//            $status = $media->nextChunk($chunk);
//            $progressPercent = round($media->getProgress() * 100);
//
//            // Update and display the progress indicator
//            $progressBarWidth = 50; // Adjust the width of the progress bar
//            $filledLength = round($progressPercent / (100 / $progressBarWidth));
//            $bar = str_repeat('=', $filledLength) . str_repeat(' ', $progressBarWidth - $filledLength);
//            $this->info("Upload progress: [$bar] $progressPercent% \r");
//
//            // Flush the output buffer to immediately display the progress
//            flush();
//        }
//        fclose($handle);
//
//        // Save the video resource or perform additional actions
//        $uploadedVideoId = $status['id'];
//        $this->info("Video uploaded successfully! Video ID: $uploadedVideoId");

    }


}
