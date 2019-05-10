<?php

namespace App\Policies;

use App\User;
use App\Chatty\post;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
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
     *  Determine whether the view is allowed to see ANY of the Posts page.
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAll(User $user)
    {
        return $user->hasAnyRole(['PostAdmin','Superuser']);
    }

    /**
     *  Determine whether user may view the Administrative functionality
     *  at the top of the Posts page (mass sync options, manual sync, etc).
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAdmin(User $user)
    {
        return $user->hasAnyRole(['PostAdmin','Superuser']);
    }

    /**
     *  Determine whether the logged-in user may view nuked posts or if they are excluded
     */
    public function viewNuked(User $user)
    {
        //
    }
    
    /**
     * Determine whether the user can view the post.
     *
     * @param  \App\User  $user
     * @param  \App\post  $post
     * @return mixed
     */
    public function view(User $user, post $post)
    {
        //
        return $user->hasAnyRole(['PostAdmin','Superuser']);
    }

    /**
     * Determine whether the user can create posts.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
        return $user->hasRole('PostAdmin');
    }

    /**
     * Determine whether the user can update the post.
     *
     * @param  \App\User  $user
     * @param  \App\post  $post
     * @return mixed
     */
    public function update(User $user, post $post)
    {
        //
        return $user->hasRole('PostAdmin');
    }

    /**
     * Determine whether the user can delete the post.
     *
     * @param  \App\User  $user
     * @param  \App\post  $post
     * @return mixed
     */
    public function delete(User $user, post $post)
    {
        //
    }
}
