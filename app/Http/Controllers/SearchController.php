<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;

use Carbon\Carbon;
use Auth;
use DB;

use App\Chatty\post;
use App\Chatty\app_setting;
use App\Chatty\dbAction;
use App\Chatty\ElasticSearch;
use App\Chatty\search_history;
use App\Chatty\postcategory;

use Elasticsearch\Client;

use App\Chatty\Contracts\ChattyContract;
use App\Chatty\Contracts\SearchContract;

class SearchController extends Controller
{
    private $chatty;
    private $search;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(ChattyContract $chatty, SearchContract $search)
    {
        $this->chatty = $chatty;
        $this->search = $search;
        //$this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //$this->authorize('search',ElasticSearch::class);

        // To record how long queries are taking in the search_history record, take an initial
        // and final timestamp
        $queryStart = date_create();

        // We may receive date info that has been entered free-form by the user, so
        // validate that it's a real date and in the proper format.
        $validator = \Validator::make($request->all(), [
            'author' => 'max:255',
            'from' => 'date_format:Y-m-d',
            'to' => 'date_format:Y-m-d|after_or_equal:from',
        ]);

        if($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator);
        }

        $searchResults = NULL;

        // The Blade template uses a few app_setting variables so pass it an instance to query with
        $appsettings = app_setting::find(1);

        // In the Blade template we'll use Carbon to do a nice DateDiff on this and display the time
        // since the last sync ran
        $lastSearchCrawl = app_setting::lastSearchCrawl();

        // Retrieve the easy URL parameters
        $searchBody = $request->input('body');
        $searchAuthor = $request->input('author');
        $rootPostOnly = false;
        if($request->has('rootposts'))
        {
            $rootPostOnly = true;
        }

        /**
         * Seems like every device submits a different quote type. iPhone's for sure use "smart quotes"
         * which are different than standard unicode apostrophes and double-quotes. Before attempting to look
         * for quotes, first replace all instances of smart quotes with two standards:
         * U+0022 quotation mark (")
         * U+0027 apostrophe (')
         * 
         * https://stackoverflow.com/questions/20025030/convert-all-types-of-smart-quotes-with-php
         */
        $chr_map = array(
            // Windows codepage 1252
            "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
            "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
            "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
            "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
            "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
            "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
            "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
            "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark
            
            // Regular Unicode     // U+0022 quotation mark (")
                                    // U+0027 apostrophe     (')
            "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
            "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
            "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
            "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
            "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
            "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
            "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
            "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
            "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
            "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
            "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
            "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
            );
        $chr = array_keys  ($chr_map); // but: for efficiency you should
        $rpl = array_values($chr_map); // pre-calculate these two arrays
        $searchBody = str_replace($chr, $rpl, html_entity_decode($searchBody, ENT_QUOTES, "UTF-8"));

        // Test whether the user submitted a quoted string - if they did set the search engine to
        // "simple query" which will use the quoted_strings property to do an exact text search.
        $useSimpleQueryEngine = false;
        $start = 0;
        $charToFind = "\"";
        $count = 0;
        while(($pos = strpos(($searchBody),$charToFind,$start)) !== FALSE) {
            $count++;
            $start = $pos+1;
        }
        if(($count > 0) && ($count % 2) == 0) {
            $useSimpleQueryEngine = true;
        }        

        // Do the date math for query range
        $from = date('1901-01-01');
        $to = date("Y-m-d");

        switch($request->input('daterange')) {
            case 'lastweek':
                $from = date("Y-m-d", strtotime("-7 days"));
                break;
            case 'lastmonth':
                $from = date("Y-m-d", strtotime("-1 month"));
                break;
            case 'lastyear':
                $from = date("Y-m-d", strtotime("-1 year"));
                break;
            // All-time is handled above by setting date to 1901-01-01
            case 'custom':
                // These dates have been confirmed valid by the Validator
                $from = date("Y-m-d", strtotime($request->input('from')));
                $to = date("Y-m-d", strtotime($request->input('to')));
                break;
        }
        
        // Grab the query engine that the user selected
        switch($request->input('engine')) {
            case 'simplequery':
                $queryEngine = 'simple';
                break;
            case 'match':
                $queryEngine = 'match';
                break;
            case 'common':
                $queryEngine = 'common';
                break;
            default:
                $queryEngine = 'match';
                break;
        }

