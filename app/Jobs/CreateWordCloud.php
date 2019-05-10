<?php

namespace App\Jobs;

use App\Chatty\Contracts\ChattyContract;
use App\Chatty\word_cloud;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateWordCloud implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $wordcloud;
    private $chatty;
    public $tries = 3;              // Number of times to retry a failed job
    public $timeout = 510;          // Number of seconds the job can run before timing out


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(word_cloud $wordcloud)
    {
        //
        $this->wordcloud = $wordcloud;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ChattyContract $chattycontract)
    {
        $this->chatty = $chattycontract;
        //
        $this->chatty->generateWordCloudForAuthor( $this->wordcloud->id);
    }
}
