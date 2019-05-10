<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Chatty\Contracts\ChattyContract;

class CountPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update post counts in post_counts table.';

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
        // Inside the Chatty class there is a function that will handle populating
        // post_counts table for us. 
        $this->chatty->countPosts();

    }
}
