<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Chatty\post;
use App\Chatty\Contracts\ChattyContract;

class EmptyBodyC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:emptyBodyC {numposts=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set a small batch of body_c values to empty string.';

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
        // Grab the records we'll be altering
        $postsToRebuild = post::where('body_c','<>','')->take($this->argument('numposts'))->get();

        // Then build a progress bar object so we can watch progress
        $bar = $this->output->createProgressBar(count($postsToRebuild));

        // Now iterate the posts, rebuilding body_c and updating the progress bar
        foreach($postsToRebuild as $post) {
            $post->body_c = '';
            $post->save();
            $bar->advance();
        }

        // We're done so finalize the bar
        $bar->finish();
    }
}
