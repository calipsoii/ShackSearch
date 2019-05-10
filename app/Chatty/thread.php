<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class thread extends Model
{
    //

    /**
     * Get all the posts owned by this thread.
     */
    public function posts()
    {
        return $this->hasMany(post::class);
    }

    /**
     * Get all the post_lols owned by posts owned by this thread.
     */
    public function post_lols()
    {
        return $this->hasManyThrough(post_lol::class,post::class);
    }


}
