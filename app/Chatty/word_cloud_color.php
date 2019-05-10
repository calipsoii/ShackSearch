<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class word_cloud_color extends Model
{
    //
    public function colorset()
    {
        return $this->belongsTo('App\Chatty\word_cloud_colorset', 'id', 'colorset_id');
    }
}
