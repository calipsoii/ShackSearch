<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;

class postcategory extends Model
{
    protected $table = 'post_category';

    /**
     *  @param string with post/thread category ('nuked','ontopic','NSFW','informative')
     *  @return unsigned int ID for that category (currently 1-7)
     */
    public static function categoryId($strPostCategory)
    {
        return postcategory::where('category',$strPostCategory)->first()->id;
    }

    /**
     *  @param unsigned integer (numeric post category; 7=nuked, 1=ontopic...)
     *  @return string category title
     */
    public static function categoryName($numericCategory)
    {
        return postcategory::where('id',$numericCategory)->first()->category;
    }
}