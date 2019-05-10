<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Auth;
use DB;

use Illuminate\Validation\Rule;

use App\Chatty\word_cloud;
use App\Chatty\word_cloud_work;
use App\Chatty\word_cloud_filter;
use App\Chatty\word_cloud_colorset;
use App\Chatty\word_cloud_color;
use App\Chatty\word_cloud_phrase;

use App\Chatty\app_setting;
use App\Chatty\dbAction;
use Illuminate\Http\Request;


use App\Chatty\Contracts\ChattyContract;
use App\Chatty\Contracts\SearchContract;

class WordCloudController extends Controller
{
    private $chatty;
    private $search;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(ChattyContract $chatty, SearchContract $search)
    {
        $this->chatty = $chatty;
        $this->search = $search;
        //$this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // If the user has generated any word clouds in the past, display them for review/deletion
        $wordClouds = word_cloud::where('created_by','ILIKE',Auth::user()->name)
            ->orderBy('created_at','desc')
            ->get();
        
        // Grab active word cloud filters for display in the drop-down
        $wordCloudFilters = null;
        if(word_cloud_filter::all()->count() > 0) {
            $wordCloudFilters = word_cloud_filter::getActive();
        } else {
            $wordCloudFilters[] = "Error: No Filters";
        }

        // Grab active word cloud colorsets for display in the drop-down
        $wordCloudColorsets = null;
        if(word_cloud_colorset::all()->count() > 0) {
            $wordCloudColorsets = word_cloud_colorset::getActive();
        } else {
            $wordCloudColorsets[] = "Error: No Colorsets";
        }

        // Javascript is an onChange event so grab the default descriptions for both filter & colorset for display
        // when the page initially loads
        $defaultFilterDescr = word_cloud_filter::where('is_default','=','true')->first()->descr;
        $defaultColorsetDescr = word_cloud_colorset::where('is_default','=','true')->first()->descr;
        $chattyDailyFilterDescr = word_cloud_filter::where('id','=',app_setting::dailyCloudFilter())->first()->descr;
        $chattyDailyColorsetDescr = word_cloud_colorset::where('id','=',app_setting::dailyCloudColorset())->first()->descr;
        $dailyCloudPerms = app_setting::dailyCloudPerms();
        $dailyTablePerms = app_setting::dailyCloudTablePerms();
        $dailyFilter = app_setting::dailyCloudFilter();
        $dailyColorset = app_setting::dailyCloudColorset();
        
        // Display a global counter that shows how many of the workers are currently processing jobs
        $cloudsInProgress = word_cloud::whereNotIn('status',['Success','Queued'])->count();
        $cloudsQueued = word_cloud::where('status','=','Queued')->count();

        // Build the index view and pass along any datasets it needs for rendering
        return \View::make('wordclouds.index')
            ->with('appsettings',app_setting::find(1))
            ->with('cloudsInProgress',$cloudsInProgress)
            ->with('cloudsQueued',$cloudsQueued)
            ->with('defaultDescr',$defaultFilterDescr)
            ->with('defaultColorsetDescr',$defaultColorsetDescr)
            ->with('chattyDailyFilterDescr',$chattyDailyFilterDescr)
            ->with('chattyDailyColorsetDescr',$chattyDailyColorsetDescr)
            ->with('wordCloudFilters',$wordCloudFilters)
            ->with('colorsets',$wordCloudColorsets)
            ->with('dailyCloudPerms',$dailyCloudPerms)
            ->with('dailyTablePerms',$dailyTablePerms)
            ->with('dailyFilter',$dailyFilter)
            ->with('dailyColorset',$dailyColorset)
            ->with('wordClouds',$wordClouds);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $messageArr = [];

        // CREATE cloud button has been clicked
        if($request->has('createCloud'))
        {
            // We may receive date info that has been entered free-form by the user, so
            // validate that it's a real date and in the proper format.
            $validator = \Validator::make($request->all(), [
                'author' => 'max:255',
                'from' => 'date_format:Y-m-d',
                'to' => 'date_format:Y-m-d|after_or_equal:from',
                //'wordColorset' => Rule::in(word_cloud_colorset::get('name')->toArray()),
            ]);

            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            // Confirm the user hasn't gone over their alloted number of clouds
            if((word_cloud::where('created_by','ILIKE',Auth::user()->name)->count() >= app_setting::wordCloudMaxPerUser()) &&
                Auth::user()->cannot('createForOthers', word_cloud::class))
            {
                $messageArr["warning"][] = 'You have reached the limit of '. app_setting::wordCloudMaxPerUser() .' word clouds per user. Please delete an older one and try again.';
                $request->session()->flash('warning', $messageArr["warning"]);
                return back()
                    ->withInput();
            }

            // Validate that user hasn't monkeyed with filters value
            if(!word_cloud_filter::where('name','=',$request->input('wordFilter'))->exists()) {
                $messageArr["error"][] = 'Word cloud filter value invalid. Please select from existing drop-down items.';
                $request->session()->flash('error', $messageArr["error"]);
                return back()
                    ->withInput();
            }

            // Validate that user hasn't monkeyed with colors value
            if(!word_cloud_colorset::where([['name','=',$request->input('wordColorset')],['active','=',true]])->exists()) {
                $messageArr["error"][] = 'Word cloud colorset value invalid. Please select from existing drop-down items.';
                $request->session()->flash('error', $messageArr["error"]);
                return back()
                    ->withInput();
            }

            // Does the user want to generate it synchronously? (browser waits for process to finish)
            $generateAsync = TRUE;
            if($request->has('wordCloudSync')) {
                $generateAsync = FALSE;
            }

            // Does the user want to try generating phrases?
            $generatePhrases = FALSE;
            if($request->has('wordCloudPhrases')) {
                $generatePhrases = TRUE;
            }

            // Now that the dates have been validated, do the math to figure out the range
            $from = date('1901-01-01');
            $to = date("Y-m-d", strtotime("+1 day"));

            switch($request->input('daterange')) {
                case 'lastmonth':
                    $from = date("Y-m-d", strtotime("-1 month"));
                    break;
                case 'prevmonth':
                    $from = date("Y-m-d", strtotime("-2 months"));
                    $to = date("Y-m-d", strtotime("-1 month"));
                    $to = date("Y-m-d", strtotime($to . " +1 day"));
                    break;
                case 'lastsixmonths':
                    $from = date("Y-m-d", strtotime("-6 months"));
                    break;
                case 'lastyear':
                    $from = date("Y-m-d", strtotime("-1 year"));
                    break;
                // All-time is handled above by setting date to 1901-01-01
                case 'custom':
                    // These dates have been confirmed valid by the Validator
                    $from = date("Y-m-d", strtotime($request->input('from')));
                    $to = date("Y-m-d", strtotime($request->input('to') . " +1 day"));
                    break;
            }

            // There is a field in app_settings to limit the max months that a user can create a cloud for, mainly
            // while I feel out the performance ramifications
            $diff = date_diff(date_create($from),date_create(date('Y-m-d',(strtotime ( '-1 day' , strtotime ($to) ) ))));
            $totalDiff = $diff->y * 12 + $diff->m + $diff->d/30 + $diff->h / 24;
            
            // Set word_cloud_month_limit to 0 to allow unlimited date range, otherwise it's restricted
            // to this many months
            if(app_setting::wordCloudMonthLimit() > 0) {
                if($totalDiff > app_setting::wordCloudMonthLimit()) {
                    $messageArr["warning"][] = 'Please limit word cloud date range to '. app_setting::wordCloudMonthLimit() .' month(s) or less at this time. Thank you!';
                    $request->session()->flash('warning', $messageArr["warning"]);
                    return back();
                }
            }

            // Set word_cloud_phrase_month_limit to 0 to allow unlimited date range, otherwise it's restricted
            // to this many months
            if($generatePhrases)
            {
                if(app_setting::wordCloudPhraseMonthLimit() > 0) {
                    if($totalDiff > app_setting::wordCloudPhraseMonthLimit()) {
                        $messageArr["warning"][] = 'When generating phrases, please limit word cloud date range to '. app_setting::wordCloudPhraseMonthLimit() .' month(s) or less at this time. Thank you!';
                        $request->session()->flash('warning', $messageArr["warning"]);
                        return back();
                    }
                }
            }

            // Security permissions
            $viewPermissions = $request->input('viewPermissions');
            $downloadPermissions = $request->input('downloadPermissions');

            // Word cloud filter has been validated as accurate so store it
            $wordCloudFilterId = word_cloud_filter::where('name','=',$request->input('wordFilter'))->first()->id;

            // Word cloud colorset has been validated so store it
            $wordCloudColorsetId = word_cloud_colorset::where('name','=',$request->input('wordColorset'))->first()->id;

            // If the user has rights to create on behalf of others, they may have passed an author value
            // through the form field. Otherwise they want to create one for themselves or they simply
            // cannot see the field.
            $author = Auth::user()->name;
            if(!empty($request->input('authorName'))) {
                $author = $request->input('authorName');
            }

            // Call the Chatty function to generate a word_cloud record and queue it for processing as a job
            $messageArr = $this->chatty->queueCreateWordCloudJob($author,$from,$to,$generateAsync,$viewPermissions,$downloadPermissions,$wordCloudFilterId,$wordCloudColorsetId,$generatePhrases);

            // TODO: clean this up with a trait so we don't repeat this code every time
            if(array_key_exists('error', $messageArr)) {
                if(count($messageArr["error"]) > 0) {
                    $request->session()->flash('error', $messageArr["error"]);
                }
            }
            if(array_key_exists('warning', $messageArr)) {
                if(count($messageArr["warning"]) > 0) {
                    $request->session()->flash('warning', $messageArr["warning"]);
                }
            }
            if(array_key_exists('success', $messageArr)) {
                if(count($messageArr["success"]) > 0) {
                    $request->session()->flash('success', $messageArr["success"]);
                }
            }

            // If the user has generated any word clouds in the past, display them for review/deletion
            $wordClouds = word_cloud::where('created_by',Auth::user()->name)->orderBy('created_at','desc')->get();

            return back()
                ->with('wordClouds',$wordClouds);
        }
        // SAVE SETTINGS button has been clicked
        else if($request->has('saveSettings'))
        {
            // Verify that the user has entered the setup data in a valid format
            $validator = \Validator::make($request->all(), [
                'wordCloudMonthLimit' => 'required|numeric|between:0,2147483647',
                'wordCloudPhraseMonthLimit' => 'required|numeric|between:0,2147483647',
                'wordCloudBatchSize' => 'required|numeric|between:0,1000',
                'wordCloudMaxPerUser' => 'required|numeric|between:0,1000',
                'wordCloudTermsPerCloud' => 'required|numeric|between:0,5000',
                'wordCloud2TermPhraseThreshold' => 'required|numeric|between:0,100',
                'wordCloud3TermPhraseThreshold' => 'required|numeric|between:0,100',
                'wordCloudPhraseDisplayThreshold' => 'required|numeric|between:0,1'
            ]);

            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            $settings = app_setting::find(1);

            // We're working with two separate models here (app_settings and word_cloud) so
            // instead of using controller authorization I'm using user authorization
            // depending on function
            if(Auth::user()->can('update',$settings))
            {
                $displaySentiment = FALSE;
                $generatePhrases = FALSE;
                if($request->has('displaySentiment')) {
                    $settings->word_cloud_display_sentiment = TRUE;
                    $displaySentiment = TRUE;
                } else {
                    $settings->wordcloud_phrases_default = FALSE;
                }
                if($request->has('wordCloudPhrasesDefault')) {
                    $settings->wordcloud_phrases_default = TRUE;
                    $generatePhrases = TRUE;
                } else {
                    $settings->wordcloud_phrases_default = FALSE;
                }
                $settings->word_cloud_month_limit = $request->input('wordCloudMonthLimit');
                $settings->word_cloud_phrase_month_limit = $request->input('wordCloudPhraseMonthLimit');
                $settings->word_cloud_elastic_terms_batch_size = $request->input('wordCloudBatchSize');
                $settings->word_cloud_max_per_user = $request->input('wordCloudMaxPerUser');
                $settings->word_cloud_terms_per_cloud = $request->input('wordCloudTermsPerCloud');
                $settings->wordcloud_phrases_2term_threshold = $request->input('wordCloud2TermPhraseThreshold');
                $settings->wordcloud_phrases_3term_threshold = $request->input('wordCloud3TermPhraseThreshold');
                $settings->wordcloud_phrase_display_threshold = $request->input('wordCloudPhraseDisplayThreshold');
                $settings->save();

                $messageArr[] = 'Display Word Cloud sentiment: ' . ($displaySentiment ? 'true' : 'false') . '. Max Range in Months: ' . 
                    $request->input('wordCloudMonthLimit') . '. Max Phrase Range in Months: ' . $request->input('wordCloudPhraseMonthLimit') .
                    '. Elastic Batch Size: ' . $request->input('wordCloudBatchSize') . 
                    '. Max Clouds per User: ' . $request->input('wordCloudMaxPerUser') . '. Generate phrases by default: ' . ($generatePhrases ? 'true' : 'false') . 
                    '. Terms per Cloud: ' . $request->input('wordCloudTermsPerCloud') . '. 2-Term Phrase Count Threshold: ' . 
                    $request->input('wordCloud2TermPhraseThreshold') . '. 3-Term Phrase Count Threshold: ' . 
                    $request->input('wordCloud3TermPhraseThreshold') . '. Phrase display threshold: ' . $request->input('wordCloudPhraseDisplayThreshold') .'.';

                $message = 'Successfully updated app_settings. Display Word Count Sentiment: ' . ($displaySentiment ? 'true' : 'false') . 
                    '. Max Range in Months: ' . $request->input('wordCloudMonthLimit') . '. Max Phrase Range in Months: ' . $request->input('wordCloudPhraseMonthLimit') . 
                    '. Elastic Batch Size: ' . $request->input('wordCloudBatchSize') . '. Max Clouds per User: ' . $request->input('wordCloudMaxPerUser') . 
                    '. Terms per Cloud: ' . $request->input('wordCloudTermsPerCloud') . '. 2-Term Phrase Count Threshold: ' .
                    $request->input('wordCloud2TermPhraseThreshold') . '. 3-Term Phrase Count Threshold: ' . 
                    $request->input('wordCloud3TermPhraseThreshold') . '. Generate phrases by default: ' . ($generatePhrases ? 'true' : 'false') . 
                    '. Phrase display threshold: ' . $request->input('wordCloudPhraseDisplayThreshold') .'.';

                $db_action = new dbAction();
                $db_action->username = Auth::user()->name;
                $db_action->message = $message;
                $db_action->save();

                return back()->with('messageIds',$messageArr);
            } else {
                // Return the user with an unauthorized error
                abort(403,'This action is unauthorized.');
            }
        }
        // SAVE DAILY SETTINGS button has been clicked
        else if($request->has('saveDailySettings'))
        {
            // Verify that the user has entered the setup data in a valid format
            $validator = \Validator::make($request->all(), [
                'dailyCloudHours' => 'required|numeric|between:0,1000',
                'dailyCloudUser' => 'required|between:0,255',
                'dailyCloudPerms' => ['required','string','max:255',Rule::in(['Self','Chatty','Anyone'])],
                'dailyTablePerms' => ['required','string','max:255',Rule::in(['Self','Chatty','Anyone'])],
            ]);

            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            // Validate that user hasn't monkeyed with filters value
            if(!word_cloud_filter::where('name','=',$request->input('dailyFilter'))->exists()) {
                $messageArr["error"][] = 'Word cloud filter value invalid. Please select from existing drop-down items.';
                $request->session()->flash('error', $messageArr["error"]);
                return back()
                    ->withInput();
            }

            // Validate that user hasn't monkeyed with colors value
            if(!word_cloud_colorset::where([['name','=',$request->input('dailyColorset')],['active','=',true]])->exists()) {
                $messageArr["error"][] = 'Word cloud colorset value invalid. Please select from existing drop-down items.';
                $request->session()->flash('error', $messageArr["error"]);
                return back()
                    ->withInput();
            }

            $settings = app_setting::find(1);

            // We're working with two separate models here (app_settings and word_cloud) so
            // instead of using controller authorization I'm using user authorization
            // depending on function
            if(Auth::user()->can('update',$settings))
            {
                $dailyCloudActive = FALSE;
                $dailyCloudPhrases = FALSE;
                if($request->has('dailyCloudActive')) {
                    $settings->chatty_daily_wordcloud_active = TRUE;
                    $dailyCloudActive = TRUE;
                } else {
                    $settings->chatty_daily_wordcloud_active = FALSE;
                }
                if($request->has('dailyCloudPhrases')) {
                    $settings->chatty_daily_wordcloud_phrases = TRUE;
                    $dailyCloudPhrases = TRUE;
                } else {
                    $settings->chatty_daily_wordcloud_phrases = FALSE;
                }
                // Filters checkboxes
                $dailyCloudFilters = [];
                if($request->has('ontopic')) {
                    $settings->chatty_daily_wordcloud_ontopic = TRUE;
                    $dailyCloudFilters['ontopic'] = TRUE;
                } else {
                    $dailyCloudFilters['ontopic'] = FALSE;
                    $settings->chatty_daily_wordcloud_ontopic = FALSE;
                }
                if($request->has('nws')) {
                    $settings->chatty_daily_wordcloud_nws = TRUE;
                    $dailyCloudFilters['nws'] = TRUE;
                } else {
                    $dailyCloudFilters['nws'] = FALSE;
                    $settings->chatty_daily_wordcloud_nws = FALSE;
                }
                if($request->has('stupid')) {
                    $settings->chatty_daily_wordcloud_stupid = TRUE;
                    $dailyCloudFilters['stupid'] = TRUE;
                } else {
                    $dailyCloudFilters['stupid'] = FALSE;
                    $settings->chatty_daily_wordcloud_stupid = FALSE;
                }
                if($request->has('political')) {
                    $settings->chatty_daily_wordcloud_political = TRUE;
                    $dailyCloudFilters['political'] = TRUE;
                } else {
                    $dailyCloudFilters['political'] = FALSE;
                    $settings->chatty_daily_wordcloud_political = FALSE;
                }
                if($request->has('tangent')) {
                    $settings->chatty_daily_wordcloud_tangent = TRUE;
                    $dailyCloudFilters['tangent'] = TRUE;
                } else {
                    $dailyCloudFilters['tangent'] = FALSE;
                    $settings->chatty_daily_wordcloud_tangent = FALSE;
                }
                if($request->has('informative')) {
                    $settings->chatty_daily_wordcloud_informative = TRUE;
                    $dailyCloudFilters['informative'] = TRUE;
                } else {
                    $dailyCloudFilters['informative'] = FALSE;
                    $settings->chatty_daily_wordcloud_informative = FALSE;
                }
                if($request->has('nuked')) {
                    $settings->chatty_daily_wordcloud_nuked = TRUE;
                    $dailyCloudFilters['nuked'] = TRUE;
                } else {
                    $dailyCloudFilters['nuked'] = FALSE;
                    $settings->chatty_daily_wordcloud_nuked = FALSE;
                }

                $settings->chatty_daily_wordcloud_hours = $request->input('dailyCloudHours');
                $settings->chatty_daily_wordcloud_user = $request->input('dailyCloudUser');
                $settings->chatty_daily_wordcloud_filter = word_cloud_filter::where('name','=',$request->input('dailyFilter'))->first()->id;
                $settings->chatty_daily_wordcloud_colorset = word_cloud_colorset::where('name','=',$request->input('dailyColorset'))->first()->id;
                $settings->chatty_daily_wordcloud_cloud_perms = $request->input('dailyCloudPerms');
                $settings->chatty_daily_wordcloud_table_perms = $request->input('dailyTablePerms');
                $settings->save();

                $message = 'Successfully updated app_settings. Daily wordcloud hours: ' . $settings->chatty_daily_wordcloud_hours .
                    '. Daily Wordcloud User: ' . $settings->chatty_daily_wordcloud_user . '. Daily Wordcloud Filter: ' .
                    $request->input('dailyFilter') . '. Daily Wordcloud Colorset: ' . $request->input('dailyColorset') .
                    '. Daily Wordcloud Permissions: ' . $request->input('dailyCloudPerms') . '. Daily Wordcloud Table Permissions: ' .
                    $request->input('dailyTablePerms') . '. Daily Cloud Active: ' . ($dailyCloudActive ? 'true' : 'false') .
                    '. Daily Cloud Phrases: ' . ($dailyCloudPhrases ? 'true' : 'false') .
                    '. Filters: ' . ($dailyCloudFilters['ontopic'] ? 'Ontopic ' : '') . ($dailyCloudFilters['nws'] ? 'NWS ' : '') .
                    ($dailyCloudFilters['stupid'] ? 'Stupid ' : '') . ($dailyCloudFilters['political'] ? 'Political ' : '') .
                    ($dailyCloudFilters['tangent'] ? 'Tangent ' : '') . ($dailyCloudFilters['informative'] ? 'Informative ' : '') .
                    ($dailyCloudFilters['nuked'] ? 'Nuked ' : '') . '.';
                $messageArr[] = $message;

                $db_action = new dbAction();
                $db_action->username = Auth::user()->name;
                $db_action->message = $message;
                $db_action->save();

                return back()->with('messageIds',$messageArr);
            } else {
                // Return the user with an unauthorized error
                abort(403,'This action is unauthorized.');
            }
        }
        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Chatty\word_cloud  $word_cloud
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,word_cloud $wordcloud)
    {
        $displayMsg = false;
        $displayText = null;

        // Bad things happen if we try to do all the math below before the cloud is done generating.
        // If it's queued/processing/errored return a message stating so and abend processing.
        if($wordcloud->status != 'Success') {
            $displayMsg = true;
            $displayText = 'Word cloud is currently '. $wordcloud->status . ' (' . $wordcloud->percent_complete . '%). Please check back in a bit!';
        }

        // If user is trying to view the table, look at permissions and determine whether/what to display
        if($wordcloud->share_cloud != 'Anyone') {
            if(!Auth::check()) {
                $displayMsg = true;
                $displayText = 'You must be logged in to view this Word Cloud. Click <a href="' . route('wordclouds.login', ['wordcloud' => $wordcloud->id]) .'">here</a> to login.';
            } else {
                if(Auth::user()->cannot('viewCloud',$wordcloud)) {
                    $displayMsg = true;
                    $displayText = 'You are not authorized to view this Word Cloud.';
                }
            }
        }

        // Don't do the rest of the processing if the user cannot see the results
        if($displayMsg) {
            return \View::make('wordclouds.table')
                            ->with('wordCloud',$wordcloud)
                            ->with('displayMsg',$displayMsg)
                            ->with('displayText',$displayText);
        }

        // Grab active word cloud filters for display in the drop-down
        $wordCloudFilters = null;
        if(word_cloud_filter::all()->count() > 0) {
            $wordCloudFilters = word_cloud_filter::getActive();
        } else {
            $wordCloudFilters[] = "Error: No Filters";
        }

        // Grab active word cloud colorsets for display in the drop-down
        $wordCloudColorsets = null;
        if(word_cloud_colorset::all()->count() > 0) {
            $wordCloudColorsets = word_cloud_colorset::getActive();
        } else {
            $wordCloudColorsets[] = "Error: No Colorsets";
        }

        // Term statistics
        $statsArr = [];
        $statsArr["term"]["mean"] = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->avg('score');
        $statsArr["term"]["median"] = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->get()->median('score');
        $statsArr["term"]["stdDev"] = $this->chatty->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordcloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['score']]; })->all());

        // Freq statistics
        $statsArr["freq"]["mean"] = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->avg('doc_freq');
        $statsArr["freq"]["median"] = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->get()->median('doc_freq');
        $statsArr["freq"]["stdDev"] = $this->chatty->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordcloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['doc_freq']]; })->all());

        // Date range statistics
        $from = Carbon::parse($wordcloud->from);
        $to = Carbon::parse($wordcloud->to);
        $diffNum = $to->diffInDays($from) - 1;
        $diffStr = $to->diffInDays($from) - 1 . ' days';
        if($wordcloud->created_by == app_setting::dailyCloudUser()) {
            $diffStr = $to->diffInHours($from) . ' hours';
        }
        
        // Grab the (filtered) dataset of word cloud terms
        $word_cloud_work = $this->chatty->getWordCloudWork($wordcloud->id);

        // Retrieve the flattened word and color strings to feed to VueWordCloud component
        $strings = $this->chatty->generateWordCloudTextAndColorStrings($wordcloud->id);
        $word_cloud_string = $strings["word_cloud_string"];
        $color_string = $strings["color_string"];

        // Calculate the pos/neu/neg counts for display so we're not doing too much math in our view
        $sentCounts["pos"] = $word_cloud_work->whereIn('sentiment','pos')->count();
        $sentCounts["neu"] = $word_cloud_work->whereIn('sentiment','neu')->count();
        $sentCounts["neg"] = $word_cloud_work->whereIn('sentiment','neg')->count();

        // Build the show view and pass along any datasets it needs for rendering
        return \View::make('wordclouds.show')
            ->with('wordCloud',$wordcloud)
            ->with('displayMsg',$displayMsg)
            ->with('displayText',$displayText)
            ->with('to',$to)
            ->with('from',$from)
            ->with('diffStr',$diffStr)
            ->with('sentCounts',$sentCounts)
            ->with('statsArr',$statsArr)
            ->with('sentEnabled',app_setting::displayWordCloudSentiment())
            ->with('dailyUser',app_setting::dailyCloudUser())
            ->with('wordCloudString',$word_cloud_string)
            ->with('wordCloudFilters',$wordCloudFilters)
            ->with('wordCloudColorsets',$wordCloudColorsets)
            ->with('colorString',$color_string)
            ->with('wordCloudWork',$word_cloud_work);
    }

    /**
     * If the user has accessed the {wordcloud}\table URL, intercept the request, add a session variable
     * and send them on their way to the Show view
     */
    public function table(Request $request, word_cloud $wordcloud)
    {
        $displayMsg = false;
        $displayText = null;

        // Bad things happen if we try to do all the math below before the cloud is done generating.
        // If it's queued/processing/errored return a message stating so and abend processing.
        if($wordcloud->status != 'Success') {
            $displayMsg = true;
            $displayText = 'Word cloud is currently '. $wordcloud->status . '(' . $wordcloud->percent_complete . '%). Please check back in a bit!';
        }

        // If user is trying to view the table, look at permissions and determine whether/what to display
        if($wordcloud->share_table_download != 'Anyone') {
            if(!Auth::check()) {
                $displayMsg = true;
                $displayText = 'You must be logged in to view the table for this Word Cloud. Click <a href="' . route('wordclouds.table.login', ['wordcloud' => $wordcloud->id]) .'">here</a> to login.';
            } else {
                if(Auth::user()->cannot('viewTable',$wordcloud)) {
                    $displayMsg = true;
                    $displayText = 'You are not authorized to view the table for this Word Cloud.';
                }
            }
        }

        // Don't do the rest of the processing if the user cannot see the results
        if($displayMsg) {
            return \View::make('wordclouds.table')
                            ->with('wordCloud',$wordcloud)
                            ->with('displayMsg',$displayMsg)
                            ->with('displayText',$displayText);
        }

        // If the user hit the /table/download URL let the above handle the validation then serve them the file
        if($request->session()->get('downloadCSV')) {
            $this->downloadTableAsCSV($wordcloud);
        }        

        // Grab active word cloud filters for display in the drop-down
        $wordCloudFilters = null;
        if(word_cloud_filter::all()->count() > 0) {
            $wordCloudFilters = word_cloud_filter::getActive();
        } else {
            $wordCloudFilters[] = "Error: No Filters";
        }

        // Grab active word cloud colorsets for display in the drop-down
        $wordCloudColorsets = null;
        if(word_cloud_colorset::all()->count() > 0) {
            $wordCloudColorsets = word_cloud_colorset::getActive();
        } else {
            $wordCloudColorsets[] = "Error: No Colorsets";
        }

        // Term statistics
        $statsArr = [];
        $statsArr["term"]["mean"] = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->avg('score');
        $statsArr["term"]["median"] = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->get()->median('score');
        $statsArr["term"]["stdDev"] = $this->chatty->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordcloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['score']]; })->all());

        // Freq statistics
        $statsArr["freq"]["mean"] = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->avg('doc_freq');
        $statsArr["freq"]["median"] = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->get()->median('doc_freq');
        $statsArr["freq"]["stdDev"] = $this->chatty->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordcloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['doc_freq']]; })->all());

        // Date range statistics
        $from = Carbon::parse($wordcloud->from);
        $to = Carbon::parse($wordcloud->to);
        $diffNum = $to->diffInDays($from) - 1;
        $diffStr = $to->diffInDays($from) - 1 . ' days';
        if($wordcloud->created_by == app_setting::dailyCloudUser()) {
            $diffStr = $to->diffInHours($from) . ' hours';
        }

        // Get the collection of (filtered) word cloud terms
        $word_cloud_work = $this->chatty->getWordCloudWork($wordcloud->id);

        // Calculate the pos/neu/neg counts for display so we're not doing too much math in our view
        $sentCounts["pos"] = $word_cloud_work->whereIn('sentiment','pos')->count();
        $sentCounts["neu"] = $word_cloud_work->whereIn('sentiment','neu')->count();
        $sentCounts["neg"] = $word_cloud_work->whereIn('sentiment','neg')->count();

        return \View::make('wordclouds.table')
            ->with('wordCloud',$wordcloud)
            ->with('to',$to)
            ->with('from',$from)
            ->with('diffStr',$diffStr)
            ->with('displayMsg',$displayMsg)
            ->with('displayText',$displayText)
            ->with('sentCounts',$sentCounts)
            ->with('sentEnabled',app_setting::displayWordCloudSentiment())
            ->with('dailyUser',app_setting::dailyCloudUser())
            ->with('wordCloudFilters',$wordCloudFilters)
            ->with('wordCloudColorsets',$wordCloudColorsets)
            ->with('statsArr',$statsArr)
            ->with('wordCloudWork', $word_cloud_work);

    }

    /**
     * Show the form for e diting the specified resource.
     *
     * @param  \App\Chatty\word_cloud  $word_cloud
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, word_cloud $wordcloud)
    {
        $displayMsg = false;
        $displayText = null;

        // Don't allow the user to edit the cloud until processing is done, for obvious reasons.
        if($wordcloud->status != 'Success') {
            $displayMsg = true;
            $displayText = 'Word cloud is currently '. $wordcloud->status . ' (' . $wordcloud->percent_complete . '%). Please check back in a bit!';
        }

        if(Auth::user()->cannot('update',$wordcloud)) {
            $displayMsg = true;
            $displayText = 'You are not authorized to edit this Word Cloud.';
        }

        // Don't do the rest of the processing if the user cannot see the results
        if($displayMsg) {
            return \View::make('wordclouds.edit')
                            ->with('cloud',$wordcloud)
                            ->with('displayMsg',$displayMsg)
                            ->with('displayText',$displayText);
        }

        $from = Carbon::parse($wordcloud->from);
        $to = Carbon::parse($wordcloud->to);
        $diffInDays = $to->diffInDays($from);

        // To display the correct value in the SELECT drop-downs we need to pull it now
        // and compare it at render time
        $view = $wordcloud->share_cloud;
        $download = $wordcloud->share_table_download;

        // Grab active word cloud filters for display in the drop-down
        $wordCloudFilters = null;
        if(word_cloud_filter::all()->count() > 0) {
            $wordCloudFilters = word_cloud_filter::getActive();
        } else {
            $wordCloudFilters[] = "Error: No Filters";
        }

        // Grab active word cloud colorsets for display in the drop-down
        $wordCloudColorsets = null;
        if(word_cloud_colorset::getActive()->count() > 0) {
            $wordCloudColorsets = word_cloud_colorset::getActive();
        } else {
            $wordCloudColorsets[] = "Error: No Colorsets";
        }

        // Grab the default descriptions for the filter and colorset as the page loads (Javascript is an onChange event)
        $defaultFilterDescr = word_cloud_filter::find($wordcloud->word_cloud_filter)->descr;
        $defaultColorsetDescr = word_cloud_colorset::find($wordcloud->word_cloud_colorset)->descr;

        // Build the edit view and pass along any datasets it needs for rendering
        return \View::make('wordclouds.edit')
            ->with('cloud',$wordcloud)
            ->with('displayMsg',$displayMsg)
            ->with('displayText',$displayText)
            ->with('from', $from->format('Y-m-d'))
            ->with('to', $to->format('Y-m-d'))
            ->with('view',$view)
            ->with('download',$download)
            ->with('defaultDescr',$defaultFilterDescr)
            ->with('defaultColorsetDescr',$defaultColorsetDescr)
            ->with('wordCloudFilters',$wordCloudFilters)
            ->with('wordCloudColorsets',$wordCloudColorsets)
            ->with('diffInDays',$diffInDays);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Chatty\word_cloud  $word_cloud
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, word_cloud $wordcloud)
    {
        // Update is currently called from the Edit form (lots of fields) and the Show form (just filter/color)
        // so certain parts of this routine only apply to different pages

        if(!$request->has('inlineWordFilter'))
        {
            $validator = \Validator::make($request->all(), [
                'viewPermissions' => ['required','string','max:255',Rule::in(['Self','Chatty','Anyone'])],
                'downloadPermissions' => ['required','string','max:255',Rule::in(['Self','Chatty','Anyone'])],
            ]);
    
            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            // Validate that user hasn't monkeyed with filters value
            if(!word_cloud_filter::where('name','=',$request->input('wordFilter'))->exists()) {
                $messageArr["error"][] = 'Word cloud filter value invalid. Please select from existing drop-down items.';
                $request->session()->flash('error', $messageArr["error"]);
                return back()
                    ->withInput();
            }

            // Validate that user hasn't monkeyed with colors value
            if(!word_cloud_colorset::where([['name','=',$request->input('wordColorset')],['active','=',true]])->exists()) {
                $messageArr["error"][] = 'Word cloud colorset value invalid. Please select from existing drop-down items.';
                $request->session()->flash('error', $messageArr["error"]);
                return back()
                    ->withInput();
            }
        }
        else
        {
            // Validate that user hasn't monkeyed with filters value
            if(!word_cloud_filter::where('name','=',$request->input('inlineWordFilter'))->exists()) {
                $messageArr["error"][] = 'Word cloud filter value invalid. Please select from existing drop-down items.';
                $request->session()->flash('error', $messageArr["error"]);
                return back()
                    ->withInput();
            }

            // Validate that user hasn't monkeyed with colors value
            if(!word_cloud_colorset::where([['name','=',$request->input('inlineWordColorset')],['active','=',true]])->exists()) {
                $messageArr["error"][] = 'Word cloud colorset value invalid. Please select from existing drop-down items.';
                $request->session()->flash('error', $messageArr["error"]);
                return back()
                    ->withInput();
            }
        }
        
        // User can only update these fields and they've been validated so just grab them
        // and save the values
        if(!$request->has('inlineWordFilter'))
        {
            $wordcloud->share_cloud = $request->input('viewPermissions');
            $wordcloud->share_table_download = $request->input('downloadPermissions');
            $wordcloud->word_cloud_filter = word_cloud_filter::where('name','=',$request->input('wordFilter'))->first()->id;
            $wordcloud->word_cloud_colorset = word_cloud_colorset::where('name','=',$request->input('wordColorset'))->first()->id;
        }
        else if($request->has('inlineWordFilter'))
        {
            $wordcloud->word_cloud_filter = word_cloud_filter::where('name','=',$request->input('inlineWordFilter'))->first()->id;
            $wordcloud->word_cloud_colorset = word_cloud_colorset::where('name','=',$request->input('inlineWordColorset'))->first()->id;
        }
        $wordcloud->save();

        // Summary message for logging and display
        $message = 'Successfully updated word cloud ' . $wordcloud->id . ' for user ' . $wordcloud->user . '. Cloud permissions updated to: ' . 
            $wordcloud->share_cloud . '. Table permissions updated to: ' . $wordcloud->share_table_download . '. Word Filter updated to: ' .
            word_cloud_filter::find($wordcloud->word_cloud_filter)->name . '. Colorset updated to: ' . 
            word_cloud_colorset::find($wordcloud->word_cloud_colorset)->name . '.';
        $messageArr["success"][] = $message;

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = $message;
            $db_action->save();
        }

        // Send the user back to main page once update has been saved
        if(!$request->has('inlineWordFilter')) {
            $request->session()->flash('success',$messageArr["success"]);
            return redirect()
                ->route('wordclouds');
        } else {
            return back();
        }
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Chatty\word_cloud  $word_cloud
     * @return \Illuminate\Http\Response
     */
    public function destroy(word_cloud $wordcloud)
    {
        //
        $this->authorize('delete',$wordcloud);

        // Store the user that the cloud was generated for so we can display it in the message
        // once the cloud is gone.
        $cloudUser = $wordcloud->user;

        $phraseCount = word_cloud_phrase::where('wordcloud_id',$wordcloud->id)->count();
        $phraseRecords = word_cloud_phrase::where('wordcloud_id',$wordcloud->id)->delete();
        $workCount = word_cloud_work::where('word_cloud_id',$wordcloud->id)->count();
        $wordId = $wordcloud->id;
        $workRecords = word_cloud_work::where('word_cloud_id',$wordcloud->id)->delete();
        $wordcloud->delete();

        $message = 'Successfully deleted word cloud ' . $wordId . ' for user ' . $cloudUser . '. Removed ' . $workCount . ' work records. Removed ' .
                    $phraseCount . ' phrase records.';

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = $message;
            $db_action->save();
        }

        return redirect()
                    ->route('wordclouds');

    }

    /**
     * Download the specified wordcloud source dataset as a .CSV file
     *
     * @param  \App\Chatty\word_cloud  $word_cloud
     * @return \Illuminate\Http\Response
     */
    public function downloadCSV(Request $request, word_cloud $wordcloud)
    {
        $request->session()->flash('downloadCSV',true);
        return redirect()->route('wordclouds.table',$wordcloud);
    }

    /**
     * 
     */
    private function downloadTableAsCSV(word_cloud $wordCloud)
    {
        // Retrieve the word_cloud_work object which contains a bazillion rows worth of terms/counts/etc
        $wordCloudWork = word_cloud_work::where('word_cloud_id',$wordCloud->id)->get();

        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $wordCloud->id . '.csv";');

        // Flush all the content that has been echoed so far (html, JS, CSS) otherwise the CSV file will
        // contain all the page content.
        ob_end_clean();

        $f = fopen('php://output','w');
        fputcsv($f,array("Word","Count","Score","Frequency","Computed Score","Sentiment"));
        foreach($wordCloudWork as $term) {
            fputcsv($f, array($term->term, $term->count, $term->score, $term->doc_freq, $term->computed_score, $term->sentiment), ',');
        }

        fclose($f);


        exit();
        
    }

}
