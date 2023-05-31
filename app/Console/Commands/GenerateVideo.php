<?php

namespace App\Console\Commands;

use App\Http\Controllers\VideoMakerController;
use Illuminate\Console\Command;

class GenerateVideo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:video {--F|file=} {--only-description}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate video from images and audio';

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
    public function handle(VideoMakerController $videoMakerController)
    {
        if($this->option('only-description')){
            VideoMakerController::generateOnlyDescriptionFile($this);
        } else {
            $videoMakerController->generateVideo($this);
        }
        return 0;
    }
}
