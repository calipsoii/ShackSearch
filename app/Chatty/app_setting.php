<?php

namespace App\Chatty;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Encryption\DecryptException;
use Auth;

class app_setting extends Model
{
    protected $table = 'app_settings';

    /**
     *  Returns lastEventId, which should be passed when next polling for events.
     */
    public static function lastEventId()
    {
        return app_setting::orderBy('id','desc')->first()->last_event_id;
    }

    /**
     *  Returns lastEventId, which should be passed when next polling for events.
     */
    public static function eventPollUsername()
    {
        return app_setting::orderBy('id','desc')->first()->event_poll_username;
    }

    /**
     *  Returns ActivelyCreateThreadsPosts flag. If TRUE, when a post or thread
     *  is found to be missing on Winchatty import, reach back out to Winchatty
     *  and retrieve the missing data and store it.
     */
    public static function activelyCreateThreadsPosts()
    {
        return app_setting::orderBy('id','desc')->first()->actively_create_threads_posts;
    }

    /**
     *  Returns ChattyViewSubthreadsToDisplay. If a thread has more than this many posts
     *  only display this many subthreads in the Chatty view by default.
     */
    public static function subthreadsToDisplay()
    {
        return app_setting::orderBy('id','desc')->first()->chatty_view_subthreads_to_display;
    }

    /**
     *  Returns the subthread truncate length. This is how many characters to display in the
     *  collapsed threaded view.
     */
    public static function subthreadTruncateLength()
    {
        return app_setting::orderBy('id','desc')->first()->subthread_truncate_length;
    }

    /**
     *  Returns ChattyViewHoursToDisplayThread. This will be how long a thread shows on the
     *  default Chatty view. Right now it's 18 hours (just like Shacknews Chatty).
     */
    public static function hoursToDisplay()
    {
        return app_setting::orderBy('id','desc')->first()->chatty_view_hours_to_display_thread;
    }

    /**
     *  Pagination for Events: how many Events to display on each paginated page of the Events log
     */
    public static function eventsPerPage()
    {
        return app_setting::orderBy('id','desc')->first()->events_to_display_per_page;
    }

    /**
     *  Pagination for Search: how many Search results to display
     */
    public static function searchResultsPerPage()
    {
        return app_setting::orderBy('id','desc')->first()->num_search_results_per_page;
    }

    /**
     * This timestamp gets updated each time the /search/crawl route is accessed
     */
    public static function lastSearchCrawl()
    {
        return app_setting::orderBy('id','desc')->first()->search_crawler_last_run;
    }

    /**
     * 
     */
    public static function searchCrawlerEnabled()
    {
        return app_setting::orderBy('id','desc')->first()->search_crawler_enabled;
    }

    /**
     * The number of posts to index each time the automated crawler runs
     */
    public static function postsToIndex()
    {
        return app_setting::orderBy('id','desc')->first()->search_crawler_posts_to_index;
    }

    /**
     * The username to log all the search crawl actions under.
     */
    public static function searchCrawlerUsername()
    {
        return app_setting::orderBy('id','desc')->first()->search_crawler_username;
    }

    /**
     *  Pagination for Logs: how many Logs to display on each paginated page of the Logs page
     */
    public static function logsPerPage()
    {
        return app_setting::orderBy('id','desc')->first()->logs_to_display_per_page;
    }

    /**
     *  Logging Level: how much information to log to the db_action log. 1=least verbose; 5=most verbose.
     */
    public static function loggingLevel()
    {
        return app_setting::orderBy('id','desc')->first()->logging_level;
    }

    /**
     *  This timestamp gets updated each time the /events/poll route is accessed
     */
    public static function lastEventPoll()
    {
        return app_setting::orderBy('id','desc')->first()->last_event_poll;
    }

    /**
     *  The working block is a block of 100k post ID's mainly used by the Mass Sync process to populate
     *  the local DB from Winchatty
     */
    public static function workingBlock()
    {
        return app_setting::orderBy('id','desc')->first()->mass_sync_working_block;
    }

    /**
     *  The stop block allows us to set boundaries on the Mass Sync process so that it only tries to download
     *  within a certain window.
     */
    public static function stopPost()
    {
        return app_setting::orderBy('id','desc')->first()->mass_sync_stop_post;
    }

