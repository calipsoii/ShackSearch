<?php

namespace App\Policies;

use App\User;
use App\Chatty\word_cloud_colorset;
use Illuminate\Auth\Access\HandlesAuthorization;

class ColorsetPolicy
{
    use HandlesAuthorization;

    /**
     *  Authorize Administrators to perform all actions.
     * 
     *  @param \App\User $user
     *  @param "name of a method within this class (WordCloudPolicy)"
     *  @return mixed (boolean or NULL)
     */
    public function before($user, $ability)
    {
        if($user->hasAnyRole(['ColorsetAdmin','Administrator'])) {
            return true;
        } else {
            return NULL;
        }
    }

    /**
     *  Determine whether the user can view the word cloud colorset module. Different from
     *  'view' in that it doesn't require an instance of word_cloud_colorset. Sometimes we might
     *  not HAVE an instance (if there are 0 rows in the table) so we won't have
     *  one to pass to even let the user to the page to view them.
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAll(User $user)
    {
        return $user->hasAnyRole(['Superuser','Administrator']);
    }

    /**
     * Determine whether the user can view the wordCloudColorset.
     *
     * @param  \App\User  $user
     * @param  \App\word_cloud_colorset  $wordCloudColorset
     * @return mixed
     */
    public function view(User $user, word_cloud_colorset $wordCloudColorset)
    {
        //
    }

    /**
     * Determine whether the user can create wordCloudColorsets.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the wordCloudColorset.
     *
     * @param  \App\User  $user
     * @param  \App\word_cloud_colorset  $wordCloudColorset
     * @return mixed
     */
    public function update(User $user, word_cloud_colorset $wordCloudColorset)
    {
        //
    }

    /**
     * Determine whether the user can delete the wordCloudColorset.
     *
     * @param  \App\User  $user
     * @param  \App\word_cloud_colorset  $wordCloudColorset
     * @return mixed
     */
    public function delete(User $user, word_cloud_colorset $wordCloudColorset)
    {
        //
    }
}
