<?php

namespace App\Observers;

use App\Chatty\posts;
use Elasticsearch\Client;

class ElasticSearchObserver
{
    private $elasticsearch;

    /**
     * Instantiate an instance of PostObserver class.
     * 
     * @param \Elasticsearch\Client $elasticsearch
     * @return void
     */
    public function __construct(Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Listen to the Post created event.
     * 
     * @param \App\Chatty\post $post
     * @return void
     */
    public function created(Post $post)
    {
        //
    }

    /**
     * Listen to the Post saved event.
     * 
     * @param \App\Chatty\post $post
     * @return void
     */
    public function saved(Post $post)
    {
        // Elasticsearch library delivers client and expects
        // the following info to be able to submit a request for 
        // indexing.
        $this->elasticsearch->index([
            'index' => $post->getSearchIndex(),
            'type' => $post->getSearchType(),
            'id' => $post->id,
            'body' => $post->toSearchArray(),
        ]);
    }

    /**
     * Listen to the Post deleting event.
     * 
     * @param \App\Chatty\post $post
     * @return void
     */
    public function deleting(User $user)
    {
        // To remove an object from Elasticsearch using Client,
        // the following pieces of data are needed.
        $this->elasticsearch->delete([
            'index' => $post->getSearchIndex(),
            'type' => $post->getSearchType(),
            'id' => $post->id,
        ]);

    }

}