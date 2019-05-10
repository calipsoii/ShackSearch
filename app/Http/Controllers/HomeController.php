<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Chatty\word_cloud;
use App\Chatty\app_setting;
use Carbon\Carbon;

use App\Chatty\Contracts\ChattyContract;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(ChattyContract $chatty)
    {
        //$this->middleware('auth');

        $this->chatty = $chatty;
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $wordcloud = word_cloud::find(app_setting::dailyCloudGUID());

        // Retrieve the flattened word and color strings to feed to VueWordCloud component
        $strings = $this->chatty->generateWordCloudTextAndColorStrings($wordcloud->id);
        $word_cloud_string = $strings["word_cloud_string"];
        $color_string = $strings["color_string"];
        $total_word_count = $strings["totalWordCount"];

        // Variables to be passed from PHP to JS when building dynamic click-to-search URLs
        $from = Carbon::parse($wordcloud->from);
        $to = Carbon::parse($wordcloud->to);

        return \View::make('home')
            ->with('wordCloud',$wordcloud)
            ->with('wordCloudString',$word_cloud_string)
            ->with('colorString',$color_string)
            ->with('totalWordCount',$total_word_count)
            ->with('from',$from)
            ->with('to',$to)
            ->with('dailyUser',app_setting::dailyCloudUser())
            ->with('dailyCloudHours',app_setting::dailyCloudHours());
    }
}
