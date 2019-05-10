<?php

/** CHATTY CLASS
 *  
 *  Some basic rules:
 *  - createOrUpdate operations should return row_counts
 *  - import/create Winchatty operations should accept row_counts, write to db_actions and pass
 *    db_actions id's back in array for display to users
 *  
 */

namespace App\Chatty;

use App\Chatty\Contracts\ChattyContract;
use App\Chatty\Contracts\SearchContract;

// Asynchronously create word clouds so that the browser doesn't timeout
use App\Jobs\CreateWordCloud;

use Auth;           // Retrieve user credentials to store in dbActions log entry
use DB;             // Required for writing queries with JOIN conditions

use GuzzleHttp\Exception\GuzzleException;       // In case something goes wrong querying WinChatty
use GuzzleHttp\Client;                          // For connecting to WinChatty and pulling JSON data

use Carbon\Carbon;  // Extends PHP date functionality for parsing timezone dates

use Illuminate\Support\Str; // For working with UUIDs

//use jwhennessey\phpinsight;     // For sentiment analysis
//require_once '..\Vendor\jwhennessey\phpinsight\autoload.php';
use PHPInsight;

use App\User;
use App\Chatty\post;
use App\Chatty\post_lol;
use App\Chatty\thread;
use App\Chatty\rowCounts;
use App\Chatty\event;
use App\Chatty\dbAction;
use App\Chatty\postcategory;
use App\Chatty\app_setting;
use App\Chatty\mass_sync_result;
use App\Chatty\indexing_error;
use App\Chatty\word_cloud;
use App\Chatty\word_cloud_work;
use App\Chatty\post_count;
use App\Chatty\monitor;
use App\Chatty\word_cloud_phrase;

use App\Mail\UserRegistered;
use App\Mail\MonitorEventPoll;

use Illuminate\Support\Facades\Mail;


class Chatty implements ChattyContract
{
    private $search;

    /**
     * Create a new Chatty class instance.
     *
     * @return void
     */
    public function __construct(SearchContract $search)
    {
        $this->search = $search;
    }


    /** 
     *  Confirm SSL and GZIP are setup correctly
     * 
     *  @return array of strings with success or failure message
     */
    public function confirmGzipSSLSetup()
    {
        /*
        $guzzle = new GuzzleClient('https://winchatty.com/v2/checkConnection', array(
            'curl.options' => array(
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            )
        ));
        */
        $message = [];

        /*$this->guzzle = new Client(['timeout' => 5, 'base_uri' => $uri, 'curl' => [
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
]]);*/

        $client = new Client();
        $requestURI = 'https://winchatty.com/v2/checkConnection';
        //ob_start();
        $res = $client->request('GET', $requestURI, [
            'decode_content' => 'gzip',
            'verify' => true,
            //'debug' => true,
        ]);
        //$processResult = ob_get_contents();
        //ob_clean();
        //dd($processResult);

        $checkResult = json_decode($res->getBody());

        // If 'result' exists, odds are VERY good the check was successful
        if(property_exists($checkResult,'result'))
        {
            if($checkResult->result == "success")
            {
                $message[] = 'Check successful! GZIP and SSL correctly configured.';
            }
            else
            {
                $message[] = $checkResult->result;
            }
        }
        // If 'result' doesn't exist, collect the error and return it.
        else
        {
            $message[] = $checkResult->code;
            $message[] = $checkResult->message;
        }

        return $message;
    }

    /**
     *  Try logging into Winchatty with a set of test credentials
     * 
     *  @param username string
     *  @param password string
     * 
     *  @return boolean 1=success;0=failure
     */
    public function testWinchattyLogin($username,$password)
    {
        $client = new Client();
        $requestURI = 'https://winchatty.com/v2/verifyCredentials';
        $res = $client->request('POST', $requestURI, [
            'form_params' => [
                'username' => $username,
                'password' => $password
            ]
        ]);

        $testResult = json_decode($res->getBody());

        if($testResult->isValid) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Create or update all LOL's on a single POST in the DB
     *
     * @param  array of JSON key=>value lol tags        $arrPostLOLs
     * @param  Chatty post id in the format 37204343    $request
     * @return App\Chatty\rowCounts
     */
    public function createOrUpdatePostLOLs($arrLOLs,$postId)
    {
        $rowCount = new rowCounts();
        
        foreach($arrLOLs as $lolItem)
        {
            // Try to find both the post_id AND tag, since there could be many different rows for that post ID
            $lol = post_lol::where([
                ['post_id',$postId],
                ['tag',$lolItem->tag],
            ])->first();

            if(!$lol) {
                $lol = new post_lol();
                $rowCount->lolRowCounts['create'] += 1;
            } else {
                $rowCount->lolRowCounts['update'] += 1;
            }

            $lol->post_id = $postId;
            $lol->tag = $lolItem->tag;
            $lol->count = $lolItem->count;

            $lol->save();
        }
        
        return $rowCount;
    }

    /**
     * Create or update a single Chatty post in the DB
     *
     * @param  JSON post object as downloaded from Winchatty
     * @return App\Chatty\rowCounts
     */
    public function createOrUpdatePost($postDetails)
    {
        $rowCount = new rowCounts();
        $counts = new rowCounts();

        // Confirm the root thread exists before attempting to add posts (due to database FK constraints)
        if(!thread::where('id','=',$postDetails->threadId)->exists()) 
        {
            // If the parentId is 0, then this post starts a brand-new thread. We need to create the thread object first
            // to satisfy FK constraints in the DB.
            if($postDetails->parentId == 0)
            {
                // Create an array with the same format as received from winChatty when querying a thread,
                // then pass that array to the createOrUpdateThreads subroutine to have both it and the post
                // created.
                $threadDetails = array('threadId' => $postDetails->id, 'posts' => array($postDetails));
                $threadDetails = json_encode($threadDetails);
                $rowCount = $this->createOrUpdateThread($threadDetails);
            // If ActivelyCreate flag is set, this post isn't a root post, and no root thread is found in our
            // DB, we break out of this subroutine and instead download the entire thread brand-new from Winchatty.
            // This post will be contained in that download.
            } else if (app_setting::activelyCreateThreadsPosts()) {
                
            } else {
                if(app_setting::loggingLevel() >= 2)
                {
                    $db_action = new dbAction();
                    $db_action->username = app_setting::eventPollUsername();
                    $db_action->message = 'Root thread ' . $postDetails->threadId . ' does not exist. Please import it before attempting to load post ' . $postDetails->id . '. Post not imported.';
                    $db_action->save();
                }
            }

        } else {

            $post = post::find($postDetails->id);
            
            if(!$post) {
                $post = new post();
                $rowCount->postRowCounts['create'] = 1;
            } else {
                // There is no 'updating' of posts on Shacknews, so this is purely an internal function
                // in case I need to re-import a post for some reason.
                $rowCount->postRowCounts['update'] = 1;
            }

            /* MIKE 2018-06-27
                Calling strip_tags on body converts <br \/> into nothing. A ton of posters (myself included)
                end a sentence with a period + newline. Stripping the breaks concatenates the last word of
                sentence 1 with the first word of sentence 2: ie (first.Finally)

                Elastic is trying to index those terms and it's messing with the word clouds and with searching
                in general, so before stripping tags, convert all <br \/> tags into a space.

                MIKE 2018-07-10
                htmlspecialchars turns HTML-encoded characters like < into &lt; which is being indexed by
                elastic as "lt". So milleh (who uses a ton of >>>>> characters) is showing tons and tons of 'gt'
                terms when he actually has none. This must be how WinChatty delivers the body (all ready to render)
                so turn them back into normal characters with htmlspecialchars_decode before sending for indexing.
            */
            $strippedBody = htmlspecialchars_decode(strip_tags(str_replace('<br \/>',' <br \/>', str_replace('<br />',' <br />',$postDetails->body))));      // For now strip the HTML tags - ELASTIC
            $strippedAuthor = strip_tags($postDetails->author);                             // will handle the stemming, etc
            $post->id = $postDetails->id;
            $post->thread_id = $postDetails->threadId;
            $post->parent_id = $postDetails->parentId;
            $post->author = $postDetails->author;
            $post->category = postcategory::categoryId($postDetails->category);
            $post->date = Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $postDetails->date, "UTC");
            $post->body = $postDetails->body;
            $post->body_c = $strippedBody;
            $post->author_c = $strippedAuthor;
            // $post->indexed = false;                          // Field has a default value of 'false' so not necessary to set it

            $post->save();

            // The mass sync process might be calling this so if it is, remove this post from the posts_to_download table.
            // If it's not, the delete will fail and that's fine.
            DB::delete('DELETE FROM posts_to_download WHERE id = :id::INT',['id' => $postDetails->id]);

            // When a new post is added to Shacknews, update the bump_date on the root thread
            // so that sorting works properly.
            if($rowCount->postRowCounts['create'] > 0) {
                $rootThread = thread::where('id','=',$postDetails->threadId)->first();
                $rootThread->bump_date = $post->date;
                $rootThread->save();
            }

            // Only try and add some LOLs if there were actually some delivered
            if(count($postDetails->lols) > 0)
            {
                $lolCounts = $this->createOrUpdatePostLOLs($postDetails->lols,$postDetails->id);
                $rowCount->lolRowCounts["create"] = $lolCounts->lolRowCounts["create"];
                $rowCount->lolRowCounts["update"] = $lolCounts->lolRowCounts["update"];
                $rowCount->lolRowCounts["delete"] = $lolCounts->lolRowCounts["delete"];
            }
        }

        return $rowCount;
    }

    /**
     * Create or update a single Chatty thread in the DB
     *
     * @param  JSON thread object as downloaded from Winchatty
     * @return App\Chatty\rowCounts
     */
    public function createOrUpdateThread($threadDetails)
    {
        $rowCount = new rowCounts();
        
        /* Bump date isn't recorded anywhere so we need to calculate it. Thread creation date is
        the date on the post ID matching the thread ID so watch for it to come up. Set both
        way in the past so that it will be guaranteed to update on the first loop. */
        $bumpDate = '1901-01-01T00:00:00Z';
        $threadDate = '1901-01-01T00:00:00Z';

        $thread = new thread();

        $threadDetails = json_decode($threadDetails);

        if(thread::where('id',$threadDetails->threadId)->exists()) {
            $thread = thread::find($threadDetails->threadId);
            $rowCount->threadRowCounts['update'] += 1;
        } else {
            $thread = new thread();
            $rowCount->threadRowCounts['create'] += 1;
        }

        // We have to create the thread in advance otherwise all the posts will fail to insert. We'll
        // update the dates again at the end.
        $thread->id = $threadDetails->threadId;
        $thread->date = $threadDate;
        $thread->bump_date = $bumpDate;
        $thread->save();
        
        foreach($threadDetails->posts as $postDetails)
        {
            // Keep storing each progressively newer post date until we find the newest one
            if($postDetails->date > $bumpDate)
            {
                $bumpDate = $postDetails->date;
                $thread->bump_date = $bumpDate;
                $thread->save();
            }

            // Watch for the root post to come up and store the date
            if($postDetails->id == $threadDetails->threadId)
            {
                $thread->date = $postDetails->date;
                $thread->save();
            }

            $postAndLOLRowCounts = $this->createOrUpdatePost($postDetails);

            /* Quick'n'dirty: I'm sure there's a nicer way to do this (maybe a class function)
                but for now I just need the values incremented each pass. */
            $rowCount->lolRowCounts["create"] += $postAndLOLRowCounts->lolRowCounts["create"];
            $rowCount->lolRowCounts["update"] += $postAndLOLRowCounts->lolRowCounts["update"];
            $rowCount->lolRowCounts["delete"] += $postAndLOLRowCounts->lolRowCounts["delete"];

            $rowCount->postRowCounts["create"] += $postAndLOLRowCounts->postRowCounts["create"];
            $rowCount->postRowCounts["update"] += $postAndLOLRowCounts->postRowCounts["update"];
            $rowCount->postRowCounts["delete"] += $postAndLOLRowCounts->postRowCounts["delete"];
        }
        
        return $rowCount;
    }

    /**
     *  Create or update a single Winchatty event in the database.
     * 
     * @param JSON event object as downloaded from Winchatty
     * @return App\Chatty\rowCounts
     */
    public function createOrUpdateEvent($chattyEvent)
    {
        $rowCount = new rowCounts();

        if(event::where('event_id',$chattyEvent->eventId)->exists()) {
            $event = event::find($chattyEvent->eventId);
            $rowCount->eventRowCounts['update'] = 1;
        } else {
            $event = new event();
            $rowCount->eventRowCounts['create'] = 1;
        }

        // Save the event details and JSON payload to the events table
        $event->event_id = $chattyEvent->eventId;
        $event->event_date = Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $chattyEvent->eventDate, "UTC");
        $event->event_type = $chattyEvent->eventType;
        $event->event_data = json_encode($chattyEvent->eventData);
        $event->save();

