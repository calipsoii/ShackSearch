@extends('layouts.app')

@section('content')

<script>
function toggleAdvancedOptions() {
    var x = document.getElementById("advanced-options");
    var y = document.getElementById("adv-opts");
    if(x.style.display === "none") {
        x.style.display = "flex";
        y.value = "true";
    } else {
        x.style.display = "none";
        y.value = "false";
    }
}
</script>

<div class="container">

    @include('returnMessage')
    @include('errors')

    

        @can('viewAdmin',App\Chatty\ElasticSearch::class)

        <!-- First row of settings -->
        <div class="row">

            <!-- First column of settings -->
            <div class="col-sm-6">

                <!-- Search Crawler Settings -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Search Crawler Settings</h5>
                                <p>Automatically submits non-indexed posts to search engine for indexing. Posts submitted in small batches. Task scheduled every 5 minutes.</p>
                                <div class="row mt-2 mb-2 justify-content-center">
                                    <div class="col-sm-10">
                                        <p><span class="mr-4">Last indexed:</span> {{ \Carbon\Carbon::parse($lastSearchCrawl)->diffForHumans() }}
                                        <p><span class="mr-4">Posts Indexed:</span> {{ $indexStats['indexedPostCount'] }} / {{ $indexStats['totalPostsToIndex'] }} <strong>({{ $indexStats['percentIndexed']  }}%)</strong></p>
                                    </div>
                                </div>
                                <form action="{{ route('search') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <label for="posts-to-index" class="col-md-6 col-form-label">Posts to Index<br /><small>(# posts to index per batch)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="posts-to-index" name="postsToIndex" value="{{ old('postsToIndex', $appsettings->search_crawler_posts_to_index) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="index-batch-size" class="col-md-6 col-form-label">Index Batch Size<br /><small>(# posts in single Elastic message)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="index-batch-size" name="indexBatchSize" value="{{ old('indexBatchSize', $appsettings->search_crawler_batch_size) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="search-results-per-page" class="col-md-6 col-form-label">Search Results per Page</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="search-results-per-page" name="searchResultsPerPage" value="{{ old('searchResultsPerPage', $appsettings->num_search_results_per_page) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="total-search-results" class="col-md-6 col-form-label">Total Search Results<br /><small>(Max results per query)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="total-search-results" name="totalSearchResults" value="{{ old('totalSearchResults', $appsettings->elastic_max_results) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="crawler-username" class="col-md-6 col-form-label">Crawler Username</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="crawler-username" name="crawlerUsername" value="{{ old('crawlerUsername', $appsettings->search_crawler_username) }}">
                                        </div>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="search-crawler-enabled-flag" name="searchCrawlerEnabled" value="searchCrawlerEnabled" {{ $appsettings->search_crawler_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="search-crawler-enabled-flag">Automatically Index Posts</label>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="searchCrawlerSettings" @cannot('update',$appsettings) disabled @endcannot>Save Settings</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manual Indexing -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Manual Index Creation</h5>
                                <p>Manually submit one Post ID for indexing in Elastic.</p>
                                <form action="{{ route('search') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <div class="input-group col-md">
                                            <input type="text" class="form-control" name="manualIndexPostID" placeholder="Post ID To Index" aria-label="Post ID To Index" aria-describedby="basic-addon2" value="{{ old('manualIndexPostID') }}">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" type="submit" name="manualIndex" @cannot('submitIndex',App\Chatty\ElasticSearch::class) disabled @endcannot>Index Post</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <p>Manually submit a single batch of posts for indexing (uses Search Crawler Settings). Import posts equal to or older than date below.</p>
                                <form action="{{ route('search') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <div class="input-group col-md">
                                            <input type="text" class="form-control" name="singleBatchDate" value='<?php echo date('Y-m-d', strtotime("+1 days")); ?>' >
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" name="manualBatchIndex" type="submit" @cannot('submitIndex',App\Chatty\ElasticSearch::class) disabled @endcannot>Submit Batch</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- Second column of settings -->
            <div class="col-sm-6">

                <!-- Search Administration -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Search Server Administration</h5>
                                <form action="{{ route('search') }}" method="POST" class="mb-3">
                                    @csrf

                                    <div class="form-group row">
                                        <label for="elastic-posts-index-name" class="col-md-4 col-form-label">Post Index Name</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="elastic-posts-index-name" name="elasticPostsIndexName" value="{{ old('elasticPostsIndexName',$appsettings->elastic_post_search_index) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="elastic-posts-index-type" class="col-md-4 col-form-label">Post Index Type</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="elastic-posts-index-type" name="elasticPostsIndexType" value="{{ old('elasticPostsIndexType',$appsettings->elastic_post_type) }}">
                                        </div>
                                    </div>

                                    <button type="submit" name="searchAdministrationSettings" class="btn btn-primary" @cannot('update',$appsettings) disabled @endcannot>Save Settings</button>
                                </form>

                                <p>Before submitting ANY posts for indexing in Elastic, first create the index. This enables stemming, stop words, suggestions, and other advanced features that would otherwise be disabled.</p>
                                <form action="{{ route('search') }}" method="POST">
                                    @csrf
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="createPostsIndex" @cannot('submitIndex',App\Chatty\ElasticSearch::class) disabled @endcannot>Create Index</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Term Counts -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Elastic Term Counts</h5>
                                <p>Pull a list of the most common words (terms) used by the author.</p>
                                <form action="{{ route('search') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <div class="input-group col-md">
                                            <input type="text" class="form-control" name="authorUsername" placeholder="Username" aria-label="Username" aria-describedby="basic-addon2" value="{{ old('authorUsername') }}">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" type="submit" name="countAuthorWords" @cannot('submitIndex',App\Chatty\ElasticSearch::class) disabled @endcannot>Count Terms</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <p>Display term counts for a specific post ID.</p>
                                <form action="{{ route('search') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <div class="input-group col-md">
                                            <input type="text" class="form-control" name="termPostId" placeholder="Post ID" aria-label="Post ID" aria-describedby="basic-addon2" value="{{ old('termPostId') }}">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" type="submit" name="countPostTerms" @cannot('submitIndex',App\Chatty\ElasticSearch::class) disabled @endcannot>Count Terms</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <p>Get posts for a term</p>
                                <form action="{{ route('search') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <label for="word-cloud-term" class="col-md-4 col-form-label">Term</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-term" name="wordCloudTerm" value="{{ old('wordCloudTerm') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-from" class="col-md-4 col-form-label">From</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-from" name="wordCloudFrom" value="{{ old('wordCloudFrom') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-to" class="col-md-4 col-form-label">To</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-to" name="wordCloudTo" value="{{ old('wordCloudTo') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-author" class="col-md-4 col-form-label">Author</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-author" name="wordCloudAuthor" value="{{ old('wordCloudAuthor') }}">
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="queryTermPosts" @cannot('submitIndex',App\Chatty\ElasticSearch::class) disabled @endcannot>Find Posts</button>
                                        </div>
                                    </div>
                                </form>
                                <p>Get Trigrams for Post ID</p>
                                <form action="{{ route('search') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <label for="trigram-post-id" class="col-md-4 col-form-label">Post ID</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="trigram-post-id" name="trigramPostId" value="{{ old('trigramPostId') }}">
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="getTrigramsForPost" @cannot('submitIndex',App\Chatty\ElasticSearch::class) disabled @endcannot>Get Trigrams</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
        
        @endcan

        <?php
            $body = isset($_GET['body']) ? $_GET['body'] : '';
            $author = isset($_GET['author']) ? $_GET['author'] : '';
            $to = isset($_GET['to']) ? $_GET['to'] : '';
            $from = isset($_GET['from']) ? $_GET['from'] : '';
            $root = isset($_GET['rootposts']) ? true : false;
            if(isset($_GET['daterange'])) {
                switch($_GET['daterange']) {
                    case 'lastweek':
                        $lastweek = true;
                        break;
                    case 'lastmonth':
                        $lastmonth = true;
                        break;
                    case 'lastyear':
                        $lastyear = true;
                        break;
                    case 'custom':
                        $custom = true;
                        break;
                    default:
                        $alltime = true;
                        break;
                }
            } else {
                $alltime = true;
            }
            if(isset($_GET['sort'])) {
                switch($_GET['sort']) {
                    case 'desc':
                        $desc = true;
                        break;
                    case 'asc':
                        $asc = true;
                        break;
                    default:
                        //$score = true;
                        $desc = true;
                        break;
                }
            } else {
                //$score = true;
                $desc = true;
            }
            if(isset($_GET['engine'])) {
                switch($_GET['engine']) {
                    case 'simplequery':
                        $simplequery = true;
                        break;
                    case 'match':
                        $match = true;
                        break;
                    case 'common':
                        $common = true;
                        break;
                    default:
                        $simplequery = true;
                        break;
                }
            } else {
                $match = true;
            }
            if(isset($_GET['linktarget'])) {
                switch($_GET['linktarget']) {
                    case 'local':
                        $local = true;
                        break;
                    case 'shacknews':
                        $shacknews = true;
                        break;
                    default:
                        $local = true;
                        break;
                }
            } else {
                $local = true;
            }
        ?>

        <!-- Search card -->
        <div class="row">
            <div class="col-md-12">

                <div class="card mb-3">
                    <div class="card-body">
                        <form action="{{ route('search') }}" method="GET">
                            <!-- <p>See <a href="https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html#_simple_query_string_syntax">this page</a> for search markup.</p> -->
                            <div class="form-group row">
                                <div class="input-group col-md">
                                    <input type="text" class="form-control" name="body" placeholder="Search Chatty Posts" aria-label="Search Chatty Posts" aria-describedby="basic-addon2" value='<?php echo htmlspecialchars($body, ENT_QUOTES); ?>' >
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="submit">Search</button>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-link" onclick="toggleAdvancedOptions()">Advanced Options</button>

                            <!-- Advanced Options -->
                            <div id="advanced-options" class="form-group row" style="display:none;">

                                <div class="col">
                                    <div class="form-group row">

                                        <!-- Author filter -->
                                        <div class="col-md-5 mb-3">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="author" placeholder="Filter by Author" aria-label="Filter by Author" aria-describedby="basic-addon2" value='<?php echo $author; ?>'>
                                            </div>
                                        </div>

                                        <!-- Date Range -->
                                        <div class="col-md-7 mb-3">
                                            <!-- Radio Buttons -->
                                            <div class="row">
                                                <div class="col-md">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="daterange" id="lastweek" value="lastweek" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" <?php if(isset($lastweek)){ echo 'checked'; }?> >
                                                        <label class="form-check-label" for="lastweek">Last Week</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="daterange" id="lastmonth" value="lastmonth" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" <?php if(isset($lastmonth)){ echo 'checked'; }?> >
                                                        <label class="form-check-label" for="lastmonth">Last Month</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="daterange" id="lastyear" value="lastyear" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" <?php if(isset($lastyear)){ echo 'checked'; }?> >
                                                        <label class="form-check-label" for="lastyear">Last Year</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="daterange" id="alltime" value="alltime" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" <?php if(isset($alltime)){ echo 'checked'; }?> >
                                                        <label class="form-check-label" for="alltime">All-time</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="daterange" id="custom" value="custom" onclick="document.getElementById('date-from').disabled=false;document.getElementById('date-to').disabled=false;" <?php if(isset($custom)){ echo 'checked'; }?> >
                                                        <label class="form-check-label" for="alltime">Custom</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- From/To text inputs -->
                                            <div class="row">
                                                <div class="input-group col-sm-6">
                                                    <input type="text" class="form-control" name="from" id="date-from" placeholder="From (YYYY-MM-DD)" aria-label="YYYY-MM-DD" aria-describedby="basic-addon2" value='<?php echo $from; ?>' <?php if(!isset($custom)) { echo('disabled'); } ?> >
                                                </div>
                                                <div class="input-group col-sm-6">
                                                    <input type="text" class="form-control" name="to" id="date-to" placeholder="To (YYYY-MM-DD)" aria-label="YYYY-MM-DD" aria-describedby="basic-addon2" value='<?php echo $to; ?>' <?php if(!isset($custom)) { echo('disabled'); } ?> >
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="row">
                                        <!-- Root Posts flag -->
                                        <div class="col-md-5">
                                            <div class="form-group form-check">
                                                <input class="form-check-input" type="checkbox" id="search-root-posts" name="rootposts" value="rootposts" <?php if($root) echo 'checked'; ?>>
                                                <label class="form-check-label" for="search-root-posts">Root Posts Only</label>
                                            </div>
                                        </div>
                                        <!-- Sort Order -->
                                        <div class="col-md-7 form-group">
                                            <span class="mr-2">Sort Order:</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="sort" id="score" value="score" <?php if(isset($score)){ echo 'checked'; }?> >
                                                <label class="form-check-label" for="lastweek">Elastic Score</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="sort" id="desc" value="desc" <?php if(isset($desc)){ echo 'checked'; }?> >
                                                <label class="form-check-label" for="lastmonth">Date Desc</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="sort" id="asc" value="asc" <?php if(isset($asc)){ echo 'checked'; }?> >
                                                <label class="form-check-label" for="lastyear">Date Asc</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <!-- Query Engine -->
                                        <div class="offset-md-5 col-md-7 mb-3 form-group">
                                            <span class="mr-2">Query Engine:</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="engine" id="simplequery" value="simplequery" <?php if(isset($simplequery)){ echo 'checked'; }?> >
                                                <label class="form-check-label" for="simplequery">Simple Query String</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="engine" id="match" value="match" <?php if(isset($match)){ echo 'checked'; }?> >
                                                <label class="form-check-label" for="match">Match Query</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="engine" id="common" value="common" <?php if(isset($common)){ echo 'checked'; }?> >
                                                <label class="form-check-label" for="common">Common Terms Query</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <!-- Shack vs. Local Links -->
                                        <div class="offset-md-5 col-md-7 mb-3 form-group">
                                            <span class="mr-2">Link Target:</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="linktarget" id="local" value="local" <?php if(isset($local)){ echo 'checked'; }?> >
                                                <label class="form-check-label" for="match">Local</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="linktarget" id="shacknews" value="shacknews" <?php if(isset($shacknews)){ echo 'checked'; }?> >
                                                <label class="form-check-label" for="simplequery">Shacknews</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        @if($suggestions)
                            <div class="row mb-1 mt-3">
                                <div class="col-md-12">
                                    Suggestions: 
                                    <?php
                                        $counter = 1;
                                    ?>
                                    @foreach($suggestions as $suggestion)
                                        <span><a href="{{ route('search') }}?body={{ urlencode(strip_tags($suggestion)) }}">{!! $suggestion !!}</a></span>@if($counter < count($suggestions))<span class="mr-1 ml-2">|</span>@endif
                                        <?php $counter++; ?>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="row ml-1 mb-3">
                        <div class="col-md-12">
                            Search result count: {{ $resultCount }}
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Search Result card -->
        @if($searchResults)
            <div class=" row">
                <div class="col">
                    <span class="float-right">{{ $searchResults->appends(Request::except('page'))->links() }}</span>
                </div>
            </div>
            <div class="list-group">
                @foreach($searchResults as $post)
                    @include('partials.searchlistitem')
                @endforeach
            </div>
            <div class=" row">
                <div class="col">
                    <span class="float-right">{{ $searchResults->appends(Request::except('page'))->links() }}</span>
                </div>
            </div>
        @endif

@endsection