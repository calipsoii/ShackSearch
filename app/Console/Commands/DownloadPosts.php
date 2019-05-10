<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Chatty\Contracts\ChattyContract;

class DownloadPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download posts from WinChatty.';

    // We need an instance of the Chatty provider
    protected $chatty;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ChattyContract $chatty)
    {
        parent::__construct();

        $this->chatty = $chatty;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Perform a batch sync, downloading 100k posts from WinChatty into local DB
        $this->chatty->downloadPostsFromWinchatty();
    }
}