        return $rowCount;
    }

    /**
     * Go out to WinChatty and retrieve the JSON for a single thread
     *
     * @param  Comma-delimited string of Post ID's (e.g. "30040356,30040312,30040378")
     * @return App\Chatty\rowCounts
     */
    public function importThreadFromWinchatty($ids, $username)
    {
        $returnArray = [];

        $client = new Client();
        $requestURI = 'https://winchatty.com/v2/getThread?id='.$ids;
        $res = $client->request('GET', $requestURI, [
            'decode_content' => 'gzip',
            'verify' => true
        ]);

        $chattyThreads = json_decode($res->getBody());
        $counts = NULL;
        $rowCount = new rowCounts();

        // Convert the comma-delimited string of IDs to an array
        $idArray = explode(",",$ids);

        if(count($chattyThreads->threads) > 0)
        {
            foreach($chattyThreads->threads as $chattyThread) 
            {
                // The createOrUpdateThread function expects JSON-encoded thread details.
                // Returns a rowCounts object with all the threads/post/lols numbers in it.
                $counts = $this->createOrUpdateThread(json_encode($chattyThread));

                $rowCount->postRowCounts["create"] += $counts->postRowCounts["create"];
                $rowCount->postRowCounts["update"] += $counts->postRowCounts["update"];
                $rowCount->threadRowCounts["create"] += $counts->threadRowCounts["create"];
                $rowCount->threadRowCounts["update"] += $counts->threadRowCounts["update"];
                $rowCount->lolRowCounts["create"] += $counts->lolRowCounts["create"];
                $rowCount->lolRowCounts["update"] += $counts->lolRowCounts["update"];
            }

            $message = 'Thread Import processed for ' . count($idArray) . ' post IDs. ' . $rowCount->threadRowCounts["create"] . 
                        ' threads created and ' . $rowCount->threadRowCounts["update"] . ' threads updated. ' . 
                        $rowCount->postRowCounts["create"] . ' posts created and ' . $rowCount->postRowCounts["update"] . 
                        ' posts updated. ' . $rowCount->lolRowCounts["create"] . 
                        ' LOLs created and ' . $rowCount->lolRowCounts["update"] . ' LOLs updated';
            
            // Fewer threads imported than requested - find the outlier(s) and log it
            if(count($chattyThreads->threads) != count($idArray))
            {
                $missingPosts = [];
                foreach($idArray as $id)
                {
                    if(!post::where('id','=',$id)->exists())
                    {
                        $missingPosts[] = $id;
                    }
                }

                foreach($missingPosts as $missingPost)
                {
                    $massSyncRes = new mass_sync_result();
                    $massSyncRes->post_id = $missingPost;
                    $massSyncRes->last_sync_attempt = Carbon::now();
                    $massSyncRes->save();
                }

                $missingMessage = 'Threads not found in WinChatty for the following Post IDs: ' . implode(", ", $missingPosts);
                if(app_setting::loggingLevel() >= 3)
                {
                    $db_action = new dbAction();
                    $db_action->username = $username;
                    $db_action->message = $missingMessage;
                    $db_action->save();
                }
            }
        }
        else
        {
            foreach($idArray as $idNotFound)
            {
                $massSyncRes = new mass_sync_result();
                $massSyncRes->post_id = $idNotFound;
                $massSyncRes->last_sync_attempt = Carbon::now();
                $massSyncRes->save();
            }
            $message = 'No threads found in Winchatty for the supplied Post IDs: ' . implode(", ", explode(",",$ids));
        }

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            $db_action->username = $username;
            $db_action->message = $message;
            $db_action->save();
        }

        // It's expected that this subroutine return an array of message strings
        $returnArray[] = $message;
        if(!empty($missingMessage))
        {
            $returnArray[] = $missingMessage;
        }

        return $returnArray;

    }

    /**
     *  Retrieve all the events from Winchatty that have occurred since the last time we polled.
     *  This will ALWAYS return lastEventId (though it may be the same as the one we just sent).
     *  It will also always return an array of events, though the array may occasionally be empty.
     */
    public function importEventsFromWinchatty($lastEventId)
    {
        $returnArr = [];
        $dbActionMessages = [];
        $rowCounts = new rowCounts();
        $client = new Client();

        $requestURI = 'https://winchatty.com/v2/pollForEvent?lastEventId='.$lastEventId;
        $res = $client->request('GET', $requestURI, [
            'decode_content' => 'gzip',
            'verify' => true
        ]);

        $eventsResponse = json_decode($res->getBody());

        // This call returns a lastEventId value that we should store and use the next time we
        // call this subroutine.
        $appSetting = new app_setting();
        $appSetting = $appSetting->getlatestAppSettings();
        $appSetting->last_event_id = $eventsResponse->lastEventId;
        $appSetting->save();

        // There may or may NOT be events in the this array - if there are, process them.
        foreach($eventsResponse->events as $chattyEvent)
        {
            // Store the event data in 'events' table in DB for safekeeping and reference 
            $eventCounts = $this->createOrUpdateEvent($chattyEvent);
            $rowCounts->eventRowCounts["create"] += $eventCounts->eventRowCounts["create"];
            $rowCounts->eventRowCounts["update"] += $eventCounts->eventRowCounts["update"];

            // Process the event payload and store the return dbAction message for display later
            $returnArr = $this->processWinchattyEvent($chattyEvent);
            array_merge($dbActionMessages,$returnArr);
        }

        $message = 'Successfully imported events from ' . $lastEventId . ' to ' .
            event::newestEventId() . '. -- Events created: ' . $rowCounts->eventRowCounts["create"] . '; updated: ' .
            $rowCounts->eventRowCounts["update"] . '.';

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            $db_action->username = app_setting::nameToLog();
            $db_action->message = $message;
            $db_action->save();
        }

        array_push($dbActionMessages,$message);

        return $dbActionMessages;
    }

    /**
     *  IMPORT MISSING POSTS FROM WINCHATTY
     * 
     *  Should something go sideways in the Event Poll process (which has happened),
     *  this provides a way to sync up the missing posts in the database.
     * 
     *  @param starting Post ID for missing post block
     *  @param ending Post ID for missing post block
     *  
     *  @return array of strings
     */
    public function importMissingPostsInRange($startPostId,$endPostId)
    {
        $returnArrStrings = [];
        $threadsImported = NULL;
        $returnArr = [];
        $dbActionMessages = [];
        $rowCounts = new rowCounts();
        $client = new Client();

        $missingPostIds = DB::select('SELECT id FROM posts_to_download 
                              WHERE id BETWEEN :startid AND :endid 
                              AND id NOT IN (SELECT post_id FROM mass_sync_results WHERE post_id BETWEEN :startid AND :endid) 
                              AND id NOT IN (SELECT id FROM posts WHERE id BETWEEN :startid AND :endid)',
                              ['startid' => $startPostId, 'endid' => $endPostId]);
        
        // This could potentially be a VERY large array of missing posts
        foreach($missingPostIds as $missingPostId)
        {
            // But each loop will download an entire thread, so ensure the previous loop
            // hasn't already downloaded the post we're looking for
            if(!post::where('id',$missingPostId->id)->exists())
            {
                // This function writes all actions to the log for us and just returns an array of strings
                $returnRes = $this->importThreadFromWinchatty($missingPostId->id,auth::user()->name);

                // Merge each array of strings with the previous ones and return them all at once.
                $returnArr = array_merge($returnArr,$returnRes);
            }
        }

        // Return the array of strings for display
        return $returnArr;
    }
    
    /**
     * Delete a single thread and all its' child posts from the DB.
     *
     * @param  Chatty thread id
     * @return App\Chatty\rowCounts
     */
    public function deleteThread($id)
    {
        // Ensure there's actually a thread to delete first - if not found, send user back
        // to requesting page and display error message.
        if(!thread::where('id',$id)->exists()) {
            return back()->with('error','Thread '. $id . ' not found.');
        } else {

            $action = 'delete';
            $rowCount = new rowCounts();

            $posts = post::where('thread_id',$id)->get();

            // Once we have all the posts, we can remove the post_lols
            foreach($posts as $post)
            {
                /* DELETE ALL POST_LOLs that have a post_id within our thread. */
                $rowCount->lolRowCounts["delete"] += post_lol::where('post_id',$post->id)->delete();
            }

            // Might as well mass-delete the posts
            $rowCount->postRowCounts["delete"] += $post::where('thread_id',$id)->delete();

            // Finally, delete the thread
            $rowCount->threadRowCounts["delete"] += thread::where('id',$id)->delete();

            // The controller will handle writing to the dbAction log so just pass back the counts
            return $rowCount;
        }
    }

    /**
     *  Delete All
     * 
     *  @param boolean deleteThreads
     *  @param boolean deletePosts
     *  @param boolean deleteLOLs
     *  @param boolean deleteEvents
     *  @param boolean deleteLogs
     * 
     *  @return array of string messages with results
     */
    public function deleteAll($threads,$posts,$lols,$events,$logs)
    {
        $message = NULL;
        $messageArr = [];

        if(!$threads && !$posts && !$lols && !$events && !$logs)
        {
            $message = 'At least one checkbox must be selected before clicking Delete All';
            array_push($messageArr,$message);
        }
        else
        {
            // If threads: delete all posts & lols too
            // If posts: delete all lols too
            // If logs: don't log the actions to db_action log
            if($threads) {
                $posts = TRUE;
            }
            if($posts) {
                $lols = TRUE;
                $threads = TRUE;
            }

            if($lols)
            {
                $initialCount = post_lol::all()->count();
                post_lol::getQuery()->delete();
                $deletedCount = post_lol::all()->count();

                if($deletedCount == 0) {
                    $message = 'Successfully mass-deleted ' . $initialCount . ' LOLs.';
                } else {
                    $message = 'Unsucessfully tried to mass-delete ' . $initialCount . ' LOLs. Deleted ' . $initialCount-$deletedCount . ' LOLs.';
                }
                
                array_push($messageArr,$message);
            }

            if($posts)
            {
                $initialCount = post::all()->count();
                post::getQuery()->delete();
                $deletedCount = post::all()->count();

                if($deletedCount == 0) {
                    $message = 'Successfully mass-deleted ' . $initialCount . ' posts.';
                } else {
                    $message = 'Unsucessfully tried to mass-delete ' . $initialCount . ' posts. Deleted ' . $initialCount-$deletedCount . ' posts.';
                }

                array_push($messageArr,$message);
            }

            if($threads)
            {
                $initialCount = thread::all()->count();
                thread::getQuery()->delete();
                $deletedCount = thread::all()->count();

                if($deletedCount == 0) {
                    $message = 'Successfully mass-deleted ' . $initialCount . ' threads.';
                } else {
                    $message = 'Unsucessfully tried to mass-delete ' . $initialCount . ' threads. Deleted ' . $initialCount-$deletedCount . ' threads.';
                }

                array_push($messageArr,$message);
            }

            if($events)
            {
                $initialCount = event::all()->count();
                event::getQuery()->delete();
                $deletedCount = event::all()->count();

                if($deletedCount == 0) {
                    $message = 'Successfully mass-deleted ' . $initialCount . ' events.';
                } else {
                    $message = 'Unsucessfully tried to mass-delete ' . $initialCount . ' events. Deleted ' . $initialCount-$deletedCount . ' events.';
                }

                array_push($messageArr,$message);
            }

            if($logs)
            {
                $initialCount = dbAction::all()->count();
                dbAction::getQuery()->delete();
                $deletedCount = dbAction::all()->count();

                if($deletedCount == 0) {
                    $message = 'Successfully mass-deleted ' . $initialCount . ' log entries.';
                } else {
                    $message = 'Unsucessfully tried to mass-delete ' . $initialCount . ' log entries. Deleted ' . $initialCount-$deletedCount . ' log entries.';
                }

                array_push($messageArr,$message);
            }
            else
            {
                if(app_setting::loggingLevel() >= 2)
                {
                    foreach($messageArr as $message)
                    {
                        $db_action = new dbAction();
                        $db_action->username = app_setting::nameToLog();
                        $db_action->message = $message;
                        $db_action->save();
                    }
                }
            }
        }

        return $messageArr;

    }

    /**
     *  Interative function that sets the category for a single post.
     * 
     *  @param App\Chatty\post
     *  @param numeric category from 0-7 (7 is nuked)
     *  @return unsignedInteger of records changed
     */
    public function setPostCategory($subthreadRootPost,$numericCategory)
    {
        // Declare our counter as static so that it will retain it's value on each loop
        static $countChanged = 0;

        $subthreadRootPost->category = $numericCategory;
        $subthreadRootPost->save();
        
        $countChanged += 1;

        // This is how we know to stop iterating, otherwise it'd loop forever
        if(count($subthreadRootPost->children()->get()) > 0)
        {
            foreach($subthreadRootPost->children()->get() as $childPost)
            {
                $this->setPostCategory($childPost,$numericCategory);
            }
        }

        return $countChanged;
    }

    /**
     *  Control function that handles looping through subthreads and mass-setting a category (nukes, mostly).
     * 
     *  @param unsignedInteger postId
     *  @param numeric category from 0-7 (7 is nuked)
     *  @return unsignedInteger of records changed
     */
    public function setSubthreadCategory($postId,$numericCategory)
    {
        $post = post::find($postId);
        $count = 0;

        if($post) 
        {
            // This will self-iterate until all children are processed, then it will return here
            $count = $this->setPostCategory($post,$numericCategory);
        }

        return $count;
    }

    /**
     *  Categorize and process a single WinChatty event. Event data has already been written to DB
     *  by createOrUpdateEvents().
     * 
     *  @param Chatty event JSON
     *  @return unsigned integer dbAction Id
     */
    public function processWinchattyEvent($chattyEvent)
    {
        $dbActionArr = [];

        switch($chattyEvent->eventType) {
            case "newPost":
                $dbActionArr = $this->processNewPostEvent($chattyEvent);
                break;
            case "categoryChange":
                $dbActionArr = $this->processCategoryChangeEvent($chattyEvent);
                break;
            case "serverMessage":
                $dbActionArr = $this->processServerMessageEvent($chattyEvent);
                break;
            case "lolCountsUpdate":
                $dbActionArr = $this->processLolCountsUpdateEvent($chattyEvent);
                break;
        }

        return $dbActionArr;
    }

    /** EVENT: SERVER MESSAGE
     * 
     *  Rarely (if ever) used, this is a central broadcast message that electroly could
     *  send from the Chatty to all clients that implement it.
     */
    public function processServerMessageEvent($eventToProcess)
    {
        $eventData = $eventToProcess->eventData;

        $message = 'Received API broadcast message. ' . $eventData->message;
        if(app_setting::loggingLevel() >= 1)
        {
            $db_action = new dbAction();
            $db_action->username = app_setting::nameToLog();
            $db_action->message = $message;
            $db_action->save();
        }

        /*  Event validation - confirm the payload of the event exists in the DB. How it got there
            (by being processed or re-downloaded from Winchatty) is of less importance. */
        $event = event::find($eventToProcess->eventId);
        $event->processed = TRUE;
        $event->save();

        return array($message);
    }

    /** EVENT: CATEGORY CHANGE
     * 
     *  Fired when a post is marked INFORMATIVE/STUPID/NSFW or when a post and it's subthread
     *  are nuked.
     */
    public function processCategoryChangeEvent($eventToProcess)
    {
        $post = null;
        $message = NULL;
        $downloadThread = FALSE;
        $validationPassed = FALSE;
        $messageArr = [];

        $eventData = $eventToProcess->eventData;

        // Post to be recategorized exists - proceed with further validation that post isn't orphaned
        if(post::where('id',$eventData->postId)->exists())
        {
            $post = post::where('id',$eventData->postId)->first();

            // Root thread exists
            if(thread::where('id','=',$post->thread_id)->exists())
            {
                // Parent post missing
                if(!post::where('id','=',$post->parentId)->exists() && $post->parentId != 0)
                {
                    if(app_setting::activelyCreateThreadsPosts()) {
                        $message = 'Category Change event received for post ' . $post->id . '. Parent post ' . $post->parentId . ' not found in DB. Importing thread from WinChatty.';
                        $downloadThread = TRUE;
                    } else {
                        $message = 'Category Change event received for post ' . $post->id . '. Parent post ' . $post->parentId . ' not found in DB. Event not processed.';
                    }
                }
                // VALIDATION PASSED - we're good to process the category change
                else
                {
                    $validationPassed = TRUE;
                }
            }
            // Root thread missing
            else 
            {
                if(app_setting::activelyCreateThreadsPosts()) {
                    $message = 'Category Change event received for post ' . $post->id . '. Root thread ' . $post->thread_id . ' not found in DB. Importing thread from WinChatty.';
                    $downloadThread = TRUE;
                } else {
                    $message = 'Category Change event received for post ' . $post->id . '. Root thread ' . $post->thread_id . ' not found in DB. Event not processed.';
                }
            }
        }
        // Post to be recategorized doesn't exist
        else 
        {
            if(app_setting::activelyCreateThreadsPosts()) {
                // WinChatty won't deliver a thread if you pass a nuked post ID, so don't try
                if($eventData->category != 'nuked') {
                    $message = 'Category Change event received for post ' . $eventData->postId . ' which was not found in DB. Importing thread from WinChatty.';
                    $downloadThread = TRUE;
                } else {
                    $message = 'Category Change event (nuked) received for post ' . $eventData->postId . ' which was not found in DB. Cannot import thread.';
                }
                
            } else {
                $message = 'Category Change event received for post ' . $eventData->postId . ' which was not found in DB. No action taken.';
            }
        }

        // Validation failed - either download the entire thread or exit with an error
        if(!$validationPassed)
        {
            // Write the above validation message to the log before the subroutines add their entries
            if(app_setting::loggingLevel() >= 2)
            {
                $db_action = new dbAction();
                $db_action->username = app_setting::nameToLog();
                $db_action->message = $message;
                $db_action->save();
            }

            if($downloadThread)
            {
                // Slip the error message in front of all other messages that might be returned
                // while importing the thread
                $retArr = $this->importThreadFromWinchatty($eventData->postId,auth::user()->name);
                $messageArr = array_merge(array($message), $retArr);
            }
            else
            {
                array_push($messageArr,$message);
            }
        }
        else
        {
            // If this event issues a nuke, set the whole subthread as nuked
            if($eventData->category == "nuked") {
                $nukeCount = $this->setSubthreadCategory($post->id, postcategory::categoryId('nuked'));
                if($nukeCount > 0) {
                    $message = 'Successfully nuked ' . $nukeCount . ' posts under post ID: ' . $eventData->postId . '.';
                } else {
                    $message = 'Post ' . $eventData->postId . ' not found. Nuke event not processed.';
                }
            // If this post undoes a nuke, set the whole subthread back
            } else if ( $post->category == postcategory::categoryId('nuked') ) {
                $unnukeCount = $this->setSubthreadCategory($post->id,postcategory::categoryId($eventData->category));
                if($unnukeCount > 0) {
                    $message = 'Successfully un-nuked ' . $unnukeCount . ' posts under post ID: ' . $eventData->postId . '.';
                } else {
                    $message = 'Post ' . $eventData->postId . ' not found. Un-nuke event not processed.';
                }
            // Otherwise the post was marked NSFW/INFORMATIVE/etc so just change it
            } else {
                $resCount = $this->setSubthreadCategory($post->id, postcategory::categoryId($eventData->category));
                if($resCount > 0) {
                    $message = 'Category change event received for post ' . $post->id . '. Changed category to ' . $eventData->category . '.';
                } else {
                    $message = 'Category change event received for post ' . $post->id . '. Attempt to change category to ' . $eventData->category . ' failed. Event not processed.';
                }
                
            }

            // Don't lose the success message
            array_push($messageArr,$message);

            // Write the above validation message to the log before the subroutines add their entries
            if(app_setting::loggingLevel() >= 2)
            {
                $db_action = new dbAction();
                $db_action->username = app_setting::nameToLog();
                $db_action->message = $message;
                $db_action->save();
            }
        }

        /*  Event validation - confirm the payload of the event exists in the DB. How it got there
        (by being processed or re-downloaded from Winchatty) is of less importance. */

        /*  Nukes get handled specially because if we don't have the post for some reason and only the postId
            is supplied by the Winchatty event, we cannot look up the thread info locally or remotely, so don't try. */
        $setProcessed = FALSE;
        if($eventData->category == 'nuked' && !post::where('id',$eventData->postId)->exists())
        {
            $setProcessed = TRUE;
            if(app_setting::loggingLevel() >= 2)
            {
                $db_action = new dbAction();
                $db_action->username = app_setting::nameToLog();
                $db_action->message = 'Nuke event received and post not found in DB. Nuke event not processed. Event marked processed.';
                $db_action->save();
            }
        }
        else
        {
            $postToValidate = post::find($eventData->postId);
            if($postToValidate->category == postcategory::categoryId($eventData->category)) {
                $setProcessed = TRUE;

            }
        }
        $event = event::find($eventToProcess->eventId);
        $event->processed = $setProcessed;
        $event->save();
        
        return $messageArr;
    }

    /** EVENT: LOL COUNT UPDATE
     *  
     *  Fired when someone tags a post with a loltag (delivered or custom).
     * 
     *  @return array of db_action string messages
     */
    public function processLolCountsUpdateEvent($eventToProcess)
    {
        $rowCount = new rowCounts();
        $post = null;
        $message = NULL;
        $messageArr = [];
        $downloadThread = FALSE;
        $validationPassed = FALSE;

        $eventData = $eventToProcess->eventData;

        // We might receive 5 LOL's in the array, process 3 and skip 2 for reasons
        $db_action = null;

        // Updates are delivered in an array so check each one
        foreach($eventData->updates as $lolUpdate) 
        {
            // Confirm post to be lol'd exists, then proceed with further validation that post isn't orphaned
            if(post::where('id',$lolUpdate->postId)->exists())
            {
                $post = post::where('id',$lolUpdate->postId)->first();

                // Root thread exists
                if(thread::where('id','=',$post->thread_id)->exists())
                {
                    // Parent post missing
                    if(!post::where('id','=',$post->parentId)->exists() && $post->parentId != 0)
                    {
                        if(app_setting::activelyCreateThreadsPosts()) {
                            $message = 'LOL Count Update event received for post ' . $post->id . '. Parent post ' . $post->parentId . ' not found in DB. Importing thread from WinChatty.';
                            $downloadThread = TRUE;
                        } else {
                            $message = 'LOL Count Update event received for post ' . $post->id . '. Parent post ' . $post->parentId . ' not found in DB. Event not processed.';
                        }
                    }
                    // VALIDATION PASSED - we're good to process the LOL event
                    else
                    {
                        $validationPassed = TRUE;
                    }
                }
                // Root thread missing
                else 
                {
                    if(app_setting::activelyCreateThreadsPosts()) {
                        $message = 'LOL Count Update event received for post ' . $post->id . '. Root thread ' . $post->thread_id . ' not found in DB. Importing thread from WinChatty.';
                        $downloadThread = TRUE;
                    } else {
                        $message = 'LOL Count Update event received for post ' . $post->id . '. Root thread ' . $post->thread_id . ' not found in DB. Event not processed.';
                    }
                }
            }
            // Post to be LOL'd doesn't exist
            else 
            {
                if(app_setting::activelyCreateThreadsPosts()) {
                    $message = 'LOL Count Update event received for post ' . $lolUpdate->postId . ' which was not found in DB. Importing thread from WinChatty.';
                    $downloadThread = TRUE;
                } else {
                    $message = 'LOL Count Update event received for post ' . $lolUpdate->postId . ' which was not found in DB. No action taken.';
                }
            }

            // Validation failed - either download the entire thread or exit with an error
            if(!$validationPassed)
            {
                // Write the above validation message to the log before the subroutines add their entries
                if(app_setting::loggingLevel() >= 2)
                {
                    $db_action = new dbAction();
                    $db_action->username = app_setting::nameToLog();
                    $db_action->message = $message;
                    $db_action->save();
                }

                if($downloadThread)
                {
                    // Slip the error message in front of all other messages that might be returned
                    // while importing the thread
                    $messageArr = array_merge(array($message), $this->importThreadFromWinchatty($lolUpdate->postId),auth::user()->name);
                }
                else
                {
                    array_push($messageArr,$message);
                }
            }
            // Validation passed - update the LOL Counts
            else
            {
                $lolArray = array('tag' => $lolUpdate->tag, 'count' => $lolUpdate->count);                
                $arrLOL = array('lols' => $lolArray);
                $lolArray = json_encode($arrLOL);
                $counts = $this->createOrUpdatePostLOLs(json_decode($lolArray),$lolUpdate->postId);

                $rowCount->lolRowCounts["create"] += $counts->lolRowCounts["create"];
                $rowCount->lolRowCounts["update"] += $counts->lolRowCounts["update"];
                $rowCount->lolRowCounts["delete"] += $counts->lolRowCounts["delete"];
            }
        }

        $message = 'LOL Count Update Event processed. ' . $rowCount->lolRowCounts["create"] .
        ' LOLs created and ' . $rowCount->lolRowCounts["update"] . ' LOLs updated.';

        array_push($messageArr,$message);

        if(app_setting::loggingLevel() >= 4)
        {
            $db_action = new dbAction();
            $db_action->username = app_setting::nameToLog();
            $db_action->message = $message;
            $db_action->save();
        }

        /*  Event validation - confirm the payload of the event exists in the DB. How it got there
        (by being processed or re-downloaded from Winchatty) is of less importance. */
        $validated = TRUE;
        foreach($eventData->updates as $lolUpdate)
        {
            $lolToValidate = post_lol::where([
                ['post_id',$lolUpdate->postId],
                ['tag',$lolUpdate->tag],
            ])->first();

            if($lolToValidate)
            {
                if($lolToValidate->count != $lolUpdate->count)
                {
                    $validated = FALSE;
                }
            }
            else
            {
                $validated = FALSE;
            }
        }
        if($validated)
        {
            $event = event::find($eventToProcess->eventId);
            $event->processed = TRUE;
            $event->save();
        }

        return $messageArr;
    }


    /** EVENT: NEW POST
     *  
     *  Fired when someone posts a new thread or a reply to an existing one.
     *  Writes to the dbAction log if root thread doesn't exist 
     * 
     *  @param  postId + App\Chatty\post object
     *  @return array of db_action string messages
     * 
     */
    public function processNewPostEvent($eventToProcess)
    {
        $messageArr = [];
        $rowCount = new rowCounts();
        $counts = NULL;
        $message = NULL;
        $downloadThread = FALSE;
        
        $eventData = $eventToProcess->eventData;

        // If the parentId is 0, this is a new root post and no further validation is needed aside from creating the thread/post
        if($eventData->post->parentId != 0)
        {
            // Ensure the root thread exists before trying to add a post beneath it
            if(thread::where('id','=',$eventData->post->threadId)->exists())
            {
                // Ensure the parent post exists before trying to add a child
                if(!post::where('id','=',$eventData->post->parentId)->exists())
                {
                    if(app_setting::activelyCreateThreadsPosts()) {
                        $message = 'New Post event received for post ' . $eventData->post->id . '. Parent post ' . $eventData->post->parentId . ' not found in DB. Importing thread from WinChatty.';
                        $downloadThread = TRUE;
                    } else {
                        $message = 'New Post event received for post ' . $eventData->post->id . '. Parent post ' . $eventData->post->parentId . ' not found in DB. Event not processed.';
                    }
                }
            } 
            else 
            {
                if(app_setting::activelyCreateThreadsPosts()) {
                    $message = 'New Post event received for post ' . $eventData->post->id . '. Root thread ' . $eventData->post->threadId . ' not found in DB. Importing thread from WinChatty.';
                    $downloadThread = TRUE;
                } else {
                    $message = 'New Post event received for post ' . $eventData->post->id . '. Root thread ' . $eventData->post->threadId . ' not found in DB. Event not processed.';
                }
            }
        }

        if(app_setting::loggingLevel() >= 2 && $message)
        {
            $db_action = new dBAction();
            $db_action->username = app_setting::nameToLog();
            $db_action->message = $message;
            $db_action->save();
        }

        // Some of the validation failed
        if($downloadThread)
        {
            // If activelyCreate flag is set, redownload the entire thread to ensure consistency
            if(app_setting::activelyCreateThreadsPosts())
            {
                $messageArr = $this->importThreadFromWinchatty($eventData->post->id,auth::user()->name);
            }            
        }
        // Basic validation passed and we're adding a single post (which may be the root post in a new thread)
        else 
        {
            $counts = $this->createOrUpdatePost($eventData->post);

            $rowCount->postRowCounts["create"] += $counts->postRowCounts["create"];
            $rowCount->postRowCounts["update"] += $counts->postRowCounts["update"];
            $rowCount->threadRowCounts["create"] += $counts->threadRowCounts["create"];
            $rowCount->threadRowCounts["update"] += $counts->threadRowCounts["update"];
            $rowCount->lolRowCounts["create"] += $counts->lolRowCounts["create"];
            $rowCount->lolRowCounts["update"] += $counts->lolRowCounts["update"];

            $message = 'New Post Event processed. ' . $rowCount->threadRowCounts["create"] . ' threads created and ' .
            $rowCount->threadRowCounts["update"] . ' threads updated. ' . $rowCount->postRowCounts["create"] .
            ' posts created and ' . $rowCount->postRowCounts["update"] . ' posts updated. ' . $rowCount->lolRowCounts["create"] .
            ' LOLs created and ' . $rowCount->lolRowCounts["update"] . ' LOLs updated';

            if(app_setting::loggingLevel() >= 4)
            {
                $db_action = new dBAction();
                $db_action->username = app_setting::nameToLog();
                $db_action->message = $message;
                $db_action->save();
            }
        }

        array_push($messageArr,$message);

        /*  Event validation - confirm the payload of the event exists in the DB. How it got there
        (by being processed or re-downloaded from Winchatty) is of less importance. */
        if(post::where('id','=',$eventData->post->id)->exists())
        {
            $event = event::find($eventToProcess->eventId);
            $event->processed = TRUE;
            $event->save();
        }

        return $messageArr;
        
    }

    /**
     * Create the Chatty Post index in Elastic. Enable a bunch of extended features, such as stemming
     * and stopwords to make Search more useful.
     */
    public function createPostsIndexInElastic()
    {
        $messageArr = [];
        $result = $this->search->createChattyPostIndex();

        $message = 'Successfully created ' . app_setting::getPostSearchIndex() . ' index in Elastic.';

        $db_action = new dbAction();
        $db_action->username = Auth::user()->name;
        $db_action->message = $message;
        $db_action->save();

        $messageArr[] = $message;

        return $messageArr;
    }


    /**
     * Select a batch of posts that is not yet indexed and send them to Elastic. This function is intended
     * to be called by the search:crawl command (which will be scheduled).
     */
    public function indexPosts()
    {
        if(app_setting::searchCrawlerEnabled())
        {
            // Retrieve Illuminate\Support\Collection of posts
            $postsToIndex = post::where([['indexed','false'],['category','<>',postcategory::categoryId('nuked')]])
            ->orderBy('date','desc')
            ->limit(app_setting::postsToIndex())->get();

            // Pass collection to Chatty class, which will pass it to ElasticSearch class. Do this
            // so that the Chatty class can do all the dbAction logging, etc.
            $this->submitPostsForSearchIndexing($postsToIndex,app_setting::searchCrawlerUsername());

            // Update the search_crawler_last_run date
            $as = app_setting::find(1);
            $as->search_crawler_last_run = Carbon::now();
            $as->save();
        }
    }

    /**
     *  Submit an array of post ID's for indexing by the search engine.
     * 
     *  https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-bulk.html
     * 
     *  @param Illuminate\Support\Collection of \App\Chatty\post
     */
    public function submitPostsForSearchIndexing($posts,$username)
    {
        $messageArr = [];

        // Elastic will return a large array of results for each index request
        $responses = [];

        // Obviously only try to index if something was passed
        if(count($posts) > 0)
        {
            $responses = $this->search->indexPosts($posts);
        }

        // Responses now contains a huge array in the format:
        /*
            array:3 [▼
                0 => array:3 [▼
                    "took" => 252
                    "errors" => false
                    "items" => array:1 [▼
                        0 => array:1 [▼
                            "index" => array:9 [▼
                                "_index" => "shacknews_chatty_posts"
                                "_type" => "post"
                                "_id" => "34499963"
                                "_version" => 1
                                "result" => "created"
                                "_shards" => array:3 [▶]
                                "_seq_no" => 0
                                "_primary_term" => 1
                                "status" => 201
                            ]
                        ]
                    ]
                ]
                1 => array:3 [▼
                    "took" => 18
                    "errors" => false
                    "items" => array:5 [▼
                        0 => array:1 [▼
                            "index" => array:9 [▼
                                "_index" => "shacknews_chatty_posts"
                                "_type" => "post"
                                "_id" => "34499962"
                                "_version" => 1
                                "result" => "created"
                                "_shards" => array:3 [▶]
                                    "_seq_no" => 0
                                    "_primary_term" => 1
                                    "status" => 201
                                ]
                            ]
                        1 => array:1 [▼
                            "index" => array:9 [▼
                            "_index" => "shacknews_chatty_posts"
                            "_type" => "post"
                            "_id" => "34499961"
                            "_version" => 1
                            "result" => "created"
                            "_shards" => array:3 [▶]
                            "_seq_no" => 1
                            "_primary_term" => 1
                            "status" => 201
                            ]
                        ]
                        2 => array:1 [▼
                            "index" => array:9 [▼
                            "_index" => "shacknews_chatty_posts"
                            "_type" => "post"
                            "_id" => "34499960"
                            "_version" => 1
                            "result" => "created"
                            "_shards" => array:3 [▶]
                            "_seq_no" => 0
                            "_primary_term" => 1
                            "status" => 201
                            ]
                        ]
                        3 => array:1 [▶]
                        4 => array:1 [▶]
                    ]
                ]
                2 => array:3 [▶]
            ]
        */

        /*
            We *should* evaluate whether:
            - any of the arrays contains "errors" => true
            - the count(items[]) for each array sums to # posts indexed

            We *can* evaluate whether: 
            - for each post, a status of "created" or "updated" was recorded
        */

        //
        $totalCount = 0;
        $errors = FALSE;
        $successfullyIndexedPosts = [];
        $createdCount = 0;
        $updatedCount = 0;
        $errorCount = 0;

        // Assuming 1000 posts to be indexed with a bundle size of 100, this should loop:
        //  (1000/100)+1 = 11 times
        foreach($responses as $postBundle)
        {
            // Elastic reported errors with the bundle of posts. Log it for reporting
            // and move on to analyzing the results
            if($postBundle['errors'] == 'true')
            {
                $errors = TRUE;
            }

            // Assuming 1000 posts to index with bundle size of 100, this should loop 100x
            foreach($postBundle['items'] as $postsInBundle)
            {
                // This *should* only ever loop once, so we *could* write $postsInBundle[0]
                // but on the off-chance Elastic ever delivers > 1 array element I'm looping again.
                foreach($postsInBundle as $bundlePost)
                {
                    // Error indexing post
                    if(!in_array($bundlePost['result'],['created','updated']))
                    {
                        // Counter for the summary message
                        $errorCount += 1;

                        // We have a table/model specifically for indexing errors. Makes it easier to review
                        // when we're running huge batch index jobs.
                        $indexError = new indexing_error();
                        $indexError->post_id = $bundlePost['_id'];
                        $indexError->status = $bundlePost['status'];
                        if(isset($bundlePost['error'])){
                            $indexError->status = $bundlePost['error'];
                        }
                        $indexError->save();
                                                
                        // This message will be written to the log (in case this is being run scheduled by the automated indexer)
                        // and returned (in case this process is being run interactively by a user at a keyboard).
                        $message = 'Error returned from Elastic while indexing post ' . $bundlePost['_id'] . '. Status code: ' . $bundlePost['status'] . '.';
                        if(isset($bundlePost['error'])){
                            $message .= ' Error message: ' . $bundlePost['error'] . '.';
                        }
                        $message .= ' 1 record written to indexing_errors table.';
                        $messageArr[] = $message;

                        if(app_setting::loggingLevel() >= 2)
                        {
                            $db_action = new dbAction();
                            $db_action->username = $username;
                            $db_action->message = $message;
                            $db_action->save();
                        }
                    }
                    // Success (created or updated) indexing post
                    else
                    {
                        if($bundlePost['result'] == 'created')
                        {
                            // Counter for the summary message
                            $createdCount += 1;
                        }
                        else if($bundlePost['result'] == 'updated')
                        {
                            // Counter for the summary message
                            $updatedCount += 1;
                        }

                        $successfullyIndexedPosts[] = $bundlePost['_id'];
                    }
                }
            }

            // We have an array of Post ID's that were successfully indexed (created or updated)
            // so flip the 'indexed' flag for each of them
            $postsToUpdateIndexFlag = DB::table('posts')
                ->whereIn('id',$successfullyIndexedPosts)
                ->update(['indexed' => true]);
            
        }

        // Summary message for logging and display
        $message = 'Submitted ' . count($posts) . ' posts for indexing in Elastic. Successfully created ' . 
            $createdCount . ' indices. Updated ' . $updatedCount . ' indices. Errors reported: ' . $errorCount .'.';
        $messageArr[] = $message;

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            $db_action->username = $username;
            $db_action->message = $message;
            $db_action->save();
        }

        return $messageArr;
    }

    /**
     * Get the most common words used by a post author.
     */
    public function popularWordsForAuthor($author)
    {
        $postCount = 0;
        $messageArr = [];

        $terms = $this->search->countTermsForAuthor($author);

        /*

        // Make sure 'docs' exists - if not, something went wrong
        if(!array_key_exists('docs',$terms)) {
            $messageArr["error"][] = 'Elastic response missing docs node. Please try again.';
        } else {
            // Docs was found, so start iterating the results. Each is self-contained.
            foreach($terms["docs"] as $term)
            {
                // Make sure result doesn't contain an error - report it if so
                if(array_key_exists('error',$term))
                {
                    $messageArr["error"][] = 'Error getting terms for Post ID: ' . $term["_id"] . '. ' . $term["error"]["type"] . '. ' . $term["error"]["reason"] . '. ' . $term["error"]["caused_by"]["type"] . '.';
                } else {
                    // No errors encountered, so we can continue 


                }

            }
        }

        return $messageArr;

    */
        

    }

    /**
     * Get the terms and sentiment for a specific post ID.
     */
    public function termsAndSentimentForPost($postId)
    {
        $returnArr = [];
        $termArray = [];

        $terms = $this->search->termVectorsForPost($postId);

        // A successful REST query should return an array with 'found' in it. If not, throw an error and quit
        if(!in_array('found',$terms)) {
            $messageArr["error"][] = 'Elastic response missing found node. Please try again';
        } else {
            // If the post ID was not found in Elastic, quit and report that
            if($terms["found"] != 'true')
            {
                $messageArr["warning"][] = 'Post ID ' . $postId . ' not found in Elastic. Terms cannot be computed.';
            }
            // Otherwise the post was found, so proceed with calculating terms
            else 
            {
                foreach($terms["term_vectors"]["body.mterms"]["terms"] as $term => $val)
                {
                    $termArray[] = $term;
                }

                $combArr = [];
                $sentiment = new \PHPInsight\Sentiment();

                foreach($termArray as $key => $value)
                {
                    $combArr["term"][] = $value;
                    $combArr["score"][] = $sentiment->score($value);
                    $combArr["cat"][] = $sentiment->categorise($value);
                }

                $posNegArr = [];
                foreach($combArr["term"] as $key => $value)
                {
                    if($combArr["score"][$key]["pos"] > 0.333)
                    {
                        $posNegArr["pos"][] = $value;
                    }
                    elseif($combArr["score"][$key]["neg"] > 0.333)
                    {
                        $posNegArr["neg"][] = $value;
                    }
                    elseif($combArr["score"][$key]["neu"] > 0.333)
                    {
                        $posNegArr["neu"][] = $value;
                    }
                    else
                    {
                        $posNegArr["neu"][] = $value;
                    }

                }
            }
        }
            
        return $messageArr;
    }

    /**
     *  Scheduled command will call this function to queue the Daily Chatty word cloud
     *  for creation.
     */
    public function createChattyDailyCloud()
    {
        if(app_setting::dailyCloudActive())
        {
            // Most (all?) of the Daily Chatty cloud options are stored in app_settings and
            // can be changed from the wordclouds page. That means there is little need to pass
            // them to this function from the Laravel Command. Instead, look them up and if
            // they need changed, do so with the Admin functionality instead of opening up
            // the Command.

            $author = app_setting::dailyCloudUser();
            $from = date("Y-m-d H:i:s", strtotime("now") - app_setting::dailyCloudHours() * 3600);
            $to = date("Y-m-d H:i:s");
            $cloudPerm = app_setting::dailyCloudPerms();
            $tablePerm = app_setting::dailyCloudTablePerms();
            $filter = app_setting::dailyCloudFilter();
            $colorset = app_setting::dailyCloudColorset();
            $phrases = app_setting::dailyCloudPhrases();
            
            $this->queueCreateWordCloudJob($author,$from,$to,TRUE,$cloudPerm,$tablePerm,$filter,$colorset,$phrases);

            // Logging is handled in the next step, not much more to do here
        }
    }

    /**
     * Queue up a Laravel job to asynchronously generate a word cloud for the user.
     * 
     * @param string $author: 
     * @param PHPDate $from: 
     * @param PHPDate $to:
     * @return 
     */
    public function queueCreateWordCloudJob($author,$from,$to,$async,$cloudPerm,$tablePerm,$filterId,$colorsetId,$phrases)
    {
        $messageArr = [];

        // From and To are being validated and calculated in the WordCloudController, so we're safe
        // to simply use them here.

        // Each word cloud will get a GUID, so generate that now
        // https://laravel.com/docs/5.6/helpers#method-str-uuid
        // https://stackoverflow.com/questions/48818099/in-laravel-5-6-there-are-new-uuid-methods-how-do-i-use-them?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
        $uuid = (string) Str::uuid();

        // Store the datetime stamp that the word cloud was generated
        $dateTimeStamp = Carbon::now();

        // Generate the word_cloud record - contains all necessary info to create a work cloud 
        // but doesn't actually do any work yet.
        $wordCloud = new word_cloud;
        $wordCloud->id = $uuid;
        $wordCloud->user = $author;
        if($author == app_setting::dailyCloudUser()) {
            $wordCloud->created_by = app_setting::dailyCloudUser();
        } else {
            $wordCloud->created_by = Auth::user()->name;
        }
        $wordCloud->imagefilepath = '/null';
        $wordCloud->from = $from;
        $wordCloud->to = $to;
        $wordCloud->percent_complete = 0;
        $wordCloud->start_time = Carbon::now();
        $wordCloud->share_cloud = $cloudPerm;
        $wordCloud->share_table_download = $tablePerm;
        $wordCloud->word_cloud_filter = $filterId;
        $wordCloud->word_cloud_colorset = $colorsetId;
        $wordCloud->end_time = NULL;
        $wordCloud->post_count = 0;
        $wordCloud->generate_phrases = $phrases;
        $wordCloud->status = 'Queued';
        $wordCloud->save();

        // Testing is very difficult when a worker thread processes the job asynchronously. If the async flag is true (default)
        // then do it in the background, otherwise call it synchronously in the foreground so we can watch for errors.
        if($async) {
            CreateWordCloud::dispatch($wordCloud);
        } else {
            $this->generateWordCloudForAuthor($wordCloud->id);
        }
        

        // Display a message indicating that the word cloud generation process has been started
        $message = 'Successfully queued word cloud ' . $uuid . ' for user ' . $author . '. Status will be updated as job is processed.';
        $messageArr["success"][] = $message;

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            if($author == app_setting::dailyCloudUser()) {
                $db_action->username = app_setting::dailyCloudUser();
            } else {
                $db_action->username = Auth::user()->name;
            }
            $db_action->message = $message;
            $db_action->save();
        }

        // Send the user back to the word_cloud page with the 'successfully queued' message. The row will show in the table with a status of
        // queued. When it's successful they can click on it to view the result.
        return $messageArr;
    }

    /**
     * Generate and store a word cloud for a user.
     * 
     * @param string $author: 
     * @param PHPDate $from: 
     * @return 
     */
    public function generateWordCloudForAuthor($wordcloud_id)
    {
        $messageArr = [];

        // Word cloud was created earlier before this Job() started running, so retrieve
        // the parameters and update the status from Queued to Processing so the user knows
        // something is happening.
        $wordCloud = word_cloud::find($wordcloud_id);
        $wordCloud->status = 'Querying Posts';
        $wordCloud->percent_complete = 1;
        $wordCloud->save();

        /* 2018-09-09 Mike: There is a single special use-case I want to add: generating a word cloud for the entire
           Chatty for an 18hr period. If that author is passed, don't limit results to any specific author.
        */
        $idArray = [];
        if($wordCloud->user == app_setting::dailyCloudUser()) {

            // Grab the filters for posts we do NOT want in the cloud
            $catFilterArr = [];
            if(app_setting::dailyCloudOntopic()) {
                $catFilterArr[] = postcategory::categoryId('ontopic');
            }
            if(app_setting::dailyCloudNWS()) {
                $catFilterArr[] = postcategory::categoryId('nws');
            }
            if(app_setting::dailyCloudStupid()) {
                $catFilterArr[] = postcategory::categoryId('stupid');
            }
            if(app_setting::dailyCloudPolitical()) {
                $catFilterArr[] = postcategory::categoryId('political');
            }
            if(app_setting::dailyCloudTangent()) {
                $catFilterArr[] = postcategory::categoryId('tangent');
            }
            
            if(app_setting::dailyCloudInformative()) {
                $catFilterArr[] = postcategory::categoryId('informative');
            }
            if(app_setting::dailyCloudNuked()) {
                $catFilterArr[] = postcategory::categoryId('nuked');
            }
            /*
            SELECT posts.id
            FROM posts
            JOIN threads
            ON posts.thread_id = threads.id
            WHERE threads.date BETWEEN CURRENT_DATE - interval '18 hour' AND CURRENT_DATE
            AND posts.category = 4; 
            */
            
            $threadIds = DB::table('posts')
                    ->select('posts.id')
                    ->join('threads', function($join) use ($wordCloud) {
                        $join->on('posts.id','=','threads.id')
                             ->whereBetween('threads.date',[$wordCloud->from,$wordCloud->to]);
                    })
                    ->whereIn('posts.category',$catFilterArr);

            $ids = DB::table('posts')
                    ->select('posts.id')
                    ->joinSub($threadIds, 'thread_ids', function($join) {
                        $join->on('posts.thread_id','=','thread_ids.id');
                    })
                    ->get();

            // Grab all post ID's for ALL authors within the timeframe
            /*
            $ids = DB::table('posts')
                ->where([
                    ['indexed','true'],
                    ['category','<>',postcategory::categoryId('nuked')],
                ])
                ->orderBy('date','desc')
                ->whereBetween('date',[$wordCloud->from,$wordCloud->to])
                ->get();
            */
        } else {
            // Grab all post ID's for the desired author. Use ILIKE (postgresql-specific comparator) to lowercase
            // the author name in case the username that was passed in doesn't match the one in the database case-wise.
            $ids = DB::table('posts')->where([
                ['indexed','true'],
                ['author_c','ILIKE',$wordCloud->user],
                ['category','<>',postcategory::categoryId('nuked')],
            ])
            ->orderBy('date','desc')
            ->whereBetween('date',[$wordCloud->from,$wordCloud->to])
            ->get();
        }

        foreach($ids as $id)
        {
            $idArray[] = $id->id;
        }

        /* For very long timeframes the user might have hundreds of thousands of posts. Sending
            all of that to Elastic in a single query *may* be possible but trying to crawl through
            the JSON afterwards would be a disaster. As we did with the IndexPosts() operation, batch
            the ID's at a certain breakpoint (currently 500) and process them in groups.
        */

        $wordCloud->status = 'Querying Elastic';
        $wordCloud->percent_complete = 20;
        $wordCloud->save();

        $batchSize = app_setting::wordCloudElasticBatchSize();
        $batchOfPostIDs = [];

        // Instantiate a PHPInsight class and feed it terms
        $sentiment = new \PHPInsight\Sentiment();

        // The array that will hold all the terms + counts, plus a few counters so we can display
        // success/failure numbers
        $termArr = [];
        $successPostCount = 0;
        $errorPostCount = 0;

        // Doc Frequency is the # of times the term was used in any document in an Elastic shard
        // (how many times the term was used in the entire dataset, though broken down by shard).
        // I want the highest doc frequency so I can do some math later on with it.
        $highestDocFreq = 0;

        for($i = 0; $i < count($idArray); $i++) 
        {
            $batchOfPostIDs[] = $idArray[$i];

            // Every {x} posts,send the batch request
            if($i % $batchSize == 0) {

                $returnResults = $this->search->termVectorsWithScoreForPostIds($batchOfPostIDs);
                $returnResults = json_decode($returnResults,true);

                // Make sure 'docs' exists in the response - if not, something went wrong
                if(!array_key_exists('docs',$returnResults)) {
                    $messageArr["error"][] = 'Elastic response missing docs node. Please try again.';
                } else {
                    // Docs was found, so start iterating the results. Each is self-contained.
                    foreach($returnResults["docs"] as $post)
                    {
                        // Make sure result doesn't contain an error - log it if so, and move on to the next record
                        if(array_key_exists('error',$post))
                        {
                            $errorPostCount += 1;
                        } else {
                            // No errors encountered, so we can continue 
                            if(array_key_exists("term_vectors",$post)) 
                            {
                                // Update the counter so we can display how many posts were used in the cloud
                                $successPostCount += 1;

                                foreach($post["term_vectors"]["body.mterms"]["terms"] as $term => $arr)
                                {
                                    if(array_key_exists($term,$termArr)) {
                                        $termArr[$term]["term_freq"] = $termArr[$term]["term_freq"] + $arr["term_freq"];
                                        $termArr[$term]["score"] = $arr["score"];
                                        $termArr[$term]["doc_freq"] = $arr["doc_freq"];
                                    } else {
                                        $termArr[$term]["term_freq"] = $arr["term_freq"];
                                        $termArr[$term]["score"] = $arr["score"];
                                        $termArr[$term]["doc_freq"] = $arr["doc_freq"];
                                    }
                                    
                                    if($arr["doc_freq"] > $highestDocFreq) {
                                        $highestDocFreq = $arr["doc_freq"];
                                    }
                                }
                            }
                        }
                    }
                }

                // Reset the batch for the next time around
                $batchOfPostIDs = [];
            }            
        }

        // Send the last batch if it's not empty
        if(!empty($batchOfPostIDs)) 
        {
            $returnResults = $this->search->termVectorsWithScoreForPostIds($batchOfPostIDs);
            $returnResults = json_decode($returnResults,true);

            // Make sure 'docs' exists in the response - if not, something went wrong
            if(!array_key_exists('docs',$returnResults)) {
                $messageArr["error"][] = 'Elastic response missing docs node. Please try again.';
            } else {
                // Docs was found, so start iterating the results. Each is self-contained.
                foreach($returnResults["docs"] as $post)
                {
                    // Make sure result doesn't contain an error - log it if so, and move on to the next record
                    if(array_key_exists('error',$post))
                    {
                        $errorPostCount += 1;
                    } else {
                        // No errors encountered, so we can continue 
                        if(array_key_exists("term_vectors",$post)) 
                        {
                            // Update the counter so we can display how many posts were used in the cloud
                            $successPostCount += 1;

                            foreach($post["term_vectors"]["body.mterms"]["terms"] as $term => $arr)
                            {
                                if(array_key_exists($term,$termArr)) {
                                    $termArr[$term]["term_freq"] = $termArr[$term]["term_freq"] + $arr["term_freq"];
                                    $termArr[$term]["score"] = $arr["score"];
                                    $termArr[$term]["doc_freq"] = $arr["doc_freq"];
                                } else {
                                    $termArr[$term]["term_freq"] = $arr["term_freq"];
                                    $termArr[$term]["score"] = $arr["score"];
                                    $termArr[$term]["doc_freq"] = $arr["doc_freq"];
                                }

                                if($arr["doc_freq"] > $highestDocFreq) {
                                    $highestDocFreq = $arr["doc_freq"];
                                }
                            }
                        }
                    }
                }
            }
        }

        $wordCloud->status = 'Analyzing Sentiment';
        $wordCloud->percent_complete = 40;
        $wordCloud->save();

        foreach($termArr as $term => $value)
        {
            // Calculate the POS/NEU/NEG values for the term. Returned as:
            /*
                array:3 [▼
                    "pos" => 0.333
                    "neg" => 0.333
                    "neu" => 0.333
                ]
            */
            
            $sentStr = 'neu';
            $termSentArr = $sentiment->score($term);
            if($termSentArr["pos"] > 0.333) {
                $sentStr = 'pos';
            } elseif($termSentArr["neg"] > 0.333) {
                $sentStr = 'neg';
            }
            
            $workTable = new word_cloud_work;
            $workTable->word_cloud_id = $wordCloud->id;
            $workTable->user = $wordCloud->user;
            $workTable->term = $term;
            $workTable->count = $value["term_freq"];
            $workTable->score = $value["score"];
            $workTable->doc_freq = $value["doc_freq"];
            //$workTable->computed_score = $value["term_freq"] * ($value["score"] * 0.8);
            $workTable->terms_in_phrase = 1;            // By default, all work_cloud_work entries have 1 term_in_phrase unless later concatenated
            $workTable->computed_score = $value["term_freq"] * $value["score"];
            $workTable->sentiment = $sentStr;
            $workTable->save();

        }

        /************* COMPUTED SCORE CALCULATION ***************/
        /* Only after we've completely populated the work_table for this cloud can we calculate the
            mean/median/std.dev on it. So as much as I hate looping back through it all now, it's the
            simplest way to create a computed_score that takes into effect the stats of the entire
            dataset vs. simply looking at terms on their own.
        */
        /************* COMPUTED SCORE CALCULATION ***************/

        $wordCloud->status = 'Calculating Computed Score';
        $wordCloud->percent_complete = 60;
        $wordCloud->save();

        // Get the document_frequency stats for the entire word_cloud dataset
        $freqMean = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordCloud->id)->avg('doc_freq');
        $freqMedian = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordCloud->id)->get()->median('doc_freq');
        $freqStdDev = $this->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordCloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['doc_freq']]; })->all());
        $workTableRecords = word_cloud_work::where('word_cloud_id','=',$wordCloud->id)->get();

        foreach($workTableRecords as $termToCompute) 
        {
            $this->calculateComputedScore($termToCompute, $freqMean, $freqMedian, $freqStdDev);
        }

        /************* PHRASE DETECTION ***************/
        /*
            This is not a computation I want to be doing at runtime each time the cloud is viewed, even though
            that's where the logic fits best. Instead we're basically going to do a "pretend" viewing where we
            sample the top words for the cloud, then do a bit of phrase matching on them and save the result.
        */
        /************* PHRASE DETECTION ***************/

        // Filters mess up the phrase generation, so if the user wants phrases, save their filter choice, set it to No Filter
        // then restore it at the end

        if($wordCloud->generate_phrases) 
        {
            $noFilterId = word_cloud_filter::getFilterId('No Filter');
            $origFilter = $wordCloud->word_cloud_filter;
            $wordCloud->word_cloud_filter = $noFilterId;

            $wordCloud->status = 'Running Phrase Detection';
            $wordCloud->percent_complete = 80;
            $wordCloud->save();

            $counter = 0;
            $termsScoresCalcVals = $this->getTermScoreArrayToShowInCloud($wordCloud->id);
            foreach($termsScoresCalcVals['termScores'] as $termScore) 
            {
                foreach($termScore as $key => $value)
                {
                    if($counter < (app_setting::wordCloudTermsPerCloud())) {

                        $term = $key;
                        // This should loop 75/100/150x pulling the terms that will be displayed in the cloud.
                        // For each one, grab the phrases and populate word_cloud_phrases for this wordcloud. date_create_from_format('Y-m-d H:i:s', app_setting::lastEventPoll());

                        $fromDate = Carbon::parse($wordCloud->from);
                        $toDate = Carbon::parse($wordCloud->to);
                        
                        $trigramsForTerm = NULL;
                        // Daily Cloud needs time right down to the second
                        if($wordCloud->created_by == app_setting::dailyCloudUser()) {
                            $trigramsForTerm = $this->search->generatePostTrigramsForTerm($term,$wordCloud->user,$fromDate->format('Y-m-d H:i:s'),$toDate->format('Y-m-d H:i:s'));
                        }
                        // All other users we just need to the day
                        else {
                            $trigramsForTerm = $this->search->generatePostTrigramsForTerm($term,$wordCloud->user,$fromDate->format('Y-m-d'),$toDate->format('Y-m-d'));
                        }

                        foreach($trigramsForTerm as $termTrigram)
                        {
                            // Exact phrase already exists in word_cloud_phrases table
                            if(word_cloud_phrase::where([
                                ['phrase','=',$termTrigram],
                                ['wordcloud_id','=',$wordCloud->id],
                            ])->exists()) {
                                /**
                                 *  Let's say "perfect lunar eclipse" exists 4x in a body of text. In the cloud terms,
                                 *  "perfect", "lunar" and "eclipse" each exist once. So we'll query trigrams for those
                                 *  three terms (three trigram queries). Each will return the same body of text, so
                                 *  we end up with:
                                 *  "perfect" => "perfect lunar eclipse" phrase x4
                                 *  "lunar" => "perfect lunar eclipse" phrase x4
                                 *  "eclipse" => "perfect lunar eclipse" phrase x4
                                 * 
                                 *  As we originally mentioned, the actual phrase occurs only 4x, but our logic will make
                                 *  it *appear* to occur 12x. So the solution is to record all 4 initial instances (returned 
                                 *  when we generated trigrams for perfect) and disregard all the add'l phrases generated
                                 *  by "lunar" and "eclipse".
                                 */
                                if(word_cloud_phrase::where([
                                    ['phrase','=',$termTrigram],
                                    ['wordcloud_id','=',$wordCloud->id],
                                    ['term','=',$term]
                                ])->exists()) {
                                    $existingPhrase = word_cloud_phrase::where([
                                        ['phrase','=',$termTrigram],
                                        ['wordcloud_id','=',$wordCloud->id],
                                    ])->first();
                                    $newCount = $existingPhrase->phrase_count;
                                    $existingPhrase->phrase_count = $newCount + 1;
                                    $existingPhrase->save();
                                }
                            // This is a new phrase that didn't exist before
                            } else {
                                // We're only dealing with trigrams (3 terms separated by 2 spaces) so split
                                // them out and save them for individual analysis
                                $termsInPhrase = explode(" ", $termTrigram);

                                $phrase = new word_cloud_phrase();
                                $phrase->wordcloud_id = $wordCloud->id;
                                $phrase->term = $term;
                                $phrase->phrase = $termTrigram;
                                $phrase->phrase_count = 1;
                                $phrase->phrase_term_1 = $termsInPhrase[0];
                                $phrase->phrase_term_2 = $termsInPhrase[1];
                                if(count($termsInPhrase) > 2) {
                                    $phrase->phrase_term_3 = $termsInPhrase[2];
                                }
                                $phrase->save();
                            }
                        }
                    }
                }
                $counter++;
            }

            /** *****************************************************************************
             * 
             *      PHRASE FILTERING
             * 
             *      Trim down the phrase dataset in SQL to just the probable phrases.
             * 
                ***************************************************************************** */

            $wordCloud->status = 'Filtering Phrases';
            $wordCloud->percent_complete = 90;
            $wordCloud->save();

            //dd(word_cloud_phrase::where('wordcloud_id','=',$wordCloud->id)->get());

            $distinctMultipleTerms = DB::table('word_cloud_phrases')
                                ->select('term',DB::raw('COUNT(id) as term_count'))
                                ->where('wordcloud_id',$wordCloud->id)
                                ->where(function ($query) {
                                    $query->where(function ($query2) { $query2->whereNull('phrase_term_3')
                                                                                ->where('phrase_count','>',app_setting::twoTermPhraseCountThreshold()); })
                                            ->orWhere(function ($query2) { $query2->whereNotNull('phrase_term_3')
                                                                                ->where('phrase_count','>',app_setting::threeTermPhraseCountThreshold()); });
                                })
                                ->groupBy('term')
                                ->orderByRaw('COUNT(id) DESC')
                                ->havingRaw('COUNT(id) > ?',[1])
                                ->get();

            /*
            $distinctTermWorkCounts = [];
            foreach($distinctMultipleTerms as $phraseTerm) {
                if(!word_cloud_work::where([['word_cloud_id',$wordCloud->id],['term','=',$phraseTerm->term]])->exists()) {
                    dd($phraseTerm);
                } else {
                    $workCount = word_cloud_work::where([['word_cloud_id',$wordCloud->id],['term','=',$phraseTerm->term]])->first()->count;
                    $distinctTermWorkCounts += array($phraseTerm->term => $workCount);
                }
                
            }
            */

            foreach($distinctMultipleTerms as $phraseTerm)
            {
                // Grab the top 2-word phrase for a term, and decide whether it fits into a 3-word one. If not, use it as-is.
                $topTwoWordPhrase = DB::table('word_cloud_phrases')
                                        ->where('wordcloud_id',$wordCloud->id)
                                        ->where('term','=',$phraseTerm->term)
                                        ->whereNull('phrase_term_3')
                                        ->orderBy('phrase_count','DESC')
                                        ->limit(1)
                                        ->first();

                // Grab the top 3-word phrase for a term, and decide whether the 2-term one above fits into it 
                $topThreeWordPhrase = DB::table('word_cloud_phrases')
                                    ->where('wordcloud_id',$wordCloud->id)
                                    ->where('term','=',$phraseTerm->term)
                                    ->whereNotNull('phrase_term_3')
                                    ->orderBy('phrase_count','DESC')
                                    ->limit(1)
                                    ->first();

                // Certain terms may not even have a phrase associated with them so make sure there's
                // a phrase to work with before proceeding
                if(!is_null($topTwoWordPhrase))
                {
                    // There may not be a 3-word phrase for this term, in which case just take the highest 2-word phrase
                    if(!is_null($topThreeWordPhrase))
                    {
                        // Find the other 2-word phrase for the 3-word one
                        $leftSubPhrase = $topThreeWordPhrase->phrase_term_1 . ' ' . $topThreeWordPhrase->phrase_term_2;
                        $rightSubPhrase = $topThreeWordPhrase->phrase_term_2 . ' ' . $topThreeWordPhrase->phrase_term_3;

                        $otherTwoWordPhrase = NULL;
                        if($topTwoWordPhrase->phrase == $leftSubPhrase) {
                            $otherTwoWordPhrase = DB::table('word_cloud_phrases')
                                                ->where('wordcloud_id',$wordCloud->id)
                                                ->where('phrase','=',$rightSubPhrase)
                                                ->whereNull('phrase_term_3')
                                                ->first();
                        } else {
                            $otherTwoWordPhrase = DB::table('word_cloud_phrases')
                                                ->where('wordcloud_id',$wordCloud->id)
                                                ->where('phrase','=',$leftSubPhrase)
                                                ->whereNull('phrase_term_3')
                                                ->first();
                        }

                        // The 3-word phrase exists (to all and), one of the 2-word phrases exists (to all) but
                        // the other two word phrase (all and) does not. In this case, if it's not a triple-word
                        // phrase, use the 2-word one
                        if(!is_null($otherTwoWordPhrase)) 
                        {
                            $topResultForOtherPhraseTerm = DB::table('word_cloud_phrases')
                                                ->where('wordcloud_id',$wordCloud->id)
                                                ->where('term','=',$otherTwoWordPhrase->term)
                                                ->whereNull('phrase_term_3')
                                                ->orderBy('phrase_count','DESC')
                                                ->limit(1)
                                                ->first();
                            
                            // If the calculated 'other 2-word phrase' is also top for its term, then we have good reason
                            // to use the 3-word phrase and mark the other two terms complete
                            if($topResultForOtherPhraseTerm->id == $otherTwoWordPhrase->id)
                            {
                                // If the other phrase we calculated is also the top returned one for that phrase, use the 3-term one
                                $phrasesToRemoveForFirstTerm = word_cloud_phrase::where([['wordcloud_id',$wordCloud->id],
                                                                                        ['term','=',$phraseTerm->term],
                                                                                        ['id','<>',$topThreeWordPhrase->id]])->delete();
                                $phrasesToRemoveForSecondTerm = word_cloud_phrase::where([['wordcloud_id',$wordCloud->id],
                                                                                        ['term','=',$otherTwoWordPhrase->term],
                                                                                        ['id','<>',$topThreeWordPhrase->id]])->delete();
                            } else {
                                // Otherwise use the original top 2-word phrase we started with, remove all the other phrases
                                // for that term, and move on.
                                $phrasesToRemoveForFirstTerm = word_cloud_phrase::where([['wordcloud_id',$wordCloud->id],
                                                                                        ['term','=',$topTwoWordPhrase->term],
                                                                                        ['id','<>',$topTwoWordPhrase->id]])->delete();
                            }
                        }
                        else
                        {
                            $phrasesToRemoveForFirstTerm = word_cloud_phrase::where([['wordcloud_id',$wordCloud->id],
                                                                                    ['term','=',$topTwoWordPhrase->term],
                                                                                    ['id','<>',$topTwoWordPhrase->id]])->delete();
                        }
                    }
                    else
                    {
                        $phrasesToRemoveForFirstTerm = word_cloud_phrase::where([['wordcloud_id',$wordCloud->id],
                                                                                    ['term','=',$topTwoWordPhrase->term],
                                                                                    ['id','<>',$topTwoWordPhrase->id]])->delete();
                    }
                }    
                                
            }

            /**
             *  The above only works on phrases that meet the minimum phrase_count criteria in app_settings. The
             *  rest of the phrases are left in during the above processing for matching purposes only, but should
             *  be removed afterwards.
             */
            $first = DB::table('word_cloud_phrases')
                        ->select('id')
                        ->whereNull('phrase_term_3')
                        ->where('wordcloud_id','=',$wordCloud->id)
                        ->where('phrase_count','>',app_setting::twoTermPhraseCountThreshold());
            $delLowCountPhrases = DB::table('word_cloud_phrases')
                            ->where('wordcloud_id',$wordCloud->id)
                            ->whereNotIn('id', function ($query) use ($first, $wordCloud) {
                                $query->select('id')
                                    ->from('word_cloud_phrases')
                                    ->whereNotNull('phrase_term_3')
                                    ->where('wordcloud_id','=',$wordCloud->id)
                                    ->where('phrase_count','>',app_setting::threeTermPhraseCountThreshold())
                                    ->union($first);
                            })
                            ->delete();



            //dd(word_cloud_phrase::where('wordcloud_id','=',$wordCloud->id)->get());

            /** ********************************************************** */
            /*     REPLACE TERMS WITH PHRASES
                    Now that we're determined the likely phrases, if the user asked for phrase
                    generation we can go through and add the phrases, adjusting the terms as needed.
            */
            /** ********************************************************** */

            $wordCloud->status = 'Merging Phrases';
            $wordCloud->percent_complete = 95;
            $wordCloud->save();

            $phrases = word_cloud_phrase::where('wordcloud_id','=',$wordCloud->id)->get();

            foreach($phrases as $phrase)
            {
                // Each time we replace a 2-word or 3-word phrase, there are either 1 or 2 more terms to process. It's possible
                // the logic below will have completely removed the current looping term from word_cloud_work. We can easily check
                // whether the existing phrase has been added to word_cloud_work and just skip over it.
                if(!word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->phrase]])->exists())
                {
                    // Call the function to actually handle the processing of the phrase
                    $this->processPhrase($wordCloud, $phrase, $freqMean, $freqMedian, $freqStdDev);
                }
            }

            // Now that all phrases that *should* be added to word_cloud_work *were* added to word_cloud_work, go through all the
            // terms used in each phrase and decrement the counter for each individual term. That way if HUE was used 64x in a phrase
            // and the term exists 128x in a cloud, it decreases appropriately in size now that both the term and phrase appear side-by-side.
            foreach($phrases as $phrase)
            {
                // The above code block might skip over a phrase (for a number of reasons) so make sure it was actually added to
                // word_cloud_work before attempting to work with it.
                if(word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->phrase]])->exists())
                {
                    // Call the function to actually handle the processing of the phrase
                    $this->reduceWorkCountsForPhraseTerms($wordCloud, $phrase, $freqMean, $freqMedian, $freqStdDev);
                }
            }
            
            // Finally, we can clean up everything in the word_cloud_phrases table since it's just a temp table. Everything of value
            // *should* be in the word_cloud_work table (which is permanent).
            word_cloud_phrase::where('wordcloud_id','=',$wordCloud->id)->delete();
        }

        // Restore the original filter now that we're done determining phrases
        if($wordCloud->generate_phrases) {
            $wordCloud->word_cloud_filter = $origFilter;
            $wordCloud->save();
        }

        $wordCloud->percent_complete = 100;
        $wordCloud->end_time = Carbon::now();
        $wordCloud->save();

        // Finally, update the total post count for the word cloud
        $wordCloud->post_count = $successPostCount;
        $wordCloud->save();

        // Update the status to Success so they know they can click
        $wordCloud->status = 'Success';
        $wordCloud->save();

        $carbonStartTime = Carbon::parse($wordCloud->start_time);
        $carbonEndTime = Carbon::parse($wordCloud->end_time);

        // Delete the old Daily Cloud - it's not needed any longer
        if($wordCloud->created_by == app_setting::dailyCloudUser())
        {
            $workCount = 0;
            $oldGUID = app_setting::dailyCloudGUID();
            if(!is_null($oldGUID)) {
                if(word_cloud::where('id','=',$oldGUID)->exists()) {
                    $oldCloud = word_cloud::find($oldGUID);
                    $oldCloud->delete();
                }
                $workCount = word_cloud_work::where('word_cloud_id','=',$oldGUID)->count();
                if($workCount > 0)
                {
                    word_cloud_work::where('word_cloud_id','=',$oldGUID)->delete();
                }
                if(app_setting::loggingLevel() >= 3)
                {
                    $db_action = new dbAction();
                    $db_action->username = $wordCloud->created_by;
                    $db_action->message = 'Deleted Daily Cloud with GUID: ' . $oldGUID . '. Replaced by Daily Cloud with GUID: ' . $wordCloud->id . '. Deleted ' . 
                        $workCount . ' rows from word_cloud_work table.';
                    $db_action->save();
                }
            }

            // If we got this far in the Daily Cloud process, we can probably safely update app_settings
            // with the new GUID
            $settings = app_setting::find(1);
            $settings->chatty_daily_wordcloud_guid = $wordCloud->id;
            $settings->save();
        }
        
        // Summary message for logging and display
        $messageArr["success"][] = 'Successfully generated word cloud ' . $wordCloud->id . ' for user ' . $wordCloud->user . '. ' . $successPostCount . ' posts successfully examined.';
        $messageArr["warning"][] = 'Unable to generate term vectors for ' . $errorPostCount . ' posts in date range.';
        $message = 'Successfully generated word cloud ' . $wordCloud->id . ' for user ' . $wordCloud->user . '. ' . $successPostCount . ' posts successfully scanned for term vectors. Unable to generate term vectors for ' . $errorPostCount . ' posts. Cloud generated in ' . $carbonStartTime->diffInMinutes(Carbon::parse($wordCloud->end_time)) . ' minutes.' ;

        if(app_setting::loggingLevel() >= 3)
        {
            $db_action = new dbAction();
            $db_action->username = $wordCloud->created_by;
            $db_action->message = $message;
            $db_action->save();
        }
    }

    /**
     *  Process each phrase that was detected. Could be two or three term phrase, doesn't really matter.
     * 
     *  @param App\Chatty\word_cloud wordCloud
     *  @param App\Chatty\word_cloud_phrase phrase
     *  @param boolean threeTermPhrase
     *  @param float freqMean
     *  @param float freqMedian
     *  @param float freqStdDev
     */
    private function processPhrase($wordCloud, $phrase, $freqMean, $freqMedian, $freqStdDev)
    {
        // For odd things like URLs there may not be a word_cloud_work entry and therefore no way to know what score to assign
        // the phrase when it's inserted. In those rare cases (WADMAASI and his www.bungie.net) just skip it and move on.
        if(word_cloud_work::where([['word_cloud_id',$wordCloud->id],['term','=',$phrase->term]])->exists())
        {
            $termCountForPhrase = 2;
            $workTerm = word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->term]])->first();

            $newWorkRecord = $this->addPhraseToWordCloudWork($workTerm, $phrase->phrase, $phrase->phrase_count, $termCountForPhrase);
            $this->calculateComputedScore($newWorkRecord, $freqMean, $freqMedian, $freqStdDev);
        }
    }

    /**
     *  We can't start reducing work counts until after all phrases are added to word_cloud_work because the process
     *  needs all the terms there (with their original counts) to make decisions such as scoring and count. Instead,
     *  after all phrases are added we loop back through one more time and decrement the counts for each term so that
     *  they appear proportionally after the phrases are added.
     */
    private function reduceWorkCountsForPhraseTerms($wordCloud, $phrase, $freqMean, $freqMedian, $freqStdDev)
    {
        if(isset($phrase->phrase_term_1)) {
            if(word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->phrase_term_1]])->exists()) {
                $term1work = word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->phrase_term_1]])->first();
                $term1work->count -= $phrase->phrase_count;
                if($term1work->count == 0) {
                    $term1work->count = 1;
                }
                $term1work->save();
                $this->calculateComputedScore($term1work, $freqMean, $freqMedian, $freqStdDev);
            }
        }
        if(isset($phrase->phrase_term_2)) {
            if(word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->phrase_term_2]])->exists()) {
                $term2work = word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->phrase_term_2]])->first();
                $term2work->count -= $phrase->phrase_count;
                if($term2work->count == 0) {
                    $term2work->count = 1;
                }
                $term2work->save();
                $this->calculateComputedScore($term2work, $freqMean, $freqMedian, $freqStdDev);
            }
        }
        if(isset($phrase->phrase_term_3)) {
            $termCountForPhrase = 3;
            if(word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->phrase_term_3]])->exists()) {
                $term3work = word_cloud_work::where([['word_cloud_id','=',$wordCloud->id],['term','=',$phrase->phrase_term_3]])->first();
                $term3work->count -= $phrase->phrase_count;
                if($term3work->count == 0) {
                    $term3work->count = 1;
                }
                $term3work->save();
                $this->calculateComputedScore($term3work, $freqMean, $freqMedian, $freqStdDev);
            }
        }
    }

    /**
     *  Insert a brand-new word_cloud_work record for a chosen wordcloud containing a phrase to display.
     *  Use this anytime you want a phrase added but don't want to completely remove one of its' constituent
     *  terms from the cloud.
     * 
     * @param  App\Chatty\word_cloud_work wordCloudWorkForTerm
     * @param string phrase
     * @param integer phrase_count
     * @param integer terms_in_phrase
     * 
     */
    private function addPhraseToWordCloudWork($wordCloudWorkForTerm, $phrase, $phrase_count, $terms_in_phrase)
    {
        $newPhraseWork = new word_cloud_work();
        $newPhraseWork->word_cloud_id = $wordCloudWorkForTerm->word_cloud_id;
        $newPhraseWork->user = $wordCloudWorkForTerm->user;
        $newPhraseWork->term = $phrase;
        $newPhraseWork->sentiment = $wordCloudWorkForTerm->sentiment;
        $newPhraseWork->count = $phrase_count;
        if($terms_in_phrase == 2) {
            $newPhraseWork->score = $wordCloudWorkForTerm->score * 1.75;
        } else {
            $newPhraseWork->score = $wordCloudWorkForTerm->score * 2.25;
        }
        $newPhraseWork->computed_score = $wordCloudWorkForTerm->computed_score;
        $newPhraseWork->doc_freq = $wordCloudWorkForTerm->doc_freq;
        $newPhraseWork->terms_in_phrase = $terms_in_phrase;
        $newPhraseWork->save();

        return $newPhraseWork;
    }

    // Called twice: once during wordcloud initial generation, and then individually for any terms
    // that are combined into phrases
    private function calculateComputedScore($termToCompute, $freqMean, $freqMedian, $freqStdDev)
    {
        // Max Standard Deviations from the mean for doc_freq (in either direction)
        // before the term is flagged as a super-common one and given a low score.
        $highestStdDevsFromMean = 2.5;

        //
        //  (1/{doc_freq}) / {freqStdDev} == 0.0339 (for "i" with 2,442,595 freq) to 
        // Very common word, we're going to nerf the score slightly
        // e.g. 2440633 doc_freq - 54459 freq_mean = 2,386,184 above mean
        if($termToCompute->doc_freq > $freqMean) 
        {
            $pointsAboveMean = $termToCompute->doc_freq - $freqMean;

            // Figure out how many std. devs. we are from mean
            // e.g. 2386184 (pntsfrommean) / 132591 (std.dev) = 17.99 Std. Dev's from mean
            $stdDevsFromMean = $pointsAboveMean / $freqStdDev;

            // Generally speaking, the Std. Dev is a fairly large 6-digit number because everyone
            // uses "i" and it's sitting at like 2,440,222 freq. It seems from testing that being
            // 1 std. dev. over mean is putting you into "yours, ours, and, i" territory which we
            // really don't want anyways. So I'm putting a cap of 1 Std. Dev on being above mean.
            // Everything over that just gets a comp. score equal to score.
            if($stdDevsFromMean >= $highestStdDevsFromMean) {
                $termToCompute->computed_score = $termToCompute->score;
            } else {
                // Otherwise, if they're within 1 std. dev., I'm going to cap usage vs. score. Score is
                // the more telling marker of interesting-ness, so reducing the count is a good way of 
                // keeping the term in case it's super interesting but reducing the score.
                $percentReduction = (100 - (($stdDevsFromMean/$highestStdDevsFromMean)*100))/100;
                //$percentReduction = ((50 * $stdDevsFromMean)/100)/2;
                $termToCompute->computed_score = ($termToCompute->score * 0.5) * ($termToCompute->count * $percentReduction);
            }

        } 
        // Uncommon word, we're going to buff it slightly
        // e.g. 54459 freq_mean - 33760 doc_freq == 20,699 below mean
        // 54,459 freq_mean - 1 doc_freq = 54,458 below mean
        else 
        {
            $pointsBelowMean = $freqMean - $termToCompute->doc_freq;

            // Figure out how many std. devs. we are from mean
            // e.g. 20,699 (pntsfrommean) / 132591 (std.dev) = 0.15 Std. Dev's from mean
            // e.g. 54,458 (pntsfrommean) / 132591 (std.dev) = 0.41 Std. Dev's from mean
            $stdDevsFromMean = $pointsBelowMean / $freqStdDev;

            // It's EXCEEDINGLY unlikely that mean is so high that we could ever get > 1 here, but just in case
            // let's cap it. Probably not necessary.
            if($stdDevsFromMean >= $highestStdDevsFromMean) {
                $termToCompute->computed_score = $termToCompute->score;
            } else {
                // Inverse to the above, if the term is an interesting one, boost the count a bit to shove it up
                // the stack.
                //$percentIncrease = (((50 * $stdDevsFromMean)/100)/2)+1;
                $percentIncrease = (100 - (($stdDevsFromMean/$highestStdDevsFromMean)*100))/100;
                $termToCompute->computed_score = ($termToCompute->score * 0.5) * ($termToCompute->count * $percentIncrease);
            }

        }

        /*
        if($termToCompute->doc_freq > $freqMean) {
            $numTimesOverMean = $termToCompute->doc_freq / $freqMean;
            if($termToCompute->count > $numTimesOverMean) {
                // Shrink the count, lowering the computed score by an amount corresponding to doc_freq
                $termToCompute->computed_score = floatval(($termToCompute->count - $numTimesOverMean) * $termToCompute->score);
            } else {
                // Effectively set the count to 1 for purposes of this calculation
                $termToCompute->computed_score = floatval($termToCompute->score);
            }
        } else {
            $termToCompute->computed_score = floatval($termToCompute->score * $termToCompute->count);
        }
        */
        $termToCompute->save();

    }

    /**
     * For the specified word cloud, grab all the appropriate word_cloud_work rows. Apply the selected
     * filter. This is in a function because both the Cloud and Table use it.
     */
    public function getWordCloudWork($wordcloud_id)
    {
        $wordcloud = word_cloud::find($wordcloud_id);
        $word_cloud_work = null;
        $filterName = word_cloud_filter::find($wordcloud->word_cloud_filter)->name;

        // Term statistics
        $termMean = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->avg('score');
        $termMedian = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->get()->median('score');
        $termStdDev = $this->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordcloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['score']]; })->all());

        // Freq statistics
        $freqMean = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->avg('doc_freq');
        $freqMedian = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->get()->median('doc_freq');
        $freqStdDev = $this->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordcloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['doc_freq']]; })->all());

        /**************************/
        /******* FILTERING ********/
        /**************************/
        
        /********** LEFT OF CENTER **********/
        // Drop all terms lower than the median score
        if($filterName == 'Left of Center')
        {
            $word_cloud_work = word_cloud_work::where([
                    ['word_cloud_id','=',$wordcloud->id],
                    ['score','>',$termMedian],
                    //['doc_freq','<',$freqMean + ($freqStdDev*1.5)],
                ])
                ->orderBy('computed_score','desc')
                ->get();
        }
        /********** MIDDLE OF THE ROAD **********/
        // Drop all terms lower than the average (mean) score
        else if($filterName == 'Middle of the Road')
        {
            $word_cloud_work = word_cloud_work::where([
                    ['word_cloud_id','=',$wordcloud->id],
                    ['score','>',$termMean],
                    //['doc_freq','<',$freqMean + ($freqStdDev*1.5)],
                ])
                ->orderBy('computed_score','desc')
                ->get();
        }
        /********** STANDARD DEVIANT **********/
        // Drop terms 3 standard deviations above mean frequency
        else if($filterName == 'Standard Deviant')
        {
            $lessThan = $freqMean + ($freqStdDev * 3);
            $word_cloud_work = word_cloud_work::where([
                ['word_cloud_id','=',$wordcloud->id],
                ['doc_freq','<=',$lessThan],
                //['doc_freq','<',$freqMean + ($freqStdDev*1.5)],
            ])
            ->orderBy('count','desc')
            ->get();
        }
        /********** SEEING DOUBLE **********/
        // Drop terms containing a single letter or double numbers
        else if($filterName == 'Seeing Double')
        {
            $word_cloud_work = word_cloud_work::whereRaw(
                'LENGTH(term) > ? AND word_cloud_id = ? AND term NOT BETWEEN ? AND ?',[1,$wordcloud->id,00,99]
            )
            ->orderBy('computed_score','desc')
            ->get();
        }
        /********** ALGORITHMIC! **********/
        // Computed Score and drop > 1.5 std. devs. from median
        else if($filterName == 'Algorithmic!')
        {
            $lessThan = $freqMedian + $freqStdDev;
            $word_cloud_work = word_cloud_work::where([
                    ['word_cloud_id','=',$wordcloud->id],
                    ['doc_freq','<=',$lessThan],
                ])
                ->orderBy('computed_score','desc')
                ->get();
        }
        /********** NO FILTER **********/
        // Purely based on computed score
        else
        {
            // In addition to the $word_cloud variable (which currently stores metadata like user, from, to, etc)
            // we currently need to retrieve data from the work tables to display
            $word_cloud_work = word_cloud_work::where([
                ['word_cloud_id','=',$wordcloud->id],
                ])
                ->orderBy('computed_score','desc')
                ->get();
        }

        return $word_cloud_work;
    }

    /**
     *  This function will be used twice:
     * 
     *  - each time a WordCloud is viewed, this function returns all the terms+scores
     *    to be flattened and passed to wordcloud.show
     * 
     *  - when the wordcloud is first generated, this function returns all the terms+scores
     *    to be run through Phrase Analysis. 
     * 
     *  Needs to return a couple pieces of data for the colorString calculations:
     *  - array of adjusted Terms + Scores
     *  - upper threshold value (highest value we'll adjust up to)
     *  - highest (adjusted) score
     * 
     *  This function will return an array that looks like this:
     *      array $returnArray['termScores'][0-74] = arrTermScores[0] = $key + $value
     *                                               = arrTermScores[1] = $key + $value
     *                                               = arrTermScores[2] = $key + $value
     *            $returnArray['upperThreshold'] = $upperThreshold (float)
     *            $returnArray['highestAdjScore'] = $highestAdjScore (float)
     *            $returnArray['totalWordCount'] = $totalWordCount(int)
     * 
     */
    private function getTermScoreArrayToShowInCloud($wordcloud_id)
    {
        $wordcloud = word_cloud::find($wordcloud_id);

        $returnArray = [];

        /* DATASET STATISTICS */
        $termMean = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->avg('score');
        $termMedian = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->get()->median('score');
        $termStdDev = $this->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordcloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['score']]; })->all());

        // Freq statistics
        $freqMean = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->avg('doc_freq');
        $freqMedian = DB::table('word_cloud_work')->where('word_cloud_id','=',$wordcloud->id)->get()->median('doc_freq');
        $freqStdDev = $this->std_deviation(word_cloud_work::where('word_cloud_id','=',$wordcloud->id)->orderBy('computed_score','desc')->get()->mapWithKeys(function ($item) { return [$item['term'] => $item['doc_freq']]; })->all());

        // Grab the (filtered) dataset of word cloud terms
        $word_cloud_work = $this->getWordCloudWork($wordcloud->id);

        /********** WORD SCALING ***********/
        /**
         *  For Standard Deviant I'm shoe-horning in some logic that uses 'count' instead of 'computed_score' as
         *  the sort column. It makes for some hilarious clouds in people who swear a lot and I think it's worth
         *  this nasty mess of code.
         */

        /* ANYTHING BUT STANDARD DEVIANT */
        $lowestCompScore = $word_cloud_work->get(app_setting::wordCloudTermsPerCloud()+50)->computed_score;
        $highestCompScore = round($word_cloud_work->first()->computed_score,1);
        
        $word_cloud_array = $word_cloud_work
                                ->take(app_setting::wordCloudTermsPerCloud()+50)
                                ->mapWithKeys(function ($item) {
                                    return [$item['term'] => $item['computed_score']];
                                })
                                ->all();

        $scoreArray = $word_cloud_work->take(app_setting::wordCloudTermsPerCloud())
                                ->map(function ($item) {
                                    return $item['computed_score'];
                                })->all();

        $maxStdDevsFromMean = 1;
        $arrayMean = round($word_cloud_work->take(app_setting::wordCloudTermsPerCloud())->avg('computed_score'),1);
        $arrayMedian = round($word_cloud_work->take(app_setting::wordCloudTermsPerCloud())->median('computed_score'),1);
        
        /* STANDARD DEVIANT */
        if($wordcloud->word_cloud_filter == word_cloud_filter::getFilterId('Standard Deviant')) {

            $lowestCompScore = $word_cloud_work->get(app_setting::wordCloudTermsPerCloud()+50)->count;
            $highestCompScore = round($word_cloud_work->first()->count,1);
            
            $word_cloud_array = $word_cloud_work
                                    ->take(app_setting::wordCloudTermsPerCloud()+50)
                                    ->mapWithKeys(function ($item) {
                                        return [$item['term'] => $item['count']];
                                    })
                                    ->all();
    
            $scoreArray = $word_cloud_work->take(app_setting::wordCloudTermsPerCloud())
                                    ->map(function ($item) {
                                        return $item['count'];
                                    })->all();
    
            $maxStdDevsFromMean = 1;
            $arrayMean = round($word_cloud_work->take(app_setting::wordCloudTermsPerCloud())->avg('count'),1);
            $arrayMedian = round($word_cloud_work->take(app_setting::wordCloudTermsPerCloud())->median('count'),1);
        }

        $arrayStdDev = $this->std_deviation($scoreArray);
        $stdDevsFromMean = ($highestCompScore - $arrayMean) / $arrayStdDev;
        
        if($stdDevsFromMean > $maxStdDevsFromMean) {
            $highestCompScore = $maxStdDevsFromMean * $arrayStdDev + $arrayMean;
        }

        // Bring the computed_scores back down by setting the lowest to 1 and working up from there
        $counter = 0;
        $shortenedURLs = [];
        $highestAdjValue = 0;
        $upperThresholdPercent = 0.9;
        $upperThresholdArrayItem = round(app_setting::wordCloudTermsPerCloud()*$upperThresholdPercent,0);
        $upperThresholdArrayValue = 0;
        foreach($word_cloud_array as $key => $value) 
        {
            // We grabbed 50 extra terms to add some overhead for filters/removals, but we really
            // only want to put wordCloudTermsPerCloud into the string
            if($counter < app_setting::wordCloudTermsPerCloud())
            {
                $skipTerm = false;
    
                /*********** ADD'L TERM FILTERING ***********/
                /* Since we're iterating each term anyway to normalize the score/weight, we can
                 *  do some additional term filtering to get rid of undesirable terms
                /*********** ADD'L TERM FILTERING ***********/
    
                // Very long URL's (SEO-optimized stuff) looks terrible no matter the size, so shorten
                // them to their root. We don't want duplicate URL's displayed (user linked chattypics 10x)
                // so keep track of any shortened URL's and just drop them if they come up again later in
                // the process.
                if (filter_var($key, FILTER_VALIDATE_URL)) {
                    $key = parse_url($key, PHP_URL_HOST);
                    if(!in_array($key,$shortenedURLs)) {
                        $shortenedURLs[] = $key;
                    } else {
                        $skipTerm = TRUE;
                    }
                }

                $adjustedVal = round($value/$lowestCompScore,1);
                if($value > ($arrayMedian * 10)) {
                    $multiplier = ((($arrayMedian/$arrayMean) + ($arrayMean/$arrayStdDev))/2);
                    $adjustedVal = round(($value*$multiplier)/$lowestCompScore,1);
                    //$adjustedVal = round(($arrayMedian / $arrayMean) * $value/$lowestCompScore,1);
                }

                if($adjustedVal > $highestAdjValue) {
                    $highestAdjValue = $adjustedVal;
                }

                // Very long strings of a single character look like shit in word clouds. nwillard in
                // https://nullterminated.org/posts/37976434 is a good example. In that case, just
                // truncate the string to something reasonable. According to:
                // https://en.wikipedia.org/wiki/Longest_word_in_English we should be good truncating at 45.
                if(!$skipTerm) {
                    if(strlen($key) > 45)
                    {
                        $key = substr($key, 0, 45);
                    }
                }
                
                // While size is good, too much size is bad as it makes everything else tiny. I'm imposing an
                // arbitrary limit on how large the most popular terms can get. In some extreme cases (ELDERSVELD)
                // the Vue script almost chokes trying to render the hilariously large words.
                // I'm suspecting that if we cap the largest words to 6x the standard deviations from mean, they'll
                // still look good but fit more comfortably.
                if(!$skipTerm) {

                    // If we hit the upper-threshold term store the value to be used in the color computations below
                    if((app_setting::wordCloudTermsPerCloud()-$counter) == $upperThresholdArrayItem) {
                        $upperThresholdArrayValue = $adjustedVal;
                    }

                    // Add the term + score combo to the return array
                    $returnArray['termScores'][] = array(addslashes($key) => $adjustedVal);

                    $counter++;
                }
            }
        }

        $returnArray['upperThreshold'] = $upperThresholdArrayValue;
        $returnArray['highestAdjScore'] = $highestAdjValue;
        $returnArray['totalWordCount'] = count($word_cloud_work);

        return $returnArray;
    }

    /**
     *  Both the wordcloud.show and home views will be rendering a VueWordCloud component. That component requires
     *  two flattened text arrays: the text and the color. It makes sense to break the processing for these two
     *  strings out into separate functions and call them as needed.
     */
    public function generateWordCloudTextAndColorStrings($wordcloud_id)
    {
        $wordcloud = word_cloud::find($wordcloud_id);

        // All the heavy-lifting and calculations are handled in this function
        // Returns an array of 3 items (1 array, 2 values)
        $termsScoresCalcVals = $this->getTermScoreArrayToShowInCloud($wordcloud_id);
        $upperThresholdArrayValue = $termsScoresCalcVals['upperThreshold'];
        $highestAdjValue = $termsScoresCalcVals['highestAdjScore'];
        $totalWordCount = $termsScoresCalcVals['totalWordCount'];

        $counter = 0;
        $word_cloud_string = "[";
        foreach($termsScoresCalcVals['termScores'] as $termScore) 
        {
            foreach($termScore as $key => $value)
            {
                $word_cloud_string .= "['" . $key ."'," . $value . "]";
                if($counter < (app_setting::wordCloudTermsPerCloud()-1)) {
                    $word_cloud_string .= ",";
                }
            }
            $counter++;
        }
        $word_cloud_string .= "]";

        // Term weights aren't distributed evenly. 80% of the terms might be below 3 while 20% goes all the way up to 13. If
        // colors are distributed evenly, 80% will be one color and 20% with be 3 colors. Instead, stagger the colors so that
        // there are fewer distributed to the upper percent and more to the lower.
        //$arrayName = 'rainbow';
        // $colorArrays[$arrayName][0]

        $colorString = "(function (_ref) {  var weight = _ref[1];  return weight >= " . 
            $highestAdjValue * 1 . " ? '" . $wordcloud->colors()->where('sequence_num','=',0)->first()->color . "' : weight >= " . 
            ((($highestAdjValue - $upperThresholdArrayValue) * 0.75) + $upperThresholdArrayValue) . " ? '" . $wordcloud->colors()->where('sequence_num','=',1)->first()->color . "' : weight >= " . 
            ((($highestAdjValue - $upperThresholdArrayValue) * 0.50) + $upperThresholdArrayValue) . " ? '" . $wordcloud->colors()->where('sequence_num','=',2)->first()->color . "' : weight >= " . 
            ((($highestAdjValue - $upperThresholdArrayValue) * 0.25) + $upperThresholdArrayValue) . " ? '" . $wordcloud->colors()->where('sequence_num','=',3)->first()->color . "' : weight >= " . 
            ((($upperThresholdArrayValue - 1) * 0.9) + 1) . " ? '" . $wordcloud->colors()->where('sequence_num','=',4)->first()->color . "' : weight >= " . 
            ((($upperThresholdArrayValue - 1) * 0.73) + 1) . " ? '" . $wordcloud->colors()->where('sequence_num','=',5)->first()->color . "' : weight >= " . 
            ((($upperThresholdArrayValue - 1) * 0.57) + 1) . " ? '" . $wordcloud->colors()->where('sequence_num','=',6)->first()->color . "' : weight >= " . 
            ((($upperThresholdArrayValue - 1) * 0.40) + 1) . " ? '" . $wordcloud->colors()->where('sequence_num','=',7)->first()->color . "' : weight >= " . 
            ((($upperThresholdArrayValue - 1) * 0.23) + 1) . " ? '" . $wordcloud->colors()->where('sequence_num','=',8)->first()->color . "' : '" . 
            $wordcloud->colors()->where('sequence_num','=',9)->first()->color . "';})";

        $returnArr = [];
        $returnArr += array("word_cloud_string" => $word_cloud_string);
        $returnArr += array("color_string" => $colorString);
        $returnArr += array("totalWordCount" => $totalWordCount);
        
        return $returnArr;
    }

    /**
     * I don't want to install all the PEAR package manager and PECL just to get access to standard deviation
     * function, so copying this guy's function instead:
     * 
     * https://stackoverflow.com/questions/21885150/stats-standard-deviation-in-php-5-2-13
     * https://www.geeksforgeeks.org/php-program-find-standard-deviation-array/
     */

    public function std_deviation($arr) {
        $num_of_elements = count($arr);
        
        $array = [];
        foreach($arr as $key => $value) {
            $array[] = (float)$value;
        }
         
        $variance = 0.0;
        // calculating mean using array_sum() method
        $average = array_sum($array)/$num_of_elements;
         
        foreach($array as $i)
        {
            // sum of squares of differences between 
                        // all numbers and means.
            $variance += pow(($i - $average), 2);
        }
         
        return (float)sqrt($variance/$num_of_elements);
    }


    /**
     * The POSTS page displays a graphical representation of post data in the database.
     * It does a COUNT(*) WHERE statement for each 100k block of post ID's, which has become
     * progressively slower as the database is populated. As a result, the page will often
     * cause nginx 504 timeouts when accessed. By counting posts on a scheduled frequency, we
     * can display near-realtime totals almost instantly.
     */
    public function countPosts()
    {
        if(app_setting::postCountEnabled())
        {
            // It'd be nice to see a total count in the logs
            $totalCounted = 0;

            for($hunThou=0; $hunThou < app_setting::postCountTotal(); $hunThou += app_setting::postCountBracketSize() )
            {
                // bracketCount = valid posts in DB
                // excludedCount = posts that numerically should exist but couldn't be downloaded from Winchatty (nuked most likely)
                $bracketCount = DB::table('posts')->whereBetween('id',[$hunThou, $hunThou + app_setting::postCountBracketSize()])->count();
                $excludedCount = DB::table('mass_sync_results')->whereBetween('post_id',[$hunThou,$hunThou + app_setting::postCountBracketSize()-1])->count();
                $nukedCount = DB::table('posts')->whereBetween('id',[$hunThou, $hunThou + app_setting::postCountBracketSize()])->where('category','=',7)->count();
                $indexedCount = DB::table('posts')->whereBetween('id',[$hunThou, $hunThou + app_setting::postCountBracketSize()])->where('indexed','=',true)->count();

                $totalCounted += $bracketCount;

                // Try to find an existing post_count in DB for this post bracket, and create one if not
                $postCount = post_count::find($hunThou);
                if(!($postCount)) {
                    $postCount = new post_count();
                    $postCount->block_id = $hunThou;
                } 
                $postCount->count = $bracketCount;
                $postCount->excluded = $excludedCount;
                $postCount->search_indexed = $indexedCount;
                $postCount->nuked = $nukedCount;
                // Total Post Count can be set quite high but there might not actually be any rows in posts_to_download
                // up to that number. If so, we're waiting on events to populate the posts, not the Mass Sync process.
                // Those squares should appear as 0%.
                $ptdl = DB::select('SELECT count(id) FROM posts_to_download WHERE id BETWEEN :startid AND :endid',
                    ['startid' => $hunThou, 'endid' => $hunThou + app_setting::postCountBracketSize()-1]);
                $ptdlCount = $ptdl[0]->count;
                $msr = DB::select('SELECT count(post_id) FROM mass_sync_results WHERE post_id BETWEEN :startid AND :endid',
                    ['startid' => $hunThou, 'endid' => $hunThou + app_setting::postCountBracketSize()-1]);
                $msrCount = $msr[0]->count;

                // This block is a far-future block that should appear 0% red
                if($ptdlCount == 0 && $msrCount == 0) {
                    $postCount->percent = 0;
                }
                // This block is a near-future/past block that should be calculated
                else 
                {
                    // If an entire block is missing (not 1 post), we'll get a divide-by-zero error
                    $missingPosts = DB::select('SELECT count(id) FROM posts_to_download 
                    WHERE id BETWEEN :startid AND :endid 
                    AND id NOT IN (SELECT post_id FROM mass_sync_results WHERE post_id BETWEEN :startid AND :endid) 
                    AND id NOT IN (SELECT id FROM posts WHERE id BETWEEN :startid AND :endid)',
                    ['startid' => $hunThou, 'endid' => $hunThou + app_setting::postCountBracketSize()-1]);

                    if($missingPosts[0]->count == 0) {
                        $postCount->percent = 100;
                    } else {
                        $postCount->percent = round(($bracketCount/(app_setting::postCountBracketSize()-$excludedCount))*100,0);
                    }  
                }
                $postCount->save();
            }

            // There is a timestamp in app_settings that records the last time this job ran
            $appSettings = app_setting::find(1);
            $appSettings->post_count_last_run = Carbon::now();
            $appSettings->save();

            // Technically the PostCount user doesn't even need to log in and do anything but I'd still like to see the dbAction logs
            // under that ID for filtering and sorting
            if(app_setting::loggingLevel() >= 3)
            {
                $db_action = new dbAction();
                $db_action->username = app_setting::postCountUsername();
                $db_action->message = 'Successfully updated post_counts for ' . $totalCounted . ' posts.';
                $db_action->save();
            }
        }
    }

    /**
     * 
     */
    public function downloadPostsFromWinchatty()
    {
        if(app_setting::massPostSyncEnabled()) 
        {
            $message = NULL;
            $messageArr = [];

            // This block of SQL will actually work to generate our random IDs and exclude already processed ones
            /*
            SELECT * FROM (SELECT trunc(random() * (400000 - 300000) + 300000) as new_id FROM generate_series(1,10)) AS x WHERE x.new_id NOT IN (SELECT post_id FROM mass_sync_results) AND x.new_id NOT IN (SELECT id FROM posts);
            
            SELECT * FROM (SELECT trunc(random() * (:upper_bound - :lower_bound) + :lower_bound) as new_id FROM generate_series(1,:num_in_series)) AS x WHERE x.new_id NOT IN (SELECT post_id FROM mass_sync_results) AND x.new_id NOT IN (SELECT id FROM posts); ['upper_bound' => 400000,'lower_bound' => 300000,'num_in_series' => 10]
            $results = DB::select('SELECT * FROM (SELECT trunc(random() * (:upper_bound - :lower_bound) + :lower_bound) as new_id FROM generate_series(1,:num_in_series)) AS x WHERE x.new_id NOT IN (SELECT post_id FROM mass_sync_results) AND x.new_id NOT IN (SELECT id FROM posts), ['upper_bound' => 400000,'lower_bound' => 300000,'num_in_series' => 10]);
            */

            // Function works in blocks of 100k - grab the current block and add 99,999 (total = 100k incl. root)
            $blockStart = app_setting::workingBlock();
            $blockEnd = ($blockStart + (app_setting::postCountBracketSize()-1));

            // Use SQL to generate the random post ID's, that way existing and attempted posts are automatically excluded
            //$randomIds = DB::select('SELECT x.new_id FROM (SELECT trunc(random() * (:upper_bound::INT - :lower_bound::INT) + :lower_bound::INT) as new_id FROM generate_series(1,:num_in_series::INT)) AS x WHERE x.new_id NOT IN (SELECT post_id FROM mass_sync_results) AND x.new_id NOT IN (SELECT id FROM posts)', ['upper_bound' => $blockEnd,'lower_bound' => $blockStart,'num_in_series' => app_setting::threadsToRetrieve()]);
            $randomIds = DB::select('SELECT id FROM posts_to_download WHERE id BETWEEN :lower_bound::INT AND :upper_bound::INT ORDER BY random() LIMIT :limit::INT', ['lower_bound' => $blockStart,'upper_bound' => $blockEnd,'limit' => app_setting::threadsToRetrieve()]);
    
            // Ensure the process hasn't downloaded all posts in the block.
            if(count($randomIds) > 0)
            {
                // Query results were returned in an array of stdClass (x objects with ->new_id)
                $idArray = [];
                foreach($randomIds as $randomId)
                {
                    $idArray[] = $randomId->id;
                    DB::delete('DELETE FROM posts_to_download WHERE id = :id::INT',['id' => $randomId->id]);
                }

                // The importThreadFromWinchatty function will accept a comma-delimited string of IDs
                $messageArr = $this->importThreadFromWinchatty(implode(",",$idArray),app_setting::massSyncUsername());
            }
            // If so, decide whether to log and do nothing, or advance to the next working block
            else
            {
                if(app_setting::massPostSyncAutoBlockAdvance())
                {
                    /* The auto-advance will either go down or up a block, depending on what is set in app_settings */
                    if(app_setting::advanceDesc()) {
                        // Ensure advancing downwards won't take us past the stop post
                        if(app_setting::workingBlock() - app_setting::postCountBracketSize() > app_setting::stopPost()) {
                            $settings = app_setting::find(1);
                            $oldWorkingBlock = $settings->mass_sync_working_block;
                            $newWorkingBlock = $oldWorkingBlock - app_setting::postCountBracketSize();
                            $settings->mass_sync_working_block = $newWorkingBlock;
                            $settings->save();
                            $message = 'Updated mass sync working block from ' . $oldWorkingBlock . ' to ' . $newWorkingBlock . '.';
                        } else {
                            $message = 'Decrementing by ' . app_setting::postCountBracketSize() . ' will go past stop post ' . app_setting::stopPost() . '. Please disable the mass sync process.';
                        }
                    } else {
                        // Ensure advancing upwards won't take us past the stop post
                        if(app_setting::workingBlock() + app_setting::postCountBracketSize() < app_setting::stopPost()) {
                            $settings = app_setting::find(1);
                            $oldWorkingBlock = $settings->mass_sync_working_block;
                            $newWorkingBlock = $oldWorkingBlock + app_setting::postCountBracketSize();
                            $settings->mass_sync_working_block = $newWorkingBlock;
                            $settings->save();
                            $message = 'Updated mass sync working block from ' . $oldWorkingBlock . ' to ' . $newWorkingBlock . '.';
                        } else {
                            $message = 'Incrementing by ' . app_setting::postCountBracketSize() . ' will go past stop post ' . app_setting::stopPost() . '. Please disable the mass sync process.';
                        }
                    }
                } 
                else 
                {
                    $message = 'All posts in block processed. Please change block or pause/cancel automatic sync process. No actions taken.';
                }

                $db_action = new dbAction();
                $db_action->username = app_setting::massSyncUsername();
                $db_action->message = $message;
                $db_action->save();
                $messageArr[] = $message;
            }

            $appSetting = app_setting::find(1);
            $appSetting->mass_sync_last_sync_run = Carbon::now();
            $appSetting->save();

            return $messageArr;
        }
    }

    /**
     *  Monitor:Events
     * 
     *  If Event Polling from WinChatty is enabled and supposed to be running, verify
     *  that it actually IS and notify if things have become stuck.
     */
    public function monitorEventPolling($eventMonitorName) 
    {
        $message = NULL;
        $messageArr = [];

        // For Event Polling, the last time the job ran is stored in App Settings
        $appsettings = app_setting::find(1);
        $lastEventPoll = $appsettings->last_event_poll;
        $monitorUsername = $appsettings->monitor_username;

        // Ensure the specified monitor actually exists
        if(monitor::where('name','=',$eventMonitorName)->exists()) {
            $monitor = monitor::where('name','=',$eventMonitorName)->first();

            // Don't proceed unless the monitor is enabled
            if($monitor->enabled) {
                // If the Event Poll timestamp is older than this many minutes, we have an issue. 
                $minThreshold = $monitor->max_mins_since_task_last_exec;
                $lastEventPoll = date_create_from_format('Y-m-d H:i:s', app_setting::lastEventPoll());
                $dateDifference = date_diff($lastEventPoll,Carbon::now());

                if($dateDifference->i > $minThreshold) {
                    //Red Alert, all crew to battlestations.
                    $monitor->last_run_alert_state = true;

                    // At the *moment* I'm only sending a single email instead of one every 4 hours or whatever.
                    // Hopefully I'll catch it in my inbox. If this doesn't work I'll come back and add multiple attempts.
                    if(!$monitor->last_run_email_sent) {
                        Mail::to(User::where('name','=',$monitorUsername)->first())->send(new MonitorEventPoll($monitor));
                        $monitor->last_run_email_sent = true;

                        $message = 'Monitor ' . $eventMonitorName . ' was triggered and email has been sent to user ' . $monitorUsername . '.';
                        $messageArr["Warning"][] = $message;
                    } else {
                        $message = 'Monitor ' . $eventMonitorName . ' was triggered. Email sent previously, no further action taken.';
                        $messageArr["Warning"][] = $message;
                    }
                } else {
                    // All clear on the Western front
                    $monitor->last_run_alert_state = false;
                    $monitor->last_run_email_sent = false;
                    $message = 'Monitor ' . $eventMonitorName . ' ran successfully and no error condition was found.';
                    $messageArr["Success"][] = $message;
                }

                $monitor->last_run = Carbon::now();
                $monitor->save();

            } else {
                $message = 'Monitor ' . $eventMonitorName . ' is not enabled. Please enable it or cancel scheduled task.';
                $messageArr["Warning"][] = $message;
            }
            
        } else {
            $message = 'Monitor ' . $eventMonitorName . ' was not found. Please update scheduled job with correct monitor name.';
            $messageArr["Error"][] = $message;
        }

        // For successful monitoring, only write a log if we have trace level 4+. Otherwise if there is an issue, always log it.
        if($monitor->last_run_alert_state == false && app_setting::loggingLevel() >= 4) {
            $db_action = new dbAction();
            $db_action->username = $monitorUsername;
            $db_action->message = $message;
            $db_action->save();
        } else {
            $db_action = new dbAction();
            $db_action->username = $monitorUsername;
            $db_action->message = $message;
            $db_action->save();
        }
        
        return $messageArr;
    }
}