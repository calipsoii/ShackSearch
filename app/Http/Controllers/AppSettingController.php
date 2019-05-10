<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use Auth;
use Carbon\Carbon;

use App\Chatty\Contracts\ChattyContract;
use App\Chatty\app_setting;
use App\Chatty\dbAction;

class AppSettingController extends Controller
{

    private $chatty;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(ChattyContract $chatty)
    {
        $this->middleware('auth');
        $this->chatty = $chatty;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get all the threads
        $appSettings = app_setting::orderBy('id','desc')->first();
        $logsToDelete = DB::table('db_actions')->where('created_at', '<', Carbon::now()->subDays($appSettings->logs_days_to_keep));
        $eventsToDelete = DB::table('events')->where('created_at', '<', Carbon::now()->subDays($appSettings->events_days_to_keep));

        // load them into the view and pass it back
        return \View::make('appsettings.index')
            ->with('appsettings',$appSettings)
            ->with('logsToDelete',$logsToDelete->count())
            ->with('eventsToDelete',$eventsToDelete->count());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $this->authorize('create',app_setting::class);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // This needs declared up here because the authorization method requires
        // an instance
        $settings = app_setting::find(1);

        $this->authorize('update',$settings);

        $messageArr = [];

        // User clicked Save Settings button on Application Settings panel
        if($request->has('applicationSettings'))
        {
            // For display purposes in the db_actions message
            $activelyCreate = FALSE;
            $allowWinchattyRegs = FALSE;

            $validator = \Validator::make($request->all(), [
                'subthreadTruncateLength' => 'required|numeric|between:0,1000',
                'subthreadsToDisplay' => 'required|numeric|between:0,1000',
                'hoursToDisplay' => 'required|numeric|between:0,5000',
                'eventsPerPage' => 'required|numeric|between:0,2147483647',
                'logsPerPage' => 'required|numeric|between:0,2147483647',
                'loggingLevel' => 'required|numeric|between:0,5',
                'monitorUser' => 'required|string|between:1,255',
                'proxyEmail' => 'required|string|between:1,255',
            ]);

            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }


            $settings->subthread_truncate_length = $request->input('subthreadTruncateLength');
            $settings->chatty_view_subthreads_to_display = $request->input('subthreadsToDisplay');
            $settings->chatty_view_hours_to_display_thread = $request->input('hoursToDisplay');
            $settings->events_to_display_per_page = $request->input('eventsPerPage');
            $settings->logs_to_display_per_page = $request->input('logsPerPage');
            $settings->logging_level = $request->input('loggingLevel');
            $settings->monitor_username = $request->input('monitorUser');
            if($request->has('allowWinchattyRegs')) {
                $settings->winchatty_registration_allowed = TRUE;
                $allowWinchattyRegs = TRUE;
            } else {
                $settings->winchatty_registration_allowed = FALSE;
            }
            $settings->proxy_email = $request->input('proxyEmail');
            // If user has entered something in the password field, save it
            $passwordMessage = 'Proxy Password Unchanged.';
            if(strlen($request->input('proxyPassword')) > 0)
            {
                $settings->proxy_password = encrypt($request->input('proxyPassword'));
                $passwordMessage = 'Proxy Password Encrypted and Updated.';
            }
            $settings->save();

            $messageArr[] = 'Subthread Truncate Length: ' . $request->input('subthreadTruncateLength');
            $messageArr[] = 'Hours to Display Thread: ' . $request->input('hoursToDisplay');
            $messageArr[] = 'Events per Page: ' . $request->input('eventsPerPage');
            $messageArr[] = 'Logs per Page: ' . $request->input('logsPerPage');
            $messageArr[] = 'Logging Level: ' . $request->input('loggingLevel');
            $messageArr[] = 'Monitor User: ' . $request->input('monitorUser');
            $messageArr[] = 'Allow Winchatty Registrations: ' . ($allowWinchattyRegs ? 'true' : 'false');
            $messageArr[] = 'Proxy Email Address: ' . $request->input('proxyEmail');
            $messageArr[] = $passwordMessage;            

            $message = 'Successfully updated app_settings. Subthread Truncate Length: ' . 
                $request->input('subthreadTruncateLength') . '. Subthreads to display: ' . $request->input('subthreadsToDisplay') . 
                '. Hours to Display Thread: ' . $request->input('hoursToDisplay') . '. Events per Page: ' . 
                $request->input('eventsPerPage') . '. Logs per Page: ' . $request->input('logsPerPage') . 
                '. Logging Level: ' . $request->input('loggingLevel') . '. Monitor Username: ' . $request->input('monitorUser') .
                '. Allow Winchatty Registrations: ' . ($allowWinchattyRegs ? 'true' : 'false') . 
                '. Proxy Email Address: ' . $request->input('proxyEmail') . '. ' . $passwordMessage;
         
            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = $message;
            $db_action->save();
        }
        // DATA ADMINISTRATION UPDATED
        else if($request->has('dataAdministrationSettings'))
        {
            $messages = $this->chatty->deleteAll($request->input('chkDeleteAllThreads'),
                                            $request->input('chkDeleteAllPosts'),
                                            $request->input('chkDeleteAllLOLs'),
                                            $request->input('chkDeleteAllEvents'),
                                            $request->input('chkDeleteAllLogs'));
            
            $messageArr = array_merge($messageArr,$messages);

            // Subroutine writes to db_action log (if appropriate) so don't worry about it here
        }
        // WINCHATTY CHECK GZIP & SSL
        else if($request->has('btnCheckGzipSSL'))
        {
            $messages = $this->chatty->confirmGzipSSLSetup();

            $messageArr = array_merge($messageArr,$messages);
        }
        // WINCHATTY LOGIN TEST
        else if($request->has('btnWinchattyLoginTest'))
        {
            $boolResult = $this->chatty->testWinchattyLogin($request->input('winchattyUsername'),$request->input('winchattyPassword'));

            if($boolResult) {
                $messageArr[] = 'Credentials verified!';
            } else {
                $messageArr[] = 'Credentials incorrect.';
            }
        }
        // CLEANUP SETTINGS
        else if($request->has('cleanupSettings'))
        {
            $validator = \Validator::make($request->all(), [
                'eventsDaysToKeep' => 'required|numeric|between:0,2147483647',
                'logsDaysToKeep' => 'required|numeric|between:0,2147483647',
            ]);

            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }
            
