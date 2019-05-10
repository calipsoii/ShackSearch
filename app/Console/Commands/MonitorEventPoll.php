<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Chatty\Contracts\ChattyContract;

class MonitorEventPoll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check that Event Poll is running properly.';

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
        //
        $this->chatty->monitorEventPolling('EventPoll');
    }
}
