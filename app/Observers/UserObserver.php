<?php

namespace App\Observers;

use App\User;
use App\Chatty\dbAction;
use App\Mail\UserRegistered;

use Illuminate\Support\Facades\Mail;

class UserObserver
{
    /**
     * Handle to the User "created" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function created(User $user)
    {
        //
        Mail::to(User::where('name','=','Mike')->first())->send(new UserRegistered($user));
    }

    /**
     * Handle the User "updated" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function updated(User $user)
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function deleted(User $user)
    {
        //
    }
}