        // Grab the link target that the user selected
        $linkTarget = null;
        switch($request->input('linktarget')) {
            case 'local':
                $linkTarget = 'local';
                break;
            case 'shacknews':
                $linkTarget = 'shacknews';
                break;
            default:
                $linkTarget = 'local';
                break;
        }

        // If the use submitted a quoted string, override their selection
        if($useSimpleQueryEngine) {
            $queryEngine = 'simple';
        }

        // This is ugly but pretty low on the priority scale of code that needs cleaned up
        if($searchBody) {
            // Match
            if($queryEngine == 'match') {
                $postAndSuggArr = $this->search->matchQueryWithSuggestions($searchBody,$searchAuthor,$rootPostOnly,$from,$to);
            // Common terms 
            } else if($queryEngine == 'common') {
                $postAndSuggArr = $this->search->commonTermsQueryWithSuggestions($searchBody,$searchAuthor,$rootPostOnly,$from,$to);
            // Simple query string    
            } else {
                $postAndSuggArr = $this->search->simpleQueryStringWithSuggestions($searchBody,$searchAuthor,$rootPostOnly,$from,$to);
            }
            $resultCount = count($postAndSuggArr['postIdsAndScores']);
        } else {
            $postAndSuggArr = NULL;
            $resultCount = 0;
        }

        /* Posts that have more replies were (in one way or another) more intriguing to people and caused more interaction. They're
            the ones most likely to stick in people's minds and be searched for. They're probably also more popular and well-known
            than one-off posts with no replies at all. Adding a slight boost to heavily-replied posts might yield more useful results
            than simply letting elastic boost them based off of post length (which is the primary source of boost right now). */

        /* Unfortunately, boosting by score is possible only if Elastic has a count of post children. Doing that would require:
            - updating parent count each time a new post event is received
            - trying to calculate child count during mass downloads when posts are not imported sequentially
            - re-submitting a post to Elastic each time a reply is received to it, causing a new version to be created
        
            Alternatively, a single Eloquent query could tell us in real-time how many children a post has. This has the disadvantage 
            that if the post the user was searching for was not returned in the initial 1000 results, it won't be boosted. Though really,
            if it didn't come back in 1000 results, maybe the query needs rethought. */
        $replyBoost = true;
        if($resultCount > 0 && $replyBoost)
        {
            $childBoost = 0.001;
            foreach($postAndSuggArr['postIdsAndScores'] as $id => $score)
            {
                $post = post::find($id);
                $children = $post->children()->count();
                $postAndSuggArr['postIdsAndScores'][$id] += $childBoost * $children;
                //if($children > 10) {
                //    dd('For ' . $id . ' original score was ' . $score . '. Found ' . $children . ' and updated score to ' . $postAndSuggArr['postIdsAndScores'][$id]);
                //}
            }
        }

        // Laravel wants to reorder the results when using whereIn, so we get around it like this: 
        // https://stackoverflow.com/questions/26704575/laravel-order-by-where-in?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
        if(isset($postAndSuggArr)) {
            arsort($postAndSuggArr['postIdsAndScores']);
            $ids_ordered = implode(',',array_keys($postAndSuggArr['postIdsAndScores']));
        }
        
        // Calculate a progress percentage of indexed vs. total posts
        $totalPostsInDB = DB::table('post_counts')->sum('count');
        $excludedPosts = DB::table('post_counts')->sum('excluded');
        $nukedPosts = DB::table('post_counts')->sum('nuked');
        $indexedPosts = DB::table('post_counts')->sum('search_indexed');
        //$oldestIndexed = post::where('indexed','true')->oldest('date')->first();

        $indexStats = [];
        $indexStats['totalPostsToIndex'] = $totalPostsInDB - $nukedPosts;
        $indexStats['indexedPostCount'] = $indexedPosts;
        $indexStats['percentIndexed'] = round(($indexedPosts/($totalPostsInDB - $nukedPosts))*100,2);

        // I left the author field in Elastic set as text to be analyzed. The analyzer tokenized all the names with spaces
        // so trying to filter on "the man with the briefcase" returns "the city" and "the grolar bear". I could rebuild the
        // index but re-indexing 30M posts seems kind of crappy. Alternatively, I could cheap out by limiting the Laravel
        // collection (since we're gathering it below anyways). Brutal, I know.
        $filterAuthor = false;
        if(trim($searchAuthor) <> '') {
            $filterAuthor = true;
        }

        // ElasticSearch results currently set to return an array of Post ID's. We can query those and
        // build a paginated list to show the user.
        $sortOrder = $request->input('sort');
        if($resultCount > 0) {

            // It's 21:24, I just want to play Overwatch and the kids are finally asleep. I don't care how ugly this code is
            // so long as it works.
            if($filterAuthor) {
                switch($sortOrder) {
                    case 'desc':
                        $searchResults = post::with('post_lols')
                            ->whereIn('id',array_keys($postAndSuggArr['postIdsAndScores']))
                            ->where('author_c','ILIKE',trim($searchAuthor))
                            ->orderBy('date','desc')
                            ->paginate(app_setting::searchResultsPerPage());
                        break;
                    case 'asc':
                        $searchResults = post::with('post_lols')
                            ->whereIn('id',array_keys($postAndSuggArr['postIdsAndScores']))
                            ->where('author_c','ILIKE',trim($searchAuthor))
                            ->orderBy('date','asc')
                            ->paginate(app_setting::searchResultsPerPage());
                        break;
                    default:
                        $searchResults = post::with('post_lols')
                            ->whereIn('id',array_keys($postAndSuggArr['postIdsAndScores']))
                            ->where('author_c','ILIKE',trim($searchAuthor))
                            ->orderByRaw(DB::raw("position(id::text in '($ids_ordered)')"))
                            ->paginate(app_setting::searchResultsPerPage());
                        break;
                }
            } else {
                // User can set sort order preferences. I don't know of a nice way to swap out the orderBy statement in an Eloquent command,
                // so I'm just going to issue different queries based on what they selected. Bite me.
                switch($sortOrder) {
                    case 'desc':
                        $searchResults = post::with('post_lols')
                            ->whereIn('id',array_keys($postAndSuggArr['postIdsAndScores']))
                            ->orderBy('date','desc')
                            ->paginate(app_setting::searchResultsPerPage());
                        break;
                    case 'asc':
                        $searchResults = post::with('post_lols')
                            ->whereIn('id',array_keys($postAndSuggArr['postIdsAndScores']))
                            ->orderBy('date','asc')
                            ->paginate(app_setting::searchResultsPerPage());
                        break;
                    default:
                        $searchResults = post::with('post_lols')
                            ->whereIn('id',array_keys($postAndSuggArr['postIdsAndScores']))
                            ->orderByRaw(DB::raw("position(id::text in '($ids_ordered)')"))
                            ->paginate(app_setting::searchResultsPerPage());
                        break;
                }
            }
            
        }

        // For recording performance statistics for queries
        $elapsedSecs = date_diff($queryStart,date_create())->s;

        // Finally, create a search_history entry so that I can track search engine usage stats. Only
        // record visits to /search if there are actual results to display, otherwise people are just
        // accessing the page for the first time
        if($resultCount > 0) {
            $searchHist = new search_history();
            // People can search without being logged in, so grab their username only if it's possible
            if(Auth::check()) {
                $searchHist->user = Auth::user()->name;
            }
            // This IP comes from the request, not the HTTP headers, so people behind load-balancers and
            // such will all appear with the same IP. That's fine, this is just a best guess so that we
            // have *something* to try and identify spammers/etc by.
            $searchHist->ip = $request->ip();
            // Author is an optional filter field so make sure they actually submitted something first
            if(strlen(trim($searchAuthor)) > 0) {
                $searchHist->author = trim($searchAuthor);
            }
            $searchHist->text = $searchBody;
            $searchHist->from = $from;
            $searchHist->to = $to;
            $searchHist->root_posts = $rootPostOnly;
            $searchHist->engine = $queryEngine;
            $searchHist->link_target = $linkTarget;
            $searchHist->sort = $sortOrder;
            $searchHist->seconds = $elapsedSecs;
            $searchHist->save();
        }
    
