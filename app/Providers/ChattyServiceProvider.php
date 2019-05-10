<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Chatty\Chatty;

class ChattyServiceProvider extends ServiceProvider
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
        $this->app->bind('App\Chatty\Contracts\ChattyContract', 'App\Chatty\Chatty');
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides()
    {
        return ['App\Chatty\Contracts\ChattyContract'];

    }
}
