<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class dbAction extends Model
{
    //

    /**
     *  Returns the message text for a specified dbAction ID
     */
    public static function message($id)
    {
        return dbAction::find($id)->message;
    }

}