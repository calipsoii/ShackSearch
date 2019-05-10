<?php

namespace App\Policies;

use App\User;
use App\Chatty\event;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventPolicy
{
    use HandlesAuthorization;

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
     *  Determine whether the user can view a list of all events. Different from
     *  'view' in that it doesn't require an instance of event. Sometimes we might
     *  not HAVE an instance (if there are 0 rows in the table) so we won't have
     *  one to pass to even let the user to the page to view them.
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAll(User $user)
    {
        return $user->hasAnyRole(['EventAdmin','Superuser']);
    }

    /**
     * Determine whether the user can view the event.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\event  $event
     * @return mixed
     */
    public function view(User $user, event $event)
    {
        //
        return $user->hasAnyRole(['EventAdmin','Superuser']);
    }

    /**
     * Determine whether the user can create events.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
        return $user->hasRole('EventAdmin');
    }

    /**
     * Determine whether the user can update the event.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\event  $event
     * @return mixed
     */
    public function update(User $user, event $event)
    {
        //
        return $user->hasRole('EventAdmin');
    }

    /**
     * Determine whether the user can delete the event.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\event  $event
     * @return mixed
     */
    public function delete(User $user, event $event)
    {
        //
    }
}
