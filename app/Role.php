<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    //
    
    /**
     *  Mike 2018-03-22: adding user roles to the application
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
