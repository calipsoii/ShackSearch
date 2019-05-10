<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;
use App\Chatty\app_setting;

class post extends Model
{
    /**
     * Get the thread that owns this post.
     */
    public function thread()
    {
        return $this->belongsTo(thread::class);
    }
    
    /**
     * Get the parent post that owns this post.
     */
    public function parent()
    {
        if($this->parent_id != 0) {
            return $this->belongsTo('App\Chatty\post','parent_id');
        } else {
            return 0;
        }
    }

    /**
     * Get the children that this post owns.
     */
    public function children()
    {
        return $this->hasMany('App\Chatty\post','parent_id');
    }

    /**
     * Get the post_lols associated with this post.
     */
    public function post_lols()
    {
        return $this->hasMany(post_lol::class);
    }

    /**
     * Elastic needs an INDEX > TYPE > ID to be passed with the index operation, ie:
     *      PUT twitter/_doc/1
     * 
     * For posts, I'm thinking of going with:
     *      PUT shacknews_chatty_posts/_doc/1
     * 
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-index_.html
     * 
     * @param void
     * @return String 
     */
    public function getSearchIndex()
    {
        return app_setting::getPostSearchIndex();
    }

    /**
     * Elastic needs an INDEX > TYPE > ID to be passed with the index operation, ie:
     *      PUT twitter/_doc/1
     * 
     * For posts, I'm thinking of going with:
     *      PUT shacknews_chatty_posts/_doc/1
     * 
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-index_.html
     * 
     * @param void
     * @return String 
     */
    public function getSearchType()
    {
        return app_setting::getPostSearchType();
    }

    /**
     * Build an array of data from the model to be passed to Elastic.
     * 
     * @return Array
     */
    public function toSearchArray()
    {
        $postDataArray = [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'author' => $this->author_c,
            'body' => $this->body_c,
            'date' => $this->date,
        ];

        return $postDataArray;
        //return $this->toArray();
    }

}