            $settings->events_days_to_keep = $request->input('eventsDaysToKeep');
            $settings->logs_days_to_keep = $request->input('logsDaysToKeep');
            $settings->save();

            $messageArr[] = 'Events Days to Keep: ' . $request->input('eventsDaysToKeep');
            $messageArr[] = 'Logs Days to Keep: ' . $request->input('logsDaysToKeep');

            $message = 'Successfully updated app_settings. Events Days to Keep: ' .
                $request->input('eventsDaysToKeep') . '. Logs Days to Keep: ' .
                $request->input('logsDaysToKeep') . '.';
            
            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = $message;
            $db_action->save();
        }

        /* Return the user to the Admin panel and display the message (if any). */
        return redirect('/appsettings')->with('messageIds',$messageArr);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Chatty\app_setting  $appsetting
     * @return \Illuminate\Http\Response
     */
    public function show(app_setting $appsetting)
    {
        //
        $this->authorize('view',$appsetting);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Chatty\app_setting  $appsetting
     * @return \Illuminate\Http\Response
     */
    public function edit(app_setting $appsetting)
    {
        //
        $this->authorize('update',$appsetting);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Chatty\app_setting  $appsetting
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, app_setting $appsetting)
    {
        //
        $this->authorize('update',$appsetting);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Chatty\app_setting  $appsetting
     * @return \Illuminate\Http\Response
     */
    public function destroy(app_setting $appsetting)
    {
        //
        $this->authorize('delete',$appsetting);
    }
}
