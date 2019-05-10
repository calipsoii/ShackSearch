<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SearchPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     *  Authorize Administrators to perform all actions.
     * 
     *  @param \App\User $user
     *  @param "name of a method within this class (EventPolicy)"
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
     * Determine whether the user can view the Search function and
     * execute a search.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function search(User $user)
    {
        //
        return $user->hasAnyRole(['User','Superuser']);
    }


    /**
     *  Determine whether user may view the Administrative functionality
     *  at the top of the Search page (index, delete, stats, etc).
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAdmin(User $user)
    {
        return $user->hasAnyRole(['Superuser']);
    }

    /**
     * Determine whether the user may submit an object to be indexed.
     * 
     * @param \App\User $user
     * @return mixed
     */
    public function submitIndex(User $user)
    {
        //
    }

    /**
     * Determine whether the user may update search settings.
     * 
     * @param App\User $user
     * @return mixed
     */
    public function update(User $user)
    {
        //
    }
}
