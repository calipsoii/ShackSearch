<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\User;
use App\Role;
use Carbon\Carbon;
use App\Chatty\app_setting;
use App\Chatty\dbAction;
use App\Chatty\monitor;
use Illuminate\Http\Request;

use App\Chatty\Contracts\ChattyContract;

class MonitorController extends Controller
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
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $monitors = monitor::orderBy('id')->get();

        return view('monitors.index', [
            'monitors' => $monitors
        ]);
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
        $this->authorize('create',monitor::class);

        //
        $validator = \Validator::make($request->all(), [
            'monitorName' => 'required|max:255',
            'monitorDescr' => 'required|max:255',
        ]);

        if($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator);
        }

        $monitor = new monitor;
        $monitor->name = $request->monitorName;
        $monitor->descr = $request->monitorDescr;
        $monitor->max_mins_since_task_last_exec = 100;
        $monitor->run_freq_mins = 60;
        $monitor->last_run_alert_state = false;
        $monitor->last_run_email_sent = false;
        $monitor->last_run = Carbon::now();
        $monitor->enabled = false;
        $monitor->save();

        // Summary message for logging and display
        $message = 'Successfully created Monitor ' . $monitor->id . '. Name: ' . 
            $monitor->name . '. Description: ' . $monitor->descr . '. Mins Since Last Exec: ' . $monitor->max_mins_since_task_last_exec .
            '. Run Frequency Mins: ' . $monitor->run_freq_mins . '. Last Run Alert State: ' . $monitor->last_run_alert_state .
            '. Last Run Email Sent: ' . $monitor->last_run_email_sent . '. Last Run: ' . $monitor->last_run . 
            '. Enabled: ' . $monitor->enabled . '.';
        $messageArr["success"][] = $message;

        $db_action = new dbAction();
        $db_action->username = Auth::user()->name;
        $db_action->message = $message;
        $db_action->save();

        $request->session()->flash('success',$messageArr["success"]);
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Chatty\monitor  $monitor
     * @return \Illuminate\Http\Response
     */
    public function show(monitor $monitor)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Chatty\monitor  $monitor
     * @return \Illuminate\Http\Response
     */
    public function edit(monitor $monitor)
    {
        return \View::make('monitors.edit')
            ->with('monitor',$monitor);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Chatty\monitor  $monitor
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, monitor $monitor)
    {
        // Ensure the user is actually authorized to call this function, in case they've edited the
        // HTML page to post this or something.
        $this->authorize('update',$monitor);

        $validator = \Validator::make($request->all(), [
            'name' => 'required|max:255',
            'descr' => 'required|max:255',
            'mins-before-alert' => 'required|numeric|between:1,10080',
            'run-freq' => 'required|numeric|between:1,10080'
        ]);

        if ($validator->fails()) {
            return redirect()->route('monitors.edit', $monitor->id)
                ->withErrors($validator)
                ->withInput();
        } else {

            $monitor->name = $request->input('name');
            $monitor->descr = $request->input('descr');
            $monitor->max_mins_since_task_last_exec = $request->input('mins-before-alert');
            $monitor->run_freq_mins = $request->input('run-freq');
            if($request->has('enabled')) {
                $monitor->enabled = true;
            } else {
                $monitor->enabled = false;
            }
            $monitor->save();

            // Summary message for logging and display
            $message = 'Successfully updated monitor ' . $monitor->name . '. Description: ' . $monitor->descr . 
            '. Alert After: ' . $monitor->max_mins_since_task_last_exec . ' mins. Monitor Run Frequency: ' . $monitor->run_freq_mins .
            ' mins. Enabled: ' . ($monitor->enabled ? 'true' : 'false') . '.';
            $messageArr["success"][] = $message;

            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = $message;
            $db_action->save();

            $request->session()->flash('success',$messageArr["success"]);
            return back();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Chatty\monitor  $monitor
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, monitor $monitor)
    {
        //
        $this->authorize('delete',$monitor);

        $monitorName = $monitor->name;

        $monitor->delete();

        // Summary message for logging and display
        $message = 'Successfully deleted Monitor: ' . $monitorName . '. Please immediately remove the monitor from the Kernel schedule!';
        $messageArr["success"][] = $message;

        $db_action = new dbAction();
        $db_action->username = Auth::user()->name;
        $db_action->message = $message;
        $db_action->save();

        // The user can only delete from the edit panel, so don't send them Back() there, redirect them to
        // monitor home.
        $request->session()->flash('success',$messageArr["success"]);
        return redirect()->route('monitors');
    }
}
