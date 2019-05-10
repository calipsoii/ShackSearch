<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use Auth;
use Carbon\Carbon;

use App\Chatty\post;
use App\Chatty\thread;
use App\Chatty\dbAction;
use App\Chatty\app_setting;
use App\Chatty\mass_sync_result;
use App\Chatty\post_count;

use GuzzleHttp\Exception\GuzzleException;       // In case something goes wrong querying WinChatty
use GuzzleHttp\Client;                          // For connecting to WinChatty and pulling JSON data

use App\Chatty\Contracts\ChattyContract;

class PostController extends Controller
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
        // Build the counts for display in the DB Population grid
        /*
        $postCountBrackets = array();
        for($hunThou=0; $hunThou < 40000000; $hunThou+=100000 )
        {
            $bracketCount = DB::table('posts')->whereBetween('id',[$hunThou,$hunThou+100000])->count();
            $excludedCount = DB::table('mass_sync_results')->whereBetween('post_id',[$hunThou,$hunThou+99999])->count();
            $postCountBrackets[] = array("postsInBracket" => $bracketCount, "excludedInBracket" => $excludedCount);
        }
        */
        $postCounts = DB::table('post_counts')->orderBy('block_id','asc')->get();

        // The Blade template uses a few app_setting variables so pass it an instance to query with
        $appsettings = app_setting::find(1);

        // It's nice to see the date difference displayed in "last updated x seconds ago" format since the server
        // is UTC and I'm not.
        $lastMassSync = app_setting::lastMassSync();
        $lastPostCount = app_setting::postCountLastRun();

        return \View::make('posts.index')
            ->with('postCounts',$postCounts)
            ->with('lastMassSync',$lastMassSync)
            ->with('lastPostCount',$lastPostCount)
            ->with('appsettings',$appsettings);
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
        // 1 of 2 authorization gates
        $this->authorize('create',post::class);

        //
        $automaticSync = FALSE;
        $automaticBlock = FALSE;
        $automaticCounts = FALSE;
        $descend = FALSE;
        $messageArr = [];

        // SAVE SETTINGS button has been clicked
        if($request->has('postSyncSettings'))
        {
            // Some of the mass sync settings are simple enough to go in the app_settings table rather
            // than their own table.
            $settings = app_setting::find(1);

            // We're working with two separate models here (app_settings and posts) so
            // instead of using controller authorization I'm using user authorization
            // depending on function
            if(Auth::user()->can('update',$settings))
            {
                $validator = \Validator::make($request->all(), [
                    'workingBlock' => 'required|numeric|between:0,2147483647',
                    'stopPost' => 'required|numeric|between:0,2147483647',
                    'threadsToRetrieve' => 'required|numeric|between:0,5000',
                    'massSyncUsername' => 'required|string|min:1|max:255',
                ]);

                if($validator->fails()) {
                    return back()
                        ->withInput()
                        ->withErrors($validator);
                }

                $settings->mass_sync_working_block = $request->input('workingBlock');
                $settings->mass_sync_stop_post = $request->input('stopPost');
                $settings->mass_sync_threads_to_retrieve = $request->input('threadsToRetrieve');
                $settings->mass_sync_username = $request->input('massSyncUsername');
                if($request->has('massSyncEnabled')) {
                    $settings->mass_post_sync_enabled = TRUE;
                    $automaticSync = TRUE;
                } else {
                    $settings->mass_post_sync_enabled = FALSE;
                }
                if($request->has('MassSyncAutoBlock')) {
                    $settings->mass_post_sync_auto_block = TRUE;
                    $automaticBlock = TRUE;
                } else {
                    $settings->mass_post_sync_auto_block = FALSE;
                }
                if($request->has('massSyncAdvanceDesc')) {
                    $settings->mass_sync_advance_desc = TRUE;
                    $descend = TRUE;
                } else {
                    $settings->mass_sync_advance_desc = FALSE;
                }
                $settings->save();

                $messageArr[] = 'Working Block: ' . $request->input('workingBlock');
                $messageArr[] = 'Stop Post: ' . $request->input('stopPost');
                $messageArr[] = 'Threads to Retrieve: ' . $request->input('threadsToRetrieve');
                $messageArr[] = 'Mass Sync Username: ' . $request->input('massSyncUsername');
                $messageArr[] = 'Automatic Mass Post Sync: ' . ($automaticSync ? 'true' : 'false');
                $messageArr[] = 'Mass Post Sync Auto Block: ' . ($automaticBlock ? 'true' : 'false');
                $messageArr[] = 'Automatic Advance Descending: ' . ($descend ? 'true' : 'false');

                $message = 'Successfully updated app_settings. Working Block: ' . 
                    $request->input('workingBlock') . '. Stop Post: ' .
                    $request->input('stopPost') . '. Threads to Retrieve: ' . 
                    $request->input('threadsToRetrieve') . '. Mass Sync Username: ' . 
                    $request->input('massSyncUsername') . '. Automatic Mass Sync: ' .
                    ($automaticSync ? 'true' : 'false') . '. Mass Sync Automatic Block Advance: ' .
                    ($automaticBlock ? 'true' : 'false') . '. Auto Advance Descend: ' . 
                    ($descend ? 'true' : 'false') . '.';

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
        // POST COUNT SETTINGS have been updated
        if($request->has('postCountSettings'))
        {
            // Some of the mass sync settings are simple enough to go in the app_settings table rather
            // than their own table.
            $settings = app_setting::find(1);

            // We're working with two separate models here (app_settings and posts) so
            // instead of using controller authorization I'm using user authorization
            // depending on function
            if(Auth::user()->can('update',$settings))
            {
                $validator = \Validator::make($request->all(), [
                    'totalPostCount' => 'required|numeric|between:0,2147483647',
                    'postBlockSize' => 'required|numeric|between:0,2147483647',
                    'postCountUsername' => 'required|string|min:1|max:255',
                ]);

                if($validator->fails()) {
                    return back()
                        ->withInput()
                        ->withErrors($validator);
                }

                $settings->post_count_max = $request->input('totalPostCount');
                $settings->post_count_bracket_size = $request->input('postBlockSize');
                $settings->post_count_username = $request->input('postCountUsername');
                if($request->has('postCountEnabled')) {
                    $settings->post_count_enabled = TRUE;
                    $automaticCounts = TRUE;
                } else {
                    $settings->post_count_enabled = FALSE;
                }
                $settings->save();

                $messageArr[] = 'Total Post Count: ' . $request->input('totalPostCount');
                $messageArr[] = 'Post Block Size: ' . $request->input('postBlockSize');
                $messageArr[] = 'Post Count Username: ' . $request->input('postCountUsername');
                $messageArr[] = 'Automatic Post Counts: ' . ($automaticCounts ? 'true' : 'false');

                $message = 'Successfully updated app_settings. Total Post Count: ' . 
                    $request->input('totalPostCount') . '. Post Block Size: ' . 
                    $request->input('postBlockSize') . '. Post Count Username: ' . 
                    $request->input('postCountUsername') . '. Automatic Post Counts: ' .
                    ($automaticCounts ? 'true' : 'false') . '.';

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
        // MANUAL SYNC button has been clicked
        else if($request->has('manualSync'))
        {
            $messageArr = $this->chatty->downloadPostsFromWinchatty();

            return back()->with('messageIds',$messageArr);
        }
        // MISSING POSTS button has been clicked
        else if ($request->has('countMissing'))
        {
            $validator = \Validator::make($request->all(), [
                'missingPostsFrom' => 'required|numeric|between:0,2147483647',
                'missingPostsTo' => 'required|numeric|between:0,2147483647',
            ]);

            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            if($request->has('retrieveMissingPosts'))
            {
                $returnStringArr = $this->chatty->importMissingPostsInRange($request->input('missingPostsFrom'),$request->input('missingPostsTo'));
                return back()->with('messageIds',$returnStringArr);
            }
            else
            {
                $missingCount = $this->countMissingPostsInRange($request->input('missingPostsFrom'),$request->input('missingPostsTo'));
                return back()->with('missingCount',$missingCount);
            }
            
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Chatty\post  $post
     * @return \Illuminate\Http\Response
     */
    public function show(post $post)
    {
        if($post) {
            // If the user was sent here (by clicking on a Post or by manually entering the URL)
            // then send them to the thread view with the desired post highlighted.
            return redirect()->route('threads.show',['thread' => $post->thread_id, 'post' => $post->id]);
        } else {
            abort(404);
        }
        
        
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Chatty\post  $post
     * @return \Illuminate\Http\Response
     */
    public function edit(post $post)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Chatty\post  $post
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, post $post)
    {
        //
        $this->authorize('update',$post);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Chatty\post  $post
     * @return \Illuminate\Http\Response
     */
    public function destroy(post $post)
    {
        //
        $this->authorize('delete',$post);
    }

    /**
     *  Count up any missing posts within a range of Post IDs
     */
    public function countMissingPostsInRange($startingPostId, $endingPostId)
    {
        $postCount = DB::select('SELECT count(id) FROM posts_to_download 
                            WHERE id BETWEEN :startid AND :endid 
                            AND id NOT IN (SELECT post_id FROM mass_sync_results WHERE post_id BETWEEN :startid AND :endid) 
                            AND id NOT IN (SELECT id FROM posts WHERE id BETWEEN :startid AND :endid)',
                            ['startid' => $startingPostId, 'endid' => $endingPostId]);
        return $postCount[0]->count;
    }
}