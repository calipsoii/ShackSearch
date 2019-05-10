<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class word_cloud_colorset extends Model
{

    //
    public function wordclouds()
    {
        return $this->belongsTo('App\Chatty\word_clouds', 'id', 'word_cloud_colorset');
    }

    //
    public function colors()
    {
        return $this->hasMany('App\Chatty\word_cloud_color', 'colorset_id', 'id');
    }

    //
    public static function getActive()
    {
        return word_cloud_colorset::where('active','true')->get();
    }
}
