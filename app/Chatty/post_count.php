<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class post_count extends Model
{
    // We're not using 'id' so we have to specify what the primary key is
    protected $primaryKey = 'block_id';
}
