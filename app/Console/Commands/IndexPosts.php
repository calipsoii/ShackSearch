<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Chatty\Contracts\ChattyContract;

class IndexPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:crawl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit a batch of posts for indexing in Elastic.';

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
        // Inside the Chatty class there is a function that will handle selecting
        // the posts and submitting them for indexing in Elastic.
        $this->chatty->indexPosts();
    }
}
