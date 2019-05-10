<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     *  Authorize Administrators to perform all actions.
     * 
     *  @param \App\User $user
     *  @param "name of a method within this class (UserPolicy)"
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
     *  Determine whether the user can view a list of all users. Different from
     *  'view' in that it doesn't require an instance of User. Sometimes we might
     *  not HAVE an instance (if there are 0 rows in the table) so we won't have
     *  one to pass to even let the user to the page to view them.
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAll(User $user)
    {
        return $user->hasRole('Superuser');
    }

    /**
     * Determine whether the user can view the full /edit page for a user.
     *
     * @param  \App\User  $user
     * @param  \App\User  $model
     * @return mixed
     */
    public function view(User $user, User $model)
    {
        if($user->hasAnyRole(['Superuser','Administrator'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * /users/{id}/profile is an abridged version of the full edit page that only shows
     * display name and username. 
     *
     * @param  \App\User  $user
     * @param  \App\User  $model
     * @return mixed
     */
    public function viewProfile(User $user, User $model)
    {
        //
        if($user->id == $model->id) {
            return true;
        } else {
            if($user->hasAnyRole(['Superuser','Administrator'])) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
        return $user->hasRole('Administrator');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\User  $model
     * @return mixed
     */
    public function update(User $user, User $model)
    {
        //
        if($user->id == $model->id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\User  $model
     * @return mixed
     */
    public function delete(User $user, User $model)
    {
        //
    }

    /**
     *  Piggybacking on this policy to determine whether
     *  a user can access the Register and Password Reset
     *  routes.
     */
    public function registerAndReset(User $user)
    {
        if(!$user->winchatty_user) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *  I don't want people looking at the email addresses unless absolutely necessarily,
     *  mostly because they're all set to my personal email. :P
     */
    public function viewEmail(User $user)
    {
        return $user->hasRole('Administrator');
    }
}
