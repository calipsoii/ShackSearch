<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

use DB;
use Auth;

use App\Chatty\thread;
use App\Chatty\post;
use App\Chatty\post_lol;
use App\Chatty\dbAction;
use App\Chatty\app_setting;

use App\Chatty\Contracts\ChattyContract;

class ThreadController extends Controller
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
        $hoursToDisplayThread = Carbon::now()->subHours(app_setting::hoursToDisplay());
        $threads = thread::where('date','>=',$hoursToDisplayThread)->orderBy('bump_date','desc')->get();

        // load them into the view and pass it back
        return \View::make('threads.index')->with('threads',$threads);
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
        $this->authorize('create', thread::class);

        //
        $validator = \Validator::make($request->all(), [
            'singleThreadID' => array('regex:/^\d{1,8}(,\d{1,8})*$/'),
        ]);
        
        if($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator);
        }

        /* User is importing a thread */
        if($request->has('importThreads'))
        {
            $messages = $this->chatty->importThreadFromWinchatty($request->input('threadsToRetrieve'));
        }

        return back()->with('messageIds',$messages);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Chatty\thread  $thread
     * @return \Illuminate\Http\Response
     */
    public function show(thread $thread, post $post = null)
    {
        // No reason that anyone (even guests) can't view the individual thread
        //$this->authorize('view',$thread);

        // Return an eager-loaded thread with all the posts & lol's easily accessible
        $threads = thread::with('posts.post_lols')->where('id','=',$thread->id)->get();

        // Return the array of thread\posts\post_lols to the threads.show view. If the user
        // didn't supply the optional post parameter, highlightedPost will be NULL and can
        // be tested for in the Blade template
        return view('threads.show',['threads' => $threads])
            ->with('singleThread',TRUE)
            ->with('highlightedPost',$post);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Chatty\thread  $thread
     * @return \Illuminate\Http\Response
     */
    public function edit(thread $thread)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Chatty\thread  $thread
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, thread $thread)
    {
        //
        $this->authorize('update', $thread);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Chatty\thread  $thread
     * @return \Illuminate\Http\Response
     */
    public function destroy(thread $thread)
    {
        $this->authorize('delete', $thread);

        /* Destroy *should* only be called from a page with a 'delete' button, which *should* prevent
            odd data from coming through. JUST IN CASE though, do a bit of validation. */
            if( !is_numeric($id)) {
                return back()->withInput('error','Must pass a numeric ID to be deleted.');
            } else if ($id < 0 || $id > 2147483647) {
                return back()->withInput('error','Post ID must be between 0 and 2147483647.');
            }
    
            // All of the logic and heavy lifting is handled behind the scenes by the Chatty class
            // (which is provided through the interface variable)
            $counts = $this->chatty->deleteThread($id);
    
            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = 'Successfully deleted thread ' . $id . '. -- Posts deleted: ' . 
                $counts->postRowCounts["delete"] . '. -- LOLs deleted: ' . $counts->lolRowCounts["delete"] . '.';
            $db_action->save();
            
            // Return all dbAction logs with the id's in our retVal array
            $messages = [];
            array_push($messages,$db_action->message);
    
            /* Return the user to the Admin panel and display the message (if any). */
            return back()->with('messageIds',$messages);
    }
}