    public static function advanceDesc() {
        return app_setting::orderBy('id','desc')->first()->mass_sync_advance_desc;
    }

    /**
     *  Number of threads to retrieve at a time when the Mass Sync process is run.
     */
    public static function threadsToRetrieve()
    {
        return app_setting::orderBy('id','desc')->first()->mass_sync_threads_to_retrieve;
    }

    /**
     *  The username to put in the logs when the Mass Sync process is run and any data is altered.
     */
    public static function massSyncUsername()
    {
        return app_setting::orderBy('id','desc')->first()->mass_sync_username;
    }

    /**
     *  The last time the mass sync post retrieval process was run.
     */
    public static function lastMassSync()
    {
        return app_setting::orderBy('id','desc')->first()->mass_sync_last_sync_run;
    }

    /** 
     *  The username to record while writing db_action logs
     */
    public static function nameToLog()
    {
        return auth::user()->name;
    }

    /**
     *  Event Poll toggle: TRUE = automatically poll for events every 1 minute.
     */
    public static function eventPollEnabled()
    {
        return app_setting::orderBy('id','desc')->first()->event_poll_enabled;
    }

    /**
     *  Mass Post Sync toggle: TRUE = automatically poll for posts every 1 minute.
     */
    public static function massPostSyncEnabled()
    {
        return app_setting::orderBy('id','desc')->first()->mass_post_sync_enabled;
    }

    /**
     *  Mass Post Sync Auto Block Advance: when current block finishes downloading, whether to move to next block 
     */
    public static function massPostSyncAutoBlockAdvance()
    {
        return app_setting::orderBy('id','desc')->first()->mass_post_sync_auto_block;
    }

    /**
     *  Allow Winchatty Registrations: whether users are allowed to login via Winchatty (creating themselves a profile here)
     */
    public static function winchattyRegAllowed()
    {
        return app_setting::orderBy('id','desc')->first()->winchatty_registration_allowed;
    }

    /**
     *  Return the proxy password used by accounts that are authenticated through WinChatty API. The text of this password
     *  is stored encrypted in the app_settings table and encrypted/decrypted at runtime in memory. The password grants
     *  the role of User (at most) and is generally harmless.
     */
    public static function decryptedProxyPassword()
    {
        $passToDecrypt = app_setting::orderBy('id','desc')->first()->proxy_password;

        $decrypted = decrypt($passToDecrypt);

        return $decrypted;
    }

    /**
     *  Return the proxy email used by accounts that are authenticated through WinChatty API.
     */
    public static function proxyEmail()
    {
        return app_setting::orderBy('id','desc')->first()->proxy_email;
    }

    /**
     *  Returns latest instance of app_setting
     */
    public static function getlatestAppSettings()
    {
        return app_setting::orderBy('id','desc')->first();
    }

    /**
     * Returns the Elastic search index for posts
     */
    public static function getPostSearchIndex()
    {
        return app_setting::orderBy('id','desc')->first()->elastic_post_search_index;
    }

    /**
     * Returns the Elastic search index type for posts
     */
    public static function getPostSearchType()
    {
        return app_setting::orderBy('id','desc')->first()->elastic_post_type;
    }

    /**
     * The max number of results to return in an Elastic query
     */
    public static function getMaxSearchResults()
    {
        return app_setting::orderBy('id','desc')->first()->elastic_max_results;
    }


    /**
     * Returns number of posts to bundle up and send in each individual Elastic message.
     */
    public static function getIndexBatchSize()
    {
        return app_setting::orderBy('id','desc')->first()->search_crawler_batch_size;
    }

    /**
     * Returns the number of days of Event history to keep. Anything older will be deleted nightly.
     */
    public static function getEventsDaysToKeep()
    {
        return app_setting::orderBy('id','desc')->first()->events_days_to_keep;
    }

    /**
     * Returns the number of days of Event history to keep. Anything older will be deleted nightly.
     */
    public static function getLogsDaysToKeep()
    {
        return app_setting::orderBy('id','desc')->first()->logs_days_to_keep;
    }

