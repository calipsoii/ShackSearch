<?php

namespace App\Providers;

use App\User;
use App\Policies\UserPolicy;
use App\Role;
use App\Policies\RolePolicy;
use App\Chatty\event;
use App\Policies\EventPolicy;
use App\Chatty\app_setting;
use App\Policies\AppSettingPolicy;
use App\Chatty\post;
use App\Policies\PostPolicy;
use App\Chatty\thread;
use App\Policies\ThreadPolicy;
use App\Chatty\dbAction;
use App\Policies\LogPolicy;
use App\Chatty\ElasticSearch;
use App\Policies\SearchPolicy;
use App\Chatty\word_cloud;
use App\Policies\WordCloudPolicy;
use App\Chatty\word_cloud_colorset;
use App\Policies\ColorsetPolicy;
use App\Chatty\monitor;
use App\Policies\MonitorPolicy;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        //'App\Model' => 'App\Policies\ModelPolicy',
        Role::class => RolePolicy::class,
        User::class => UserPolicy::class,
        event::class => EventPolicy::class,
        app_setting::class => AppSettingPolicy::class,
        post::class => PostPolicy::class,
        thread::class => ThreadPolicy::class,
        dbAction::class => LogPolicy::class,
        ElasticSearch::class => SearchPolicy::class,
        word_cloud::class => WordCloudPolicy::class,
        word_cloud_colorset::class => ColorsetPolicy::class,
        monitor::class => MonitorPolicy::class,

    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // 
    }
}
