<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class word_cloud extends Model
{
    //
    protected $table = 'word_clouds';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    public function colorset()
    {
        return $this->hasOne('App\Chatty\word_cloud_colorset', 'id', 'word_cloud_colorset');
    }

    public function colors()
    {
        return $this->hasManyThrough(
            'App\Chatty\word_cloud_color',
            'App\Chatty\word_cloud_colorset',
            'id',
            'colorset_id',
            'word_cloud_colorset',
            'id'
        );
    }

    /*protected $fillable = ['percent_complete','status'];

    protected $casts = [
        'percent_complete' => 'integer',
    ];*/

    public function user()
    {
        return $this->belongsTo('App\User','name', 'user');
    }
}
