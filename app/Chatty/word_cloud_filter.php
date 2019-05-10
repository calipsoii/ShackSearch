<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class word_cloud_filter extends Model
{
    //
    public static function getActive()
    {
        return word_cloud_filter::where('enabled','true')->orderBy('name','asc')->get();
    }

    public static function getFilterId($filterName)
    {
        return word_cloud_filter::where('name','=',$filterName)->first()->id;
    }
}
