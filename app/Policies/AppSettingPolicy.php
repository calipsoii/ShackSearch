<?php

namespace App\Policies;

use App\User;
use App\Chatty\app_setting;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppSettingPolicy
{
    use HandlesAuthorization;

    /**
     *  Authorize Administrators to perform all actions.
     * 
     *  @param \App\User $user
     *  @param "name of a method within this class (AppSettingPolicy)"
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
     *  Determine whether the user can view a list of all app_settings. Different from
     *  'view' in that it doesn't require an instance of event. Sometimes we might
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
     * Determine whether the user can view the app_settings.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\app_setting  $appsetting
     * @return mixed
     */
    public function view(User $user, app_setting $appsetting)
    {
        //
    }

    /**
     * Determine whether the user can create appsetting.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the appsetting.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\app_setting  $appsetting
     * @return mixed
     */
    public function update(User $user, app_setting $appsetting)
    {
        //
    }

    /**
     * Determine whether the user can delete the appsetting.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\app_setting  $appsetting
     * @return mixed
     */
    public function delete(User $user, app_setting $appsetting)
    {
        //
    }
}
