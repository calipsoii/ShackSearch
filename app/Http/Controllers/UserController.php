<?php

namespace App\Http\Controllers;

use DB;
use App\User;
use App\Role;
use App\Chatty\app_setting;
use App\Chatty\dbAction;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use App\Chatty\Contracts\ChattyContract;

class UserController extends Controller
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
        $this->middleware('auth')->except('winchattyLogin');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $users = User::orderBy('username')->get();

        return view('users.index', [
            'users' => $users
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
        $this->authorize('create',User::class);
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
        $this->authorize('update',User::class);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $userRoleIds = DB::select('SELECT role_id FROM role_user WHERE user_id = :id',['id' => $user->id]);
        $userRoles = [];
        foreach($userRoleIds as $role)
        {
            $userRoles[] = $role->role_id;
        }
        return view('users.edit')
            ->with('user',$user)
            ->with('roles',$roles)
            ->with('userRoles',$userRoles);
    }

    /**
     *  Show a simplified Edit form that only displays the Display Name and Username. This
     *  allows the user to customize their Display Name without granting the normal access
     *  to Roles, email, etc.
     */
    public function profile(User $user)
    {
        return view('users.profile')
            ->with('user',$user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('delete',$user);

        //
        $validator = \Validator::make($request->all(), [
            'name' => 'required|max:255',
            'username' => 'required|max:255',
            'password' => 'max:255',
            'email' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('users' . $user->id . '/edit')
                ->withErrors($validator)
                ->withInput();
        } 
        
        $user->name = $request->input('name');
        $user->username = $request->input('username');
        if(strlen($request->input('password')) > 0) {
            $user->password = Hash::make($request->input('password'));
        }
        $user->email = $request->input('email');
        $user->save();

        // Clear the table completely for that user and then load any options that were selected
        $roles = '';
        db::delete('DELETE FROM role_user WHERE user_id = :userid',['userid' => $user->id]);
        if(isset($_POST['roleSelect']))
        {
            // roleSelect contains an array of role id's and that's it
            foreach($_POST['roleSelect'] as $role)
            {
                if(count(DB::table('role_user')->where('role_id',$role)->where('user_id',$user->id)->get()) == 0)
                {
                    DB::insert('INSERT INTO role_user (role_id,user_id) VALUES (:roleid,:userid)',['roleid' => $role, 'userid' => $user->id]);
                }
                $roles .= Role::find($role)->name . ' ';
            }
        }

        $message = 'Successfully updated display name for user ID ' . $user->id . ' to: ' . $user->name . '. Username updated to: ' . 
            $user->username . '. Email updated to: ' . $user->email . '. Roles updated to: ' . $roles . '.';
        $messageArr["success"][] = $message;

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = $message;
            $db_action->save();
        }

        $request->session()->flash('success',$messageArr["success"]);

        return redirect()->route('users.edit', ['user' => $user->id]);
    }

    /**
     *  A simpler update function for use by end users to control their display name. Since
     *  WinChatty allows case-insensitive logins they might create calipsoii when they really
     *  want to see CalipsoII on the site. This gives them an easy way to update it themselves.
     * 
     * @param App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request, User $user)
    {
        $this->authorize('update',$user);

        //
        $validator = \Validator::make($request->all(), [
            'name' => 'required|max:255',
            'username' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('users.profile', ['user' => $user->id])
                ->withErrors($validator)
                ->withInput();
        } 
        
        $user->name = $request->input('name');
        $user->username = $request->input('username');
        $user->save();

        $message = 'Successfully updated display name for user ID ' . $user->id . ' to: ' . $user->name . '. Username updated to: ' . 
            $user->username . '.';
        $messageArr["success"][] = $message;

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            $db_action->username = Auth::user()->name;
            $db_action->message = $message;
            $db_action->save();
        }

        $request->session()->flash('success',$messageArr["success"]);

        return redirect()->route('users.profile', ['user' => $user->id]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $this->authorize('delete',$user);

        DB::delete('DELETE FROM role_user WHERE user_id = :userid',['userid' => $user->id]);
            
        $user->delete();
        return redirect()
                ->route('users');
    }

    /**
     *  PROCESS WINCHATTY LOGIN
     * 
     *  If user clicked checkbox for "Log in using WinChatty credentials" they'll wind up here.
     *  Process their WinChatty login, create them a proxy user here if needed, and sign them into
     *  that proxy user so we can apply security permissions to their viewing.
     * 
     *  @param \Illuminate\Http\Request  $request
     *  @return \Illuminate\Http\Response
     */
    public function winchattyLogin(Request $request)
    {
        // WinChatty must lower-case all login requests because any combination of case seems to work.
        // Laravel is case-sensitive by default, but since we have this handy method, we can replicate
        // the lower-casing of Winchatty to jive.
        $lowerCaseUsername = strtolower($request->input('username'));

        $result = $this->chatty->testWinchattyLogin($request->input('username'), $request->input('password'));
        
        // User entered correct parameters and WinChatty returned 'true'
        if($result)
        {
            // User has never been here but they ARE WinChatty authenticated so create them a proxy user
            // and grant them appropriate role(s).
            //if(!user::where('username','ILIKE',$lowerCaseUsername)->exists())
            if(!DB::table('users')->whereRaw('LOWER(username) = ?',[$lowerCaseUsername])->exists())
            {
                $user = new User;
                $user->name = $request->input('username');
                $user->username = $request->input('username');
                // Retrieve the decrypted-in-memory proxy password, hash it, and store it back in the DB
                $user->password = Hash::make(app_setting::decryptedProxyPassword());
                $user->email = app_setting::proxyEmail();
                $user->winchatty_user = true;
                $user->save();

                // Put the new user account into the User role
                //DB::insert('INSERT INTO role_user (role_id, user_id) VALUES ((SELECT id FROM roles WHERE name = "User"), :userid',['userid' => $request->input('username')]);
                $userRoleQuery = DB::select('SELECT id FROM roles WHERE name = :roletoquery',['roletoquery' => 'User']);
                $userRoleId = $userRoleQuery[0]->id;

                DB::table('role_user')->insert(
                    ['role_id' => $userRoleId,'user_id' => $user->id]
                );

                $message = 'User ' . $request->input('username') . ' successfully authenticated via WinChatty and had a new Laravel user account created with the role "User".';
                if(app_setting::loggingLevel() >= 2)
                {
                    $db_action = new dbAction();
                    $db_action->username = $request->input('username');
                    $db_action->message = $message;
                    $db_action->save();
                }
            }

            /* User now has an account here (pre-existing or new) and they're WinChatty authenticated,
                so log them in with the proxy credentials so that their security gets applied.

                NOTE2: originally this used Auth::attempt, but I couldn't find a way to customize the code
                to lowercase the username. Since the user has been WinChatty authed, it's *probably* safe
                to simply bypass authentication (which is mainly validating password - we're using proxy pass)
                and log the user in directly.
            */
            if(DB::table('users')->whereRaw('LOWER(username) = ?',[$lowerCaseUsername])->exists())
            {
                $userToLogin = DB::table('users')->whereRaw('LOWER(username) = ?',[$lowerCaseUsername])->first();
                $userInstance = User::find($userToLogin->id);
                Auth::login($userInstance);

                $message = 'User ' . $request->input('username') . ' successfully authenticated via WinChatty and was logged into existing Laravel user.';
                if(app_setting::loggingLevel() >= 4)
                {
                    $db_action = new dbAction();
                    $db_action->username = Auth::user()->username;
                    $db_action->message = $message;
                    $db_action->save();
                }
            }
            else
            {
                if(app_setting::loggingLevel() >= 2)
                {
                    $db_action = new dbAction();
                    $db_action->username = 'Guest';
                    $db_action->message = 'User ' . $request->input('username') . ' was validated by WinChatty but a user account to log them into could not be found.';
                    $db_action->save();
                }

                return back()
                        ->withErrors(['password' => ['Something went wrong on login, please contact administrator.']]);
            }

            return redirect()->intended(route('home'));
            //return redirect()
            //    ->route('home');
        }
        // WinChatty rejected these credentials - display that message to the user
        else
        {
            return back()
                    ->withErrors(['password' => ['WinChatty API rejected your login credentials. Please try again.']]);
        }
    }
}
