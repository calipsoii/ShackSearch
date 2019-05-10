<?php

namespace App\Policies;

use App\User;
use App\Chatty\word_cloud;
use Illuminate\Auth\Access\HandlesAuthorization;

class WordCloudPolicy
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
        if($user->hasRole('Administrator')) {
            return true;
        } else {
            return NULL;
        }
    }

    /**
     *  Determine whether the user can view the word clouds module. Different from
     *  'view' in that it doesn't require an instance of word_cloud. Sometimes we might
     *  not HAVE an instance (if there are 0 rows in the table) so we won't have
     *  one to pass to even let the user to the page to view them.
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAll(User $user)
    {
        return $user->hasAnyRole(['User','Superuser','Administrator']);
    }

    /**
     * Determine whether the user can view the wordCloud.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\word_cloud  $wordCloud
     * @return mixed
     */
    public function viewCloud(User $user, word_cloud $wordCloud)
    {
        //
        if($wordCloud->share_cloud == "Chatty" || $wordCloud->share_cloud == 'Anyone') {
            return true;
        } else {
            if(($wordCloud->share_cloud == "Self" && (strtolower($user->name) == strtolower($wordCloud->created_by))) ||
                $this->createForOthers($user)) {
                    return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Determine whether the user can view the data table for the word cloud.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\word_cloud  $wordCloud
     * @return mixed
     */
    public function viewTable(User $user, word_cloud $wordCloud)
    {
        //
        if($wordCloud->share_table_download == "Chatty" || $wordCloud->share_table_download == 'Anyone') {
            return true;
        } else {
            if(($wordCloud->share_table_download == "Self" && (strtolower($user->name) == strtolower($wordCloud->created_by))) ||
                $this->createForOthers($user)) {
                    return true;
            } else {
                return false;
            }
        }
    }

    /**
     *  Determine whether user may view the Administrative functionality
     *  at the top of the Word Clouds page (Display Sentiment, etc).
     * 
     *  @param \App\User $user
     *  @return mixed
     */
    public function viewAdmin(User $user)
    {
        return $user->hasAnyRole(['Superuser','Administrator']);
    }

    /**
     * Determine whether the user can create wordClouds.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
        return $user->hasAnyRole(['User','Superuser','Administrator']);
    }

    /**
     * Determine whether the user can update the wordCloud.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\word_cloud  $wordCloud
     * @return mixed
     */
    public function update(User $user, word_cloud $wordCloud)
    {
        $returnValue = false;

        // Exclude guests and anonymous people from editing
        if($user->hasAnyRole(['User','Superuser','Administrator'])) {
            // Restrict updates to either the users own cloud 
            // or superusers who can create on behalf of others
            if(strtolower($user->name) == strtolower($wordCloud->created_by) || $this->createForOthers($user)) {
                $returnValue = TRUE;
            }
        }
        return $returnValue;
    }

    /**
     * Determine whether the user can delete the wordCloud.
     *
     * @param  \App\User  $user
     * @param  \App\Chatty\word_cloud  $wordCloud
     * @return mixed
     */
    public function delete(User $user, word_cloud $wordCloud)
    {
        //
        return $user->hasAnyRole(['User','Superuser','Administrator']);
    }

    /**
     * Determine whether the user can create wordclouds for other users.
     * 
     * @param  \App\User  $user
     * @param  \App\Chatty\word_cloud  $wordCloud
     * @return mixed
     */
    public function createForOthers(User $user)
    {
        //
        return $user->hasAnyRole(['Administrator']);
    }

    /**
     * Determine whether the user can view the table and/or download this cloud.
     */
    public function viewTableAndDownload(User $user,word_cloud $wordCloud)
    {
        // We are not allowing 'anyone' to view the table and download, only signed in
        // Chatty users. If some external user is really so torn up about it, they can
        // create a free Shacknews account.

        if($wordCloud->share_table_download == "Chatty" || $wordCloud->share_table_download == "Anyone") {
            return true;
        } else {
            if(($wordCloud->share_table_download == "Self" && (strtolower($user->name) == strtolower($wordCloud->created_by))) ||
                $this->createForOthers($user)) {
                    return true;
            } else {
                return false;
            }
        }
    }
}
