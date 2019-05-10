 <?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use Auth;
use Carbon\Carbon;

use App\Chatty\dbAction;
use App\Chatty\event;
use App\Chatty\postcategory;
use App\Chatty\app_setting;

use App\Chatty\Contracts\ChattyContract;

class EventController extends Controller
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
        //$this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // No need to view all events at once and paginating increases page load enormously
        $events = event::orderBy('event_id','desc')->paginate(app_setting::eventsPerPage());
        $lastPolled = date_create_from_format('Y-m-d H:i:s',app_setting::lastEventPoll());
        $dateDifference = date_diff($lastPolled,Carbon::now());

        // The Blade template uses a few app_setting variables so pass it an instance to query with
        $appsettings = app_setting::find(1);

        // load them into the view and pass it back
        return view('events.index', ['events' => $events])->with('lastEventPoll',$lastPolled->format('Y-m-d H:i:s'))->with('dateDifference',$dateDifference)->with('appsettings',$appsettings);
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
        //
        $this->authorize('create',event::class);

        $automaticPoll = FALSE;
        $activelyCreate = FALSE;

        // User clicked Import Events button at top of /events page
        if($request->has('importEvents'))
        {
            $validator = \Validator::make($request->all(), [
                'eventId' => 'required|numeric|between:0,2147483647',
            ]);
            
            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            // This call returns an array of messageId's which we'd ideally like to display to the user
            $messages = $this->chatty->importEventsFromWinchatty($request->input('eventId'));

            // Record the last poll date in the DB for display on the events page
            $appSetting = app_setting::getLatestAppSettings();
            $appSetting->last_event_poll = Carbon::now();
            $appSetting->save();
    
            return back()->with('messageIds',$messages);
        }
        // User wants to save the Event Polling settings
        else if($request->has('saveEventSettings'))
        {
            // Some of the event poll settings are simple enough to go in the app_settings table rather
            // than their own table.
            $settings = app_setting::find(1);

            $settings->event_poll_username = $request->input('eventPollUsername');
            if($request->has('eventPollEnabled')) {
                $settings->event_poll_enabled = TRUE;
                $automaticPoll = TRUE;
            } else {
                $settings->event_poll_enabled = FALSE;
            }
            if($request->has('activelyCreateflag')) {
                $settings->actively_create_threads_posts = TRUE;
                $activelyCreate = TRUE;
            } else {
                $settings->actively_create_threads_posts = FALSE;
            }
            $settings->save();

            $messageArr[] = 'Event Poll Username: ' . $request->input('eventPollUsername');
            $messageArr[] = 'Automatic Event Polling: ' . ($automaticPoll ? 'true' : 'false');
            $messageArr[] = 'Download Missing Threads: ' . ($activelyCreate ? 'true' : 'false');

            $message = 'Successfully updated app_settings. Event Poll Username: ' . 
                $request->input('eventPollUsername') . '. Automatic Event Polling: ' .
                ($automaticPoll ? 'true' : 'false') . '. Automatic Thread Downloading: ' . 
                ($activelyCreate ? 'true' : 'false') . '.';

            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = $message;
            $db_action->save();

            return back()->with('messageIds',$messageArr);
        }  
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Event $event)
    {
        //
        return view('events.show', ['event' => $event]);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * 
     *  Poll Winchatty v2 for any new events issued since the last time we polled. This function
     *  is intended to be called from a headless CRON job so it doesn't need a fancy view or anything
     *  since the output will just be going to CURL or whatever.
     */
    public function poll()
    {
        if(app_setting::eventPollEnabled())
        {
            //         
            if(Auth::attempt(['username' => app_setting::eventPollUsername(), 'password' => '<removed>']))
            {
                $startingEventId = app_setting::lastEventId();

                // Returns an array of strings with informational messages from the Event processing code
                $messages = $this->chatty->importEventsFromWinchatty($startingEventId);
                
                $endingEventId = app_setting::lastEventId();

                $appSetting = app_setting::getLatestAppSettings();
                $appSetting->last_event_poll = Carbon::now();
                $appSetting->save();

                return $endingEventId - $startingEventId;

                Auth::logout();
            }
            else
            {
                return 'failure';
            }
        }
    }

}
