<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class post_lol extends Model
{
    //

    /**
     * Get the post that owns these post_lols.
     */
    public function post()
    {
        return $this->belongsTo(post::class);
    }
}
