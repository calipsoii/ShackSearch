<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class event extends Model
{
    protected $primaryKey = 'event_id';
    

    /**
     *  Returns the newest event ID in the database. Purely for reporting, when querying for 
     *  events, use the values stored in app_settings->last_event_id.
     */
    public static function newestEventId()
    {
        return event::orderBy('event_id','desc')->first()->event_id;
    }
}