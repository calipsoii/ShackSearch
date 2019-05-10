<?php

namespace App\Policies;

use App\User;
use App\Chatty\dbAction;
use Illuminate\Auth\Access\HandlesAuthorization;

class LogPolicy
{
    use HandlesAuthorization;

    /**
     *  Authorize Administrators to perform all actions.
     * 
     *  @param \App\User $user
     *  @param "name of a method within this class (LogPolicy)"
     *  @return mixed (boolean or NULL)
     */
    public function before($user, $ability)
    {
        if($user->hasRole('Administrator')) {
            return true;
        } else {
            return NULL;
        }
    }

    /**
     *  Determine whether the view is allowed to see ANY of the Logs page.
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAll(User $user)
    {
        return $user->hasRole('Superuser');
    }

    /**
     *  Determine whether user may view the Administrative functionality
     *  at the top of the Logs page (delete old, cleanup schedule, etc).
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAdmin(User $user)
    {
        return $user->hasRole('Superuser');
    }

    /**
     * Determine whether the user can view the dbAction.
     *
     * @param  \App\User  $user
     * @param  \App\dbAction  $dbAction
     * @return mixed
     */
    public function view(User $user, dbAction $dbAction)
    {
        //
    }

    /**
     * Determine whether the user can create dbActions.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the dbAction.
     *
     * @param  \App\User  $user
     * @param  \App\dbAction  $dbAction
     * @return mixed
     */
    public function update(User $user, dbAction $dbAction)
    {
        //
    }

    /**
     * Determine whether the user can delete the dbAction.
     *
     * @param  \App\User  $user
     * @param  \App\dbAction  $dbAction
     * @return mixed
     */
    public function delete(User $user, dbAction $dbAction)
    {
        //
    }
}
