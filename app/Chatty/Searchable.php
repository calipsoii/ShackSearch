<?php

namespace App\Chatty;

use App\Observer\ElasticSearchObserver;

trait Searchable
{
    /**
     * Used to toggle the search feature flag on and off.
     */
    public static function bootSearchable()
    {
        if(config('services.search.enabled')) {
            static::observe(ElasticSearchObserver::class)
        }
    }

    /**
     * 
     */
    public function getSearchIndex()
    {
        return $this->getTable();
    }

    /**
     * 
     */
    public function getSearchType()
    {
        if(property_exists($this,'useSearchType')) {
            return $this->useSearchType;
        }

        return $this->getTable();
    }

    /**
     * 
     */
    public function toSearchArray()
    {
        return $this->toArray();
    }

}