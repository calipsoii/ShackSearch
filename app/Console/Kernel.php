<?php

namespace App\Console;

use DB;
use Carbon\Carbon;
use App\Chatty\app_setting;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();

        // Call the Winchatty Event Poll function every 1 minute
        // to keep our DB in sync with Winchatty
        $schedule->exec('curl https://nullterminated.org/events/poll')->everyMinute();

        // Once per minute, retrieve historical posts from Winchatty
        // using the Posts subroutines
        //$schedule->exec('curl https://nullterminated.org/posts/sync')->everyMinute();

        // Once per minute, pull the latest posts that are not yet indexed
        // and submit them to ElasticSearch
        $schedule->command('search:crawl')->everyFiveMinutes();

        // Each morning at 02:00, clean up the Event and dbAction table, leaving
        // only {x} days of records.
        $schedule->call(function() {
            DB::table('db_actions')->where('created_at', '<', Carbon::now()->subDays(app_setting::getLogsDaysToKeep()))->delete();
            DB::table('events')->where('created_at','<', Carbon::now()->subDays(app_setting::getEventsDaysToKeep()))->delete();
        })->dailyAt('02:00');

        $schedule->command('posts:count')->hourly();

        $schedule->command('posts:download')->everyMinute();

        $schedule->command('clouds:createdaily')->hourly();

        // $schedule->command('posts:rebuildBodyC 100000')->everyTenMinutes();

        $schedule->command('monitor:events')->everyFifteenMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
