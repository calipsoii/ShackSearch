<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Chatty\post;
use App\Chatty\Contracts\ChattyContract;

class RebuildBodyC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:rebuildBodyC {numposts=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the posts..body_c column. Run SQL to set column to empty string first.';

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
        if(post::where('body_c','=','')->count() > 0)
        {
            // Start by displaying how many posts we'll be altering
            //$this->info("Rebuilding body_c for " . $this->argument('numposts') . " of a total " . post::where('body_c','=','')->count() . " posts.");

            // Grab the records we'll be altering
            $postsToRebuild = post::where('body_c','=','')->take($this->argument('numposts'))->get();

            // Then build a progress bar object so we can watch progress
            $bar = $this->output->createProgressBar(count($postsToRebuild));

            // Now iterate the posts, rebuilding body_c and updating the progress bar
            foreach($postsToRebuild as $post) {
                $post->body_c = htmlspecialchars_decode(strip_tags(str_replace('<br \/>',' <br \/>', str_replace('<br />',' <br />',$post->body))));
                $post->save();
                $bar->advance();
            }

            // We're done so finalize the bar
            $bar->finish();
        }
    }
}