<?php

namespace App\Mail;

use App\Chatty\monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MonitorEventPoll extends Mailable
{
    use Queueable, SerializesModels;

    public $monitor;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Monitor $monitor)
    {
        //
        $this->monitor = $monitor;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.monitors.eventpoll');
    }
}
