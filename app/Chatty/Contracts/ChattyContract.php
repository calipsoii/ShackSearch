<?php

namespace App\Chatty\Contracts;

Interface ChattyContract
{
    /** STORE METHODS
     *  These do not do any form validation or download from Winchatty.
     *  They each accept a JSON-formatted string of thread/post/lol data, turn it into
     *  models, and save it to the database. Each returns a count of operations performed.
     */
    public function createOrUpdatePostLOLs($arrPostLOLs,$postId);
    public function createOrUpdatePost($postDetails);
    public function createOrUpdateThread($threadDetails);

    /** IMPORT METHODS
     *  No validation or model storage. These retrieve JSON datasets from Winchatty
     *  and pass them to the appropriate function to be created.
     */
    public function importThreadFromWinchatty($ids, $username);
    public function importEventsFromWinchatty($lastEventId);
    public function downloadPostsFromWinchatty();


    /** DELETE METHODS
     *  We wouldn't normally delete anything but if something goes out-of-sync or we're
     *  still in development/testing it's good to have a way to remove entries from the DB.
     */
    public function deleteThread($id);
    public function deleteAll($threads,$posts,$lols,$events,$logs);

    /** WINCHATTY EVENTS
     *  Process events polled from Winchatty (new posts, nukes, LOLs).
     */
    public function processServerMessageEvent($eventData);
    public function processCategoryChangeEvent($eventData);
    public function processLolCountsUpdateEvent($eventData);
    public function processNewPostEvent($eventData);

    /** MISC FUNCTIONS
     *  Functions exposed for testing, etc.
     */
    public function setSubthreadCategory($postId,$numericCategory);
    public function confirmGzipSSLSetup();

    /** SEARCH FUNCTIONS
     *  Functions for interacting with the Search Engine (currently elastic)
     */
    public function createPostsIndexInElastic();
    public function indexPosts();
    public function submitPostsForSearchIndexing($posts,$username);
    public function popularWordsForAuthor($author);

    /** WORD CLOUD FUNCTIONS
     * Functions for generating and working with word clouds.
     */
    public function createChattyDailyCloud();
    public function queueCreateWordCloudJob($author,$from,$to,$async,$cloudPerm,$tablePerm,$filterId,$colorsetId,$phrases);
    public function generateWordCloudForAuthor($wordcloud_id);
    public function generateWordCloudTextAndColorStrings($wordcloud_id);
    public function getWordCloudWork($wordcloud_id);
    public function std_deviation($arr);
    public function termsAndSentimentForPost($postId);

    /** MONITORING FUNCTIONS
     *  Functions for monitoring the operation of the system
     */
    public function monitorEventPolling($eventMonitorName);

    /** ASYNC OPERATIONS
     * Misc functions that perform cleanup or maintenance on the dataset.
     */
}