<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Chatty\ElasticSearch;
use App\Chatty\Contracts\SearchContract;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;


class SearchServiceProvider extends ServiceProvider
{
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
        //$this->app->bind('App\Chatty\Contracts\SearchContract', 'App\Chatty\ElasticSearch');
        $this->app->singleton(SearchContract::class, function($app) {
            if(config('services.search.enabled')) {
                return new ElasticSearch($app->make(Client::class));
            }
        });

        $this->bindSearchClient();
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides()
    {
        return ['App\Chatty\Contracts\SearchContract'];

    }

    /**
     * 
     */
    private function bindSearchClient()
    {
        $this->app->bind(Client::class, function ($app) {
            return ClientBuilder::create()
                ->setHosts(config('services.search.hosts'))
                ->build();
        });
    }
}
