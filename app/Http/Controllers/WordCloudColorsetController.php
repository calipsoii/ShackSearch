<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\Chatty\dbAction;
use App\Chatty\word_cloud;
use App\Chatty\word_cloud_color;
use App\Chatty\word_cloud_colorset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WordCloudColorsetController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
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
        $colorsets = word_cloud_colorset::orderBy('id')->get();

        return view('colorsets.index', [
            'colorsets' => $colorsets
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
        $this->authorize('create',word_cloud_colorset::class);

        //
        $validator = \Validator::make($request->all(), [
            'colorsetName' => 'required|max:255',
            'colorsetDescr' => 'required|max:255',
        ]);

        if($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator);
        }

        $colorset = new word_cloud_colorset;
        $colorset->name = $request->colorsetName;
        $colorset->descr = $request->colorsetDescr;
        if($request->has('defaultColorset')) {
            if(word_cloud_colorset::where('is_default','=',true)->exists()) {
                $currentDefault = word_cloud_colorset::where('is_default','=',true)->first();
                $currentDefault->is_default = false;
                $currentDefault->save();
            }
            $colorset->is_default = true;
        } else {
            $colorset->is_default = false;
        }
        if($request->has('active')) {
            $colorset->active = true;
        } else {
            $colorset->active = false;
        }
        $colorset->is_default = false;
        $colorset->active = 
        $colorset->save();

        // Summary message for logging and display
        $message = 'Successfully created Word Cloud Colorset ' . $colorset->id . '. Name: ' . 
            $colorset->name . '. Description: ' . $colorset->descr . '. Is Default: ' . $colorset->is_default .
            '. Active: ' . $colorset->active . '.';
        $messageArr["success"][] = $message;

        $db_action = new dbAction();
        $db_action->username = Auth::user()->name;
        $db_action->message = $message;
        $db_action->save();

        $request->session()->flash('success',$messageArr["success"]);
        return back();

    }

    public function storeColor(Request $request, word_cloud_colorset $colorset)
    {
        $this->authorize('create',word_cloud_colorset::class);

        //
        $validator = \Validator::make($request->all(), [
            'colorSeqNum' => 'required|numeric|between:0,9',
            'colorName' => 'required|max:255',
        ]);

        if($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator);
        }

        // Make sure the submitted sequence number doesn't already exist
        if(word_cloud_color::where([['colorset_id','=',$colorset->id],
                                    ['sequence_num','=',$request->colorSeqNum]])->exists()) {
            $messageArr["error"][] = 'Sequence number ' . $request->colorSeqNum . ' already exists for this colorset.';
            $request->session()->flash('error',$messageArr["error"]);
            return back();
        }

        $color = new word_cloud_color();
        $color->colorset_id = $colorset->id;
        $color->sequence_num = $request->colorSeqNum;
        $color->color = $request->colorName;
        $color->save();

        // Summary message for logging and display
        $message = 'Successfully created Word Cloud Color ' . $color->id . ' for colorset ' . $colorset->name . '. Color: ' . $color->color .
                   '. Sequence: ' . $color->sequence_num . '.';
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
     * @param  \App\Chatty\word_cloud_colorset  $word_cloud_colorset
     * @return \Illuminate\Http\Response
     */
    public function show(word_cloud_colorset $word_cloud_colorset)
    {
        //
        //dd($word_cloud_colorset);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Chatty\word_cloud_colorset  $word_cloud_colorset
     * @return \Illuminate\Http\Response
     */
    public function edit(word_cloud_colorset $colorset)
    {
        $colors = word_cloud_color::where('colorset_id','=',$colorset->id)->orderBy('sequence_num','asc')->get();
        //
        return \View::make('colorsets.edit')
            ->with('colorset',$colorset)
            ->with('colors',$colors);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Chatty\word_cloud_colorset  $word_cloud_colorset
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, word_cloud_colorset $colorset)
    {
        //
        $this->authorize('update',$colorset);
        //
        $validator = \Validator::make($request->all(), [
            'colorsetName' => 'required|max:255',
            'colorsetDescr' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('colorsets.edit', $colorset->id)
                ->withErrors($validator)
                ->withInput();
        } else {

            $defaultColorset = false;
            if($request->has('defaultColorset')) {
                $defaultColorset = true;
                if(word_cloud_colorset::where('is_default','=',true)->exists()) {
                    $currentDefault = word_cloud_colorset::where('is_default','=',true)->first();
                    if($currentDefault->id != $colorset->id) {
                        $currentDefault->is_default = false;
                        $currentDefault->save();
                    }
                }
            }
            if($request->has('active')) {
                $colorset->active = true;
            } else {
                $colorset->active = false;
            }
            $colorset->name = $request->input('colorsetName');
            $colorset->descr = $request->input('colorsetDescr');
            $colorset->is_default = $defaultColorset;
            $colorset->save();

            // Summary message for logging and display
            $message = 'Successfully updated Word Cloud Colorset: ' . $colorset->name . ' with Description: ' . $colorset->descr . 
            '. Is Default: ' . ($colorset->is_default ? 'true' : 'false') . '. Active: ' . ($colorset->active ? 'true' : 'false') . '.';
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
     * @param  \App\Chatty\word_cloud_colorset  $word_cloud_colorset
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, word_cloud_colorset $colorset)
    {
        $this->authorize('delete',$colorset);

        // First make sure the colorset isn't in use somewhere
        $count = word_cloud::where('word_cloud_colorset','=',$colorset->id)->count();
        if($count > 0) {
            $messageArr["error"][] = 'Colorset ' . $colorset->name . ' is in use by ' . $count . ' word clouds. If you intended to delete this colorset, contact the site admin to have clouds updated.';
            $request->session()->flash('error',$messageArr["error"]);
            return back();
        }

        // Make sure the user isn't trying to delete a default colorset, and if they are, ask that
        // they assign a new one first.
        if($colorset->is_default) {
            $messageArr["error"][] = 'Colorset ' . $colorset->name . ' is currently set as default. Please assign a different colorset as default and try deleting again.';
            $request->session()->flash('error',$messageArr["error"]);
            return back();
        }

        // A colorset can have up to 10 colors attached to it so delete those as well. The colors
        // are unique to the colorset so no worries about ruining other colorsets.
        $colorsCount = word_cloud_color::where('colorset_id','=',$colorset->id)->count();
        $colorsetName = $colorset->name;

        if($colorsCount > 0) {
            word_cloud_color::where('colorset_id','=',$colorset->id)->delete();
        }
        $colorset->delete();

        // Summary message for logging and display
        $message = 'Successfully deleted Word Cloud Colorset "' . $colorsetName . '" and ' . $colorsCount . ' child colors.';
        $messageArr["success"][] = $message;

        $db_action = new dbAction();
        $db_action->username = Auth::user()->name;
        $db_action->message = $message;
        $db_action->save();

        // The user can only delete from the edit panel, so don't send them Back() there, redirect them to
        // colorset home.
        $request->session()->flash('success',$messageArr["success"]);
        return redirect()->route('colorsets');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Chatty\word_cloud_colorset  $word_cloud_colorset
     * @return \Illuminate\Http\Response
     */
    public function destroyColor(Request $request, word_cloud_color $color)
    {
        $this->authorize('delete',word_cloud_colorset::find($color->colorset_id));

        $message = 'Successfully deleted Word Cloud Color ' . $color->id . ' (' . $color->color . ') with sequence number ' . $color->sequence_num .
                ' for colorset ' . word_cloud_colorset::find($color->colorset_id)->name . '.';
        $color->delete();

        // Summary message for logging and display
        $messageArr["success"][] = $message;

        $db_action = new dbAction();
        $db_action->username = Auth::user()->name;
        $db_action->message = $message;
        $db_action->save();

        $request->session()->flash('success',$messageArr["success"]);
        return back();
    }
}