    /**
     * Whether or not the automatic post counting function is enabled
     */
    public static function postCountEnabled()
    {
        return app_setting::orderBy('id','desc')->first()->post_count_enabled;
    }

    /**
     * The username that the post count function should use and log under
     */
    public static function postCountUsername()
    {
        return app_setting::orderBy('id','desc')->first()->post_count_username;
    }

    /**
     * How many posts are in each bracket for count and display (default 100k)
     */
    public static function postCountBracketSize()
    {
        return app_setting::orderBy('id','desc')->first()->post_count_bracket_size;
    }

    /**
     * Total number of posts to count and display (default 40M)
     */
    public static function postCountTotal()
    {
        return app_setting::orderBy('id','desc')->first()->post_count_max;
    }

    /**
     * The last time the post count job ran to update totals
     */
    public static function postCountLastRun()
    {
        return app_setting::orderBy('id','desc')->first()->post_count_last_run;
    }

    /**
     * Whether to include sentiment analysis in WordClouds
     */
    public static function displayWordCloudSentiment()
    {
        return app_setting::orderBy('id','desc')->first()->word_cloud_display_sentiment;
    }

    /**
     * Max number of months to allow users to select for word cloud generation. Set to 0 for unlimited.
     */
    public static function wordCloudMonthLimit()
    {
        return app_setting::orderBy('id','desc')->first()->word_cloud_month_limit;
    }

    /**
     * Max number of months to allow users to select for word cloud generation with phases. Set to 0 for unlimited.
     */
    public static function wordCloudPhraseMonthLimit()
    {
        return app_setting::orderBy('id','desc')->first()->word_cloud_phrase_month_limit;
    }

    /**
     * When querying term statistics, how many post ID's to send in a batch
     */
    public static function wordCloudElasticBatchSize()
    {
        return app_setting::orderBy('id','desc')->first()->word_cloud_elastic_terms_batch_size;
    }

    /**
     * The number of workers currently configured to process jobs under Supervisor. Purely for
     * display purposes.
     */
    public static function wordCloudTotalWorkers()
    {
        return app_setting::orderBy('id','desc')->first()->word_cloud_total_workers;
    }

    /**
     * 
     */
    public static function wordCloudMaxPerUser() {
        return app_setting::orderBy('id','desc')->first()->word_cloud_max_per_user;
    }

    public static function wordCloudTermsPerCloud() {
        return app_setting::orderBy('id','desc')->first()->word_cloud_terms_per_cloud;
    }
    public static function wordCloudPhrasesDefault() {
        return app_setting::orderBy('id','desc')->first()->wordcloud_phrases_default;
    }

    public static function dailyCloudHours() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_hours;
    }
    public static function dailyCloudUser() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_user;
    }
    public static function dailyCloudFilter() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_filter;
    }
    public static function dailyCloudColorset() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_colorset;
    }
    public static function dailyCloudPerms() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_cloud_perms;
    }
    public static function dailyCloudTablePerms() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_table_perms;
    }
    public static function dailyCloudGUID() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_guid;
    }
    public static function dailyCloudActive() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_active;
    }

    public static function dailyCloudOntopic() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_ontopic;
    }
    public static function dailyCloudNWS() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_nws;
    }
    public static function dailyCloudStupid() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_stupid;
    }
    public static function dailyCloudPolitical() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_political;
    }
    public static function dailyCloudTangent() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_tangent;
    }
    public static function dailyCloudInformative() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_informative;
    }
    public static function dailyCloudNuked() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_nuked;
    }
    public static function dailyCloudPhrases() {
        return app_setting::orderBy('id','desc')->first()->chatty_daily_wordcloud_phrases;
    }
    public static function twoTermPhraseCountThreshold() {
        return app_setting::orderBy('id','desc')->first()->wordcloud_phrases_2term_threshold;
    }
    public static function threeTermPhraseCountThreshold() {
        return app_setting::orderBy('id','desc')->first()->wordcloud_phrases_3term_threshold;
    }
    public static function phraseDisplayThreshold() {
        return app_setting::orderBy('id','desc')->first()->wordcloud_phrase_display_threshold;
    }
}