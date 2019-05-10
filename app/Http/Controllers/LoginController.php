<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

use App\Chatty\app_setting;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     *  OVERRIDING INDEX FUNCTION
     * 
     *  I want to keep both the Laravel auth functionality and also provide WinChatty auth. I don't want
     *  to roll a new guard and provider and all that - I just want to quickly auth the user, create them
     *  a proxy account if needed, and log them in.
     * 
     *  The easiest way to do this is to redirect the user to two different forms (with two different targets)
     *  depending on whether WinChatty auth is enabled and requested.
     * 
     *  @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        // If WinChatty logins 
        $winchattyLoginAllowed = FALSE;
        if(app_setting::winchattyRegAllowed()) 
        {
            $winchattyLoginAllowed = TRUE;
        }
            
        // Load the display flag into session and render the login form
        return \View::make('auth.login')->with('winchattyLoginAllowed',$winchattyLoginAllowed);        
    }

    /**
     *  As per Laravel 5.6 documentation, we can switch from email to username
     *  authentication by creating this method.
     * 
     *  Return whichever field in the 'users' table you'd like to use as the identifier.
     *  Remember to update the views to reflect.
     */
    
    public function username()
    {
        return 'username';
    }

    /**
     * Finally found the way to add an additional column to the default Auth::attempt() function.
     * http://laraveldaily.com/auth-login-how-to-check-more-than-just-emailpassword/
     */
    public function credentials(Request $request)
    {
        $credentials = $request->only($this->username(), 'password');
        $credentials = array_add($credentials, 'winchatty_user', false);
        return $credentials;
    }
    
}