        return \View::make('search.index')
            ->with('searchResults',$searchResults)
            ->with('idsAndScores',$postAndSuggArr['postIdsAndScores'])
            ->with('suggestions',$postAndSuggArr['suggestions'])
            ->with('resultCount',$resultCount)
            ->with('lastSearchCrawl',$lastSearchCrawl)
            ->with('indexStats',$indexStats)
            ->with('linkTarget',$linkTarget)
            ->with('appsettings',$appsettings);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $messageArr = [];
        $message = NULL;
        $logLevel = 3;

        // MANUAL POST INDEX REQUESTED
        if($request->has('manualIndex'))
        {
            $result = null;

            // Ensures user is authorized to be submitting Search admin functionality requests
            $this->authorize('submitIndex',ElasticSearch::class);

            // Find the model based on the ID they requested
            $postToIndex = post::find($request->input('manualIndexPostID'));

            // Make sure a post was found for the submitted ID
            if($postToIndex)
            {
                // Ensure the post isn't a nuked one
                if($postToIndex->category != postcategory::categoryId('nuked'))
                {
                    // Use our existing functionality to perform the operation. All logging and messaging
                    // is handled by this call - simply display the result to the user.
                    $messageArr = $this->chatty->submitPostsForSearchIndexing(array($postToIndex));
                }
                else
                {
                    $message = 'Nuked posts may not be submitted for indexing. Indexing operation cancelled.';
                    $messageArr[] = $message;
                    if(app_setting::loggingLevel() >= 4)
                    {
                        $db_action = new dbAction();
                        $db_action->username = Auth::user()->name;
                        $db_action->message = $message;
                        $db_action->save();
                    }
                }
            }
            else
            {
                $message = 'No post found for Post ID: ' . $request->input('manualIndexPostID') . '. Indexing operation cancelled.';
                $messageArr[] = $message;
                if(app_setting::loggingLevel() >= 5)
                {
                    $db_action = new dbAction();
                    $db_action->username = Auth::user()->name;
                    $db_action->message = $message;
                    $db_action->save();
                }
            }

            return back()->with('messageIds',$messageArr);
        }
        // SEARCH CRAWLER SETTINGS
        else if($request->has('searchCrawlerSettings'))
        {
            $settings = app_setting::find(1);
            $automaticCrawling = FALSE;

            // We're working with two separate models here (app_settings and search indexing) so
            // instead of using controller authorization I'm using user authorization
            // depending on function
            if(Auth::user()->can('update',$settings))
            {
                $validator = \Validator::make($request->all(), [
                    'postsToIndex' => 'required|numeric|between:1,100000',
                    'indexBatchSize' => 'required|numeric|between:1,100000',
                    'crawlerUsername' => 'required|string|min:1|max:255',
                    'searchResultsPerPage' => 'required|numeric|between:0,500',
                    'totalSearchResults' => 'required|numeric|between:0,5000',
                ]);

                if($validator->fails()) {
                    return back()
                        ->withInput()
                        ->withErrors($validator);
                }

                $settings->search_crawler_posts_to_index = $request->input('postsToIndex');
                $settings->search_crawler_batch_size = $request->input('indexBatchSize');
                $settings->search_crawler_username = $request->input('crawlerUsername');
                $settings->num_search_results_per_page = $request->input('searchResultsPerPage');
                $settings->elastic_max_results = $request->input('totalSearchResults');
                if($request->has('searchCrawlerEnabled')) {
                    $settings->search_crawler_enabled = TRUE;
                    $automaticCrawling = TRUE;
                } else {
                    $settings->search_crawler_enabled = FALSE;
                }
                $settings->save();

                $messageArr[] = 'Posts to index: ' . $request->input('postsToIndex');
                $messageArr[] = 'Index Batch Size: ' . $request->input('indexBatchSize');
                $messageArr[] = 'Crawler Username: ' . $request->input('crawlerUsername');
                $messageArr[] = 'Search Results per Page: ' . $request->input('searchResultsPerPage');
                $messageArr[] = 'Max Search Results: ' . $request->input('totalSearchResults');
                $messageArr[] = 'Automatic Post Indexing: ' . ($automaticCrawling ? 'true' : 'false');

                $message = 'Successfully updated app_settings. Posts to index: ' . 
                    $request->input('postsToIndex') . '. Index Batch Size: ' .
                    $request->input('indexBatchSize') . '. Crawler Username: ' .
                    $request->input('crawlerUsername') . '. Search Results per Page: ' .
                    $request->input('searchResultsPerPage') . ' Max Search Results: ' .
                    $request->input('totalSearchResults') . '. Automatic Post Indexing: ' .
                    ($automaticCrawling ? 'true' : 'false') . '.';

                $db_action = new dbAction();
                $db_action->username = Auth::user()->name;
                $db_action->message = $message;
                $db_action->save();

                return back()->with('messageIds',$messageArr);
            } else {
                // Return the user with an unauthorized error
                abort(403,'This action is unauthorized.');
            }
        }
        // SEARCH INDEX SETTINGS
        else if($request->has('searchAdministrationSettings'))
        {
            $settings = app_setting::find(1);

            // We're working with two separate models here (app_settings and search indexing) so
            // instead of using controller authorization I'm using user authorization
            // depending on function
            if(Auth::user()->can('update',$settings))
            {
                $validator = \Validator::make($request->all(), [
                    'elasticPostsIndexName' => 'required|string|between:0,255',
                    'elasticPostsIndexType' => 'required|string|between:0,255',
                ]);

                if($validator->fails()) {
                    return back()
                        ->withInput()
                        ->withErrors($validator);
                }

                $settings->elastic_post_search_index = $request->input('elasticPostsIndexName');
                $settings->elastic_post_type = $request->input('elasticPostsIndexType');
                $settings->save();

                $messageArr[] = 'Post Index Name: ' . $request->input('elasticPostsIndexName');
                $messageArr[] = 'Post Index Type: ' . $request->input('elasticPostsIndexType');

                $message = 'Successfully updated app_settings. Post Index Name: ' .
                    $request->input('elasticPostsIndexName') . '. Post Index Type: ' .
                    $request->input('elasticPostsIndexType') . '.';
                
                $db_action = new dbAction();
                $db_action->username = Auth::user()->name;
                $db_action->message = $message;
                $db_action->save();

                return back()->with('messageIds',$messageArr);

            } else {
                // Return the user with an unauthorized error
                abort(403,'This action is unauthorized.');
            }

            
        }
        // SINGLE INDEX BATCH REQUESTED
        else if($request->has('manualBatchIndex'))
        {
            // Ensures user is authorized to be submitting Search admin functionality requests
            $this->authorize('submitIndex',ElasticSearch::class);

            // User might override default date with their own, so validate it
            $validator = \Validator::make($request->all(), [
                'singleBatchDate' => 'date_format:Y-m-d'
            ]);

            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            // Retrieve Illuminate\Support\Collection of posts
            $postsToIndex = post::where([
                    ['indexed','false'],
                    ['category','<>',7],
                    ['date','<=',$request->input('singleBatchDate')]
                ])->orderBy('date','desc')
                ->limit(app_setting::postsToIndex())
                ->get();

            // Pass collection to Chatty class, which will pass the collection to Elastic to be indexed,
            // then collect the responses and mark all appropriate posts as indexed.
            $messageArr = $this->chatty->submitPostsForSearchIndexing($postsToIndex,Auth::user()->name);
            
            return back()->with('messageIds',$messageArr);
        }
        // CREATE POST INDEX IN ELASTIC
        else if($request->has('createPostsIndex'))
        {
            // Ensures user is authorized to be submitting Search admin functionality requests
            $this->authorize('submitIndex',ElasticSearch::class);

            $messageArr = $this->chatty->createPostsIndexInElastic();

            return back()->with('messageIds',$messageArr);
        }
        // AUTHOR WORD COUNT
        else if($request->has('countAuthorWords'))
        {
            // Ensure user is authorized for this action
            $this->authorize('submitIndex',ElasticSearch::class);

            // Could return Success/Warning/Error messages via the Session variable, so store everything for display
            $result = $this->search->termVectorsForAuthorWithScore($request->input('authorUsername'));
            $resArr = json_decode($result,true);

            $termArr = [];
            $successPostCount = 0;
            $errorPostCount = 0;
    
            // Make sure 'docs' exists - if not, something went wrong
            if(!array_key_exists('docs',$resArr)) {
                $messageArr["error"][] = 'Elastic response missing docs node. Please try again.';
            } else {
                // Docs was found, so start iterating the results. Each is self-contained.
                foreach($resArr["docs"] as $post)
                {
                    // Make sure result doesn't contain an error - log it if so, and move on to the next record
                    if(array_key_exists('error',$post))
                    {
                        $errorPostCount += 1;
                        //$messageArr["error"][] = 'Error getting terms for Post ID: ' . $post["_id"] . '. ' . $post["error"]["type"] . '. ' . $post["error"]["reason"] . '. ' . $post["error"]["caused_by"]["type"] . '.';
                    } else {
                        // No errors encountered, so we can continue 
                        if(array_key_exists("term_vectors",$post)) 
                        {
                            // Update the counter so we can display how many posts were used in the cloud
                            $successPostCount += 1;
    
                            foreach($post["term_vectors"]["body.mterms"]["terms"] as $term => $arr)
                            {
                                $termArr += [$term => $arr["score"]];
                            }
                        }
                    }
                }
            }
            arsort($termArr);
            dd($termArr);

            // TODO: clean this up with a trait so we don't repeat this code every time
            if(array_key_exists('error', $messageArr)) {
                $request->session()->flash('error', $messageArr["error"]);
            }
            if(array_key_exists('warning', $messageArr)) {
                $request->session()->flash('warning', $messageArr["warning"]);
            }
            if(array_key_exists('success', $messageArr)) {
                $request->session()->flash('success', $messageArr["success"]);
            }

            return back();
        }
        // POST WORD COUNT
        else if($request->has('countPostTerms'))
        {
            // Ensure user is authorized for this action
            $this->authorize('submitIndex', ElasticSearch::class);

            // Ensure they entered a valid post ID and return if not
            $validator = \Validator::make($request->all(), [
                'termPostId' => 'required|numeric|between:0,2147483647',
            ]);

            if($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            // Could return Success/Warning/Error messages via the Session variable, so store everything for display
            //$messageArr = $this->chatty->termsAndSentimentForPost($request->input('termPostId'));
            $result = $this->search->termVectorsForPostWithScore($request->input('termPostId'));
            dd($result);

            // TODO: clean this up with a trait so we don't repeat this code every time
            if(array_key_exists('error', $messageArr)) {
                $request->session()->flash('error', $messageArr["error"]);
            }
            if(array_key_exists('warning', $messageArr)) {
                $request->session()->flash('warning', $messageArr["warning"]);
            }
            if(array_key_exists('success', $messageArr)) {
                $request->session()->flash('success', $messageArr["success"]);
            }
            
            // Session variables have already been flashed so just return back
            return back();
        }
        // QUERY POSTS FOR TERM
        else if($request->has('queryTermPosts'))
        {
            // Could return Success/Warning/Error messages via the Session variable, so store everything for display
            //$messageArr = $this->chatty->termsAndSentimentForPost($request->input('termPostId'));
            $result = $this->search->generatePostTrigramsForTerm($request->input('wordCloudTerm'),$request->input('wordCloudAuthor'),$request->input('wordCloudFrom'),$request->input('wordCloudTo'));
            dd($result);

            // TODO: clean this up with a trait so we don't repeat this code every time
            if(array_key_exists('error', $messageArr)) {
                $request->session()->flash('error', $messageArr["error"]);
            }
            if(array_key_exists('warning', $messageArr)) {
                $request->session()->flash('warning', $messageArr["warning"]);
            }
            if(array_key_exists('success', $messageArr)) {
                $request->session()->flash('success', $messageArr["success"]);
            }
            
            // Session variables have already been flashed so just return back
            return back();
        }
        // GET TRIGRAMS FOR POST ID
        else if($request->has('getTrigramsForPost'))
        {
            $result = $this->search->getTrigramsForPost($request->input('trigramPostId'));
            dd($result);

            return back();
        }
    }

    /**
     * Similar to the Post sync() and Event sync() functions, this function
     * automatically retrieves posts from the database that haven't been indexed
     * in the search server and submits them for searching.
     * 
     * @param 
     */
    public function crawl()
    {
        // Retrieve Illuminate\Support\Collection of posts
        $postsToIndex = post::where([['indexed','false'],['category','<>',postcategory::categoryId('nuked')]])->limit(app_setting::postsToIndex())->get();

        // Pass collection to Chatty class, which will pass it to ElasticSearch class. Do this
        // so that the Chatty class can do all the dbAction logging, etc.
        $return = $chatty->submitPostsForSearchIndexing($postsToIndex);
    }
}
