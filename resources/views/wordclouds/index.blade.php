@extends('layouts.app')

@section('content')
<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('viewAll',App\Chatty\word_cloud::class)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view Word Clouds.
            </div>
        </div>
    @else

        
        <div class="row">
            
            <div class="col-md-6">

                <!-- Welcome Card -->
                <div class="row">
                    <div class="col">
                        
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Welcome to Word Clouds</h5>
                                <p>Word clouds are a graphical representation of your word usage while posting on the Chatty. Larger words are used more frequently. Previously generated word clouds can be viewed/shared/deleted via the table below. Or make your next word cloud using the next panel!</p>
                                
                                <div class="row mt-2 mb-2 justify-content-center">
                                    <div class="col-sm-5">
                                        <p><span class="mr-4">Processing:</span> {{ $cloudsInProgress }} / {{ $appsettings->word_cloud_total_workers }}</p>
                                    </div>
                                    <div class="col-sm-5">
                                        <p><span class="mr-4">Queued:</span> {{ $cloudsQueued }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @can('viewAdmin',App\Chatty\word_cloud::class)
                <!-- Settings Card -->
                <div class="row">
                    <div class="col">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Word Cloud Settings</h5>
                                <p>After any code changes: sudo supervisorctl stop laravel-worker:*</p>
                                <form action="{{ route('wordclouds') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <label for="word-cloud-month-limit" class="col-md-5 col-form-label">Max Range in Months<br /><small>(0 for unlimited)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-month-limit" name="wordCloudMonthLimit" value="{{ old('wordCloudMonthLimit', $appsettings->word_cloud_month_limit) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-phrase-month-limit" class="col-md-5 col-form-label">Max Phrase Range (months)<br /><small>(0 for unlimited)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-phrase-month-limit" name="wordCloudPhraseMonthLimit" value="{{ old('wordCloudPhraseMonthLimit', $appsettings->word_cloud_phrase_month_limit) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-batch-size" class="col-md-5 col-form-label">Elastic Batch Size<br /><small>(1000 or fewer)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-batch-size" name="wordCloudBatchSize" value="{{ old('wordCloudMonthLimit', $appsettings->word_cloud_elastic_terms_batch_size) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-max-per-user" class="col-md-5 col-form-label">Max Word Clouds<br /><small>(Max clouds a user can create)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-max-per-user" name="wordCloudMaxPerUser" value="{{ old('wordCloudMaxPerUser', $appsettings->word_cloud_max_per_user) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-terms-per-cloud" class="col-md-5 col-form-label">Terms Per Cloud</label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-terms-per-cloud" name="wordCloudTermsPerCloud" value="{{ old('wordCloudTermsPerCloud', $appsettings->word_cloud_terms_per_cloud) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-phrase-2term-threshold" class="col-md-5 col-form-label">2-Term Threshold<br /><small>(Keep phrases above this count)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-phrase-2term-threshold" name="wordCloud2TermPhraseThreshold" value="{{ old('wordCloud2TermPhraseThreshold', $appsettings->wordcloud_phrases_2term_threshold) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-phrase-3term-threshold" class="col-md-5 col-form-label">3-Term Threshold<br /><small>(Keep phrases above this count)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-phrase-3term-threshold" name="wordCloud3TermPhraseThreshold" value="{{ old('wordCloud3TermPhraseThreshold', $appsettings->wordcloud_phrases_3term_threshold) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="word-cloud-phrase-display-threshold" class="col-md-5 col-form-label">Phrase Display Threshold<br /><small>(% phrase use vs. term use)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="word-cloud-phrase-display-threshold" name="wordCloudPhraseDisplayThreshold" value="{{ old('wordCloudPhraseDisplayThreshold', $appsettings->wordcloud_phrase_display_threshold) }}">
                                        </div>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="display-sentiment" name="displaySentiment" value="displaySentiment" {{ $appsettings->word_cloud_display_sentiment ? 'checked' : '' }}>
                                        <label class="form-check-label" for="display-sentiment">Display Sentiment</label>
                                    </div>
                                    <!-- Phrase generation -->
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="word-cloud-phrases-default" name="wordCloudPhrasesDefault" value="wordCloudPhrasesDefault" {{ $appsettings->wordcloud_phrases_default ? 'checked' : '' }}>
                                        <label class="form-check-label" for="word-cloud-phrases-default">Generate phrases by default?</label>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="saveSettings" @cannot('update',$appsettings) disabled @endcannot>Save Settings</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endcan
            </div>

            <!-- Create Word Cloud card -->
            <div class="col-md-6">

                <!-- Daily Word Cloud Card -->
                @can('viewAdmin',App\Chatty\word_cloud::class)
                <div class="row">
                    <div class="col">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Daily Chatty Cloud</h5>
                                <p><strong>Code Changes:</strong> sudo supervisorctl stop laravel-worker:*<br />
                                <strong>Manual creation:</strong> php artisan clouds:createdaily</p>
                                <form action="{{ route('wordclouds') }}" method="POST">
                                @csrf
                                    <div class="form-group row">
                                        <label for="daily-cloud-hours" class="col-md-5 col-form-label">Daily Cloud Hours<br /><small>(time window in hours)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="daily-cloud-hours" name="dailyCloudHours" value="{{ old('dailyCloudHours', $appsettings->chatty_daily_wordcloud_hours) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="daily-cloud-user" class="col-md-5 col-form-label">Daily Cloud User<br /><small>(should not exist in Chatty)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="daily-cloud-user" name="dailyCloudUser" value="{{ old('dailyCloudUser', $appsettings->chatty_daily_wordcloud_user) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="daily-cloud-cloud" class="col-md-7 col-form-label">Cloud Permissions<br /><small>(Who can view cloud & save PNG?)</small></label>
                                        <div class="col-md">
                                            <select class="form-control" id="daily-cloud-cloud" name="dailyCloudPerms" value="{{ old('dailyCloudPerms') }}">
                                                <option @if($dailyCloudPerms == 'Self') selected="selected" @endif>Self</option>
                                                <option @if($dailyCloudPerms == 'Chatty') selected="selected" @endif>Chatty</option>
                                                <option @if($dailyCloudPerms == 'Anyone') selected="selected" @endif>Anyone</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="daily-cloud-table" class="col-md-7 col-form-label">Table Permissions<br /><small>(Who can view table & save CSV?)</small></label>
                                        <div class="col-md">
                                            <select class="form-control" id="daily-cloud-table" name="dailyTablePerms" value="{{ old('dailyTablePerms') }}">
                                                <option @if($dailyTablePerms == 'Self') selected="selected" @endif>Self</option>
                                                <option @if($dailyTablePerms == 'Chatty') selected="selected" @endif>Chatty</option>
                                                <option @if($dailyTablePerms == 'Anyone') selected="selected" @endif>Anyone</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="daily-cloud-filter" class="col-md-7 col-form-label">Word Filter<br /><small id="daily-filter-description">{{ $chattyDailyFilterDescr }}</small></label>
                                        <div class="col-md">
                                            <select class="form-control" id="daily-cloud-filter" name="dailyFilter" value="{{ old('dailyFilter') }}" onchange="updateWordCloudFilterDescr()">
                                                @foreach($wordCloudFilters as $wordFilter)
                                                    <option @if($dailyFilter == $wordFilter->id) selected="selected" @endif data-descr="{{ $wordFilter->descr }}">{{ $wordFilter->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="daily-cloud-colorset" class="col-md-7 col-form-label">Colorset<br /><small id="daily-colorset-description">{{ $chattyDailyColorsetDescr }}</small></label>
                                        <div class="col-md">
                                            <select class="form-control" id="daily-cloud-colorset" name="dailyColorset" value="{{ old('dailyColorset') }}" onchange="updateWordCloudcolorsetDescr()">
                                                @foreach($colorsets as $colorset)
                                                    <option @if($dailyColorset == $colorset->id) selected="selected" @endif data-descr="{{ $colorset->descr }}">{{ $colorset->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Filters -->
                                    <div class="form-group row">
                                        <div class="col-md">
                                            <span>Categories to include in Daily Chatty cloud:</span><br />
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="ontopic" id="ontopic" value="ontopic"  {{ $appsettings->chatty_daily_wordcloud_ontopic ? 'checked' : '' }}>
                                                <label class="form-check-label" for="ontopic">Ontopic</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="nws" id="nws" value="nws" {{ $appsettings->chatty_daily_wordcloud_nws ? 'checked' : '' }}>
                                                <label class="form-check-label" for="nws">NWS</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="stupid" id="stupid" value="stupid" {{ $appsettings->chatty_daily_wordcloud_stupid ? 'checked' : '' }}>
                                                <label class="form-check-label" for="stupid">Stupid</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="political" id="political" value="political" {{ $appsettings->chatty_daily_wordcloud_political ? 'checked' : '' }}>
                                                <label class="form-check-label" for="political">Political</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="tangent" id="tangent" value="tangent" {{ $appsettings->chatty_daily_wordcloud_tangent ? 'checked' : '' }}>
                                                <label class="form-check-label" for="tangent">Tangent</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="informative" id="informative" value="informative" {{ $appsettings->chatty_daily_wordcloud_informative ? 'checked' : '' }}>
                                                <label class="form-check-label" for="informative">Informative</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="nuked" id="nuked" value="nuked" {{ $appsettings->chatty_daily_wordcloud_nuked ? 'checked' : '' }}>
                                                <label class="form-check-label" for="nuked">Nuked</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="daily-cloud-phrases" name="dailyCloudPhrases" value="dailyCloudPhrases" {{ $appsettings->chatty_daily_wordcloud_phrases ? 'checked' : '' }}>
                                        <label class="form-check-label" for="daily-cloud-phrases">Generate phrases?</label>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="daily-cloud-active" name="dailyCloudActive" value="dailyCloudActive" {{ $appsettings->chatty_daily_wordcloud_active ? 'checked' : '' }}>
                                        <label class="form-check-label" for="daily-cloud-active">Automatically create Daily Chatty cloud?</label>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="saveDailySettings" @cannot('update',$appsettings) disabled @endcannot>Save Settings</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endcan

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Create a Word Cloud</h5>
                        <p>Fill out the fields below and hit Create to create your next word cloud.</p>

                        <form action="{{ route('wordclouds') }}" method="POST">
                        @csrf
                            @can('createForOthers',App\Chatty\word_cloud::class)
                            <div class="form-group row">
                                <label for="wordcloud-author" class="col-md-5 col-form-label">Create For Author</label>
                                <div class="col-md">
                                    <input type="text" class="form-control" id="wordcloud-author" name="authorName" value="{{ old('authorName') }}">
                                </div>
                            </div>
                            @endcan
                            <div class="form-group row">
                                <!-- Date Range -->
                                <div class="col-md-12 mb-3">
                                    <!-- Radio Buttons -->
                                    <div class="row">
                                        <div class="col-md">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="daterange" id="lastmonth" value="lastmonth" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" checked >
                                                <label class="form-check-label" for="lastmonth">Last 30 Days</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="daterange" id="prevmonth" value="prevmonth" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" >
                                                <label class="form-check-label" for="prevmonth">Previous Month</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="daterange" id="lastsixmonths" value="lastsixmonths" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" >
                                                <label class="form-check-label" for="lastsixmonths">Last 6 Months</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="daterange" id="lastyear" value="lastyear" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" >
                                                <label class="form-check-label" for="lastyear">Last Year</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="daterange" id="alltime" value="alltime" onclick="document.getElementById('date-from').disabled=true;document.getElementById('date-to').disabled=true;" >
                                                <label class="form-check-label" for="alltime">All-time</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="daterange" id="custom" value="custom" onclick="document.getElementById('date-from').disabled=false;document.getElementById('date-to').disabled=false;" >
                                                <label class="form-check-label" for="alltime">Custom</label>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- From/To text inputs -->
                                    <div class="form-group row">
                                        <div class="input-group col-sm-6">
                                            <input type="text" class="form-control" name="from" id="date-from" placeholder="From (YYYY-MM-DD)" aria-label="YYYY-MM-DD" aria-describedby="basic-addon2" value="{{ old('from') }}" <?php if(!isset($custom)) { echo('disabled'); } ?> >
                                        </div>
                                        <div class="input-group col-sm-6">
                                            <input type="text" class="form-control" name="to" id="date-to" placeholder="To (YYYY-MM-DD)" aria-label="YYYY-MM-DD" aria-describedby="basic-addon2" value="{{ old('to') }}" <?php if(!isset($custom)) { echo('disabled'); } ?> >
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <div class="col text-center mt-3 mb-3">
                                                <span style="border-style:solid; border-color:silver; border-width: 0.5px 0 0.5px 0;">Note: filter and permissions can be changed anytime after creation.</span>
                                        </div>
                                    </div>
                                    

                                    <!-- Security permissions -->
                                    <div class="form-group row">
                                        <label for="sharing-view" class="col-md-7 col-form-label">Cloud Permissions<br /><small>(Who can view cloud & save PNG?)</small></label>
                                        <div class="col-md">
                                            <select class="form-control" id="sharing-view" name="viewPermissions" value="{{ old('viewPermissions') }}">
                                                <option>Self</option>
                                                <option selected="selected">Chatty</option>
                                                <option>Anyone</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="sharing-download" class="col-md-7 col-form-label">Table Permissions<br /><small>(Who can view table & save CSV?)</small></label>
                                        <div class="col-md">
                                            <select class="form-control" id="sharing-download" name="downloadPermissions" value="{{ old('downloadPermissions') }}">
                                                <option>Self</option>
                                                <option>Chatty</option>
                                                <option>Anyone</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Word Filter -->
                                    <div class="form-group row">
                                        <label for="word-filter" class="col-md-7 col-form-label">Word Filter<br /><small id="word-filter-description">{{ $defaultDescr }}</small></label>
                                        <div class="col-md">
                                            <select class="form-control" id="word-filter" name="wordFilter" value="{{ old('wordFilter') }}" onchange="updateWordCloudFilterDescr()">
                                                @foreach($wordCloudFilters as $wordFilter)
                                                    <option @if($wordFilter->is_default) selected="selected" @endif data-descr="{{ $wordFilter->descr }}">{{ $wordFilter->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Colorset -->
                                    <div class="form-group row">
                                        <label for="colorset" class="col-md-7 col-form-label">Colorset<br /><small id="word-colorset-description">{{ $defaultColorsetDescr }}</small></label>
                                        <div class="col-md">
                                            <select class="form-control" id="colorset" name="wordColorset" value="{{ old('wordColorset') }}" onchange="updateWordCloudcolorsetDescr()">
                                                @foreach($colorsets as $colorset)
                                                    <option @if($colorset->is_default) selected="selected" @endif data-descr="{{ $colorset->descr }}">{{ $colorset->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Async vs. sync -->
                                    @can('createForOthers',App\Chatty\word_cloud::class)
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="word-cloud-sync" name="wordCloudSync" value="wordCloudSync">
                                        <label class="form-check-label" for="word-cloud-sync">Generate synchonously?</label>
                                    </div>
                                    @endcan
                                    <!-- Phrase generation -->
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="word-cloud-phrases" name="wordCloudPhrases" value="wordCloudPhrases" {{ $appsettings->wordcloud_phrases_default ? 'checked' : '' }}>
                                        <label class="form-check-label" for="word-cloud-phrases">Generate phrases? (Try this!)</label>
                                    </div>
                                </div>
                            </div>

                            <div class="input-group">
                                <div class="input-group-btn">
                                    <button class="btn btn-primary" type="submit" name="createCloud" @cannot('create',App\Chatty\word_cloud::class) disabled @endcannot>Create</button>
                                </div>
                            </div>

                        </form>

                    </div>

                </div>
            </div>

        </div>

        <!-- Non-table fluid view -->
        <div class="row">
            <div class="col-md">

                <div class="list-group">
                    <li class="list-group-item d-none d-md-block"> 
                        <div class="row" style="font-weight:bold;">
                            <div class="col-2">
                                ID
                            </div>
                            <div class="col-2">
                                Status
                            </div>
                            <div class="col-1">
                                Cloud
                            </div>
                            <div class="col-1">
                                Table
                            </div>
                            <div class="col-2">
                                Filter
                            </div>
                            <div class="col-2">
                                Date Range
                            </div>
                            <div class="col-1">
                                Phrases 
                            </div>
                            <div class="col-1">
                                &nbsp;
                            </div>
                        </div>
                    </li>
                    @foreach($wordClouds as $wordCloud)
                    <?php
                        $from = Carbon\Carbon::parse($wordCloud->from);
                        $to = Carbon\Carbon::parse($wordCloud->to);
                        $diffInDays = $to->diffInDays($from) - 1;
                    ?>

                    <li class="list-group-item">
                        <div class="row">
                            <div class="order-2 order-md-1 col-5 col-md-2">
                                <span class="d-inline d-md-none mr-3" style="font-weight: bold;">ID: </span><a href="{{ route('wordclouds.show',$wordCloud->id) }}">@php echo substr($wordCloud->id,-8) @endphp</a>
                                @can('createForOthers',App\Chatty\word_cloud::class)
                                <br />{{ $wordCloud->user }}
                                @endcan
                            </div>
                            <div class="order-3 order-md-3 col-7 col-md-2">
                                <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Status: </span>{{ $wordCloud->status }} ({{ $wordCloud->percent_complete }}%)
                            </div>
                            <div class="order-4 col-5 col-md-1">
                                <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Cloud: </span>{{ $wordCloud->share_cloud }}
                            </div>
                            <div class="order-5 col-7 col-md-1">
                                <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Table: </span>{{ $wordCloud->share_table_download }}
                            </div>
                            <div class="order-6 col-5 col-md-2">
                                <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Filter: </span><?php echo App\Chatty\word_cloud_filter::find($wordCloud->word_cloud_filter)->name; ?>
                            </div>
                            <div class="order-7 order-md-8 col-7 col-md-1 text-md-center">
                                <span class="d-inline d-md-none mr-5" style="font-weight: bold;">Phrases: </span><input class="form-check-input" type="checkbox" name="chkPhrases" value="chkPhrases" disabled {{ $wordCloud->generate_phrases ? 'checked' : '' }}>
                            </div>
                            <div class="order-8 order-md-7 col-md-2">
                                <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Dates: </span>{{ $from->format('M j Y') }} - {{ $to->format('M j Y') }} ({{ $diffInDays }} days)
                            </div>
                            <div class="order-9 col-md-1">
                                <a class="btn btn-primary" href="{{ route('wordclouds.edit', $wordCloud->id) }}" role="button" style="width:4em;">Edit</a>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </div>

            </div>
        </div>

    @endcannot

</div>
@endsection