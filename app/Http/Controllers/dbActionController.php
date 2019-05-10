<?php

namespace App\Http\Controllers;

use DB;
use Carbon\Carbon;

use Illuminate\Http\Request;
use App\Chatty\dbAction;
use App\Chatty\app_setting;

class dbActionController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        //$this->chatty = $chatty;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // get all DB actions
        //$dbActions = dbAction::orderBy('id','desc')->get();
        $dbActions = dbAction::orderBy('id','desc')->paginate(app_setting::logsPerPage());        

        // load them into the view and pass it back
        //return \View::make('dbActions.index')->with('dbActions',$dbActions);
        return view('dbActions.index', ['dbActions' => $dbActions]);
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
        $this->authorize('create',dbAction::class);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Chatty\dbAction  $dbAction
     * @return \Illuminate\Http\Response
     */
    public function show($dbAction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Chatty\dbAction  $dbAction
     * @return \Illuminate\Http\Response
     */
    public function edit($dbAction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Chatty\dbAction  $dbAction
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $dbAction)
    {
        //
        $this->authorize('update',$dbAction);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Chatty\dbAction  $dbAction
     * @return \Illuminate\Http\Response
     */
    public function destroy($dbAction)
    {
        //
        $this->authorize('delete',$dbAction);
    }
}
