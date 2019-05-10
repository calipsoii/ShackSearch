<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

use DB;
use Auth;

use App\Chatty\thread;
use App\Chatty\dbAction;
use App\Chatty\app_setting;

use App\Chatty\Contracts\ChattyContract;
use Carbon\Carbon;  // Extends PHP date functionality for doing date-math equations

class ChattyViewController extends Controller
{
    private $chatty;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(ChattyContract $chatty)
    {
        $this->chatty = $chatty;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        /*
        // Get all the threads
        //$threads = thread::with(['App\Chatty\post','App\Chatty\post_lol'])->orderBy('bump_date','desc')->get();
        $hoursToDisplayThread = Carbon::now()->subHours(app_setting::hoursToDisplay());
        //$hoursToDisplayThread = Carbon::now()->subHours(10);
        $threads = thread::with('posts.post_lols')->where('date','>=',$hoursToDisplayThread)->orderBy('bump_date','desc')->get();

        // load them into the view and pass it back
        return \View::make('chatty.index')->with('threads',$threads)->with('truncateLength',app_setting::subthreadTruncateLength());
        */
        $hoursToDisplayThread = Carbon::now()->subHours(app_setting::hoursToDisplay());
        $threads = thread::with('posts.post_lols')->where('date','>=',$hoursToDisplayThread)->orderBy('bump_date','desc')->get();

        // load them into the view and pass it back
        return \View::make('chatty.index')->with('threads',$threads);
    }

}
