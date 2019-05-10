<?php

namespace App\Policies;

use App\User;
use App\Chatty\monitor;
use Illuminate\Auth\Access\HandlesAuthorization;

class MonitorPolicy
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
     *  Determine whether the user can view a list of all monitors. Different from
     *  'view' in that it doesn't require an instance of monitor. Sometimes we might
     *  not HAVE an instance (if there are 0 rows in the table) so we won't have
     *  one to pass to even let the user to the page to view them.
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAll(User $user)
    {
        return $user->hasAnyRole(['Superuser']);
    }

    /**
     * Determine whether the user can view the monitor.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\monitor  $monitor
     * @return mixed
     */
    public function view(User $user, monitor $monitor)
    {
        //
        if($user->hasAnyRole(['Superuser','Administrator'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can create monitors.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the monitor.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\monitor  $monitor
     * @return mixed
     */
    public function update(User $user, monitor $monitor)
    {
        //
    }

    /**
     * Determine whether the user can delete the monitor.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\monitor  $monitor
     * @return mixed
     */
    public function delete(User $user, monitor $monitor)
    {
        //
    }

    /**
     * Determine whether the user can restore the monitor.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\monitor  $monitor
     * @return mixed
     */
    public function restore(User $user, monitor $monitor)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the monitor.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\monitor  $monitor
     * @return mixed
     */
    public function forceDelete(User $user, monitor $monitor)
    {
        //
    }

    /**
     *  Ensure only Administrators are able to view the email for alerts,
     *  mainly because I'm using my personal address right now. :)
     */
    public function viewMonitorEmail(Monitor $monitor)
    {
        return $user->hasRole('Administrator');
    }
}
