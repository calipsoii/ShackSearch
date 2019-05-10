@extends('layouts.app')

@section('content')
<div class="container">

    @include('returnMessage')
    @include('errors')
    
    @cannot('viewAll',App\Chatty\post::class)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view Posts.
            </div>
        </div>
    @else

        @can('viewAdmin',App\Chatty\post::class)
            <div class="row">

                <!-- Left Column -->
                <div class="col-sm-6">
                    <!-- Post Sync Settings card -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Post Sync Settings</h5>
                            <p>Automatically populate missing posts in local DB. Threads downloaded from Winchatty in small batches within a working block of Post IDs. Task scheduled every 1 minute.</p>
                            <div class="row mt-2 mb-2 justify-content-center">
                                <div class="col-sm-10">
                                    <p><span class="mr-4">Last mass sync:</span> {{ \Carbon\Carbon::parse($lastMassSync)->diffForHumans() }}</p>
                                </div>
                            </div>
                            <form action="{{ url('/posts') }}" method="POST">
                                <div class="form-group">
                                    {{ csrf_field() }}
                                    <div class="form-group row">
                                        <label for="working-block" class="col-md-7 col-form-label">Working Block<br /><small>(sync 100k posts from this starting point)</small></label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="working-block" name="workingBlock" value="{{ old('workingBlock', $appsettings->mass_sync_working_block) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="stop-post" class="col-md-7 col-form-label">Stop Post<br /><small>(stop syncing when this post will be passed)</small></label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="stop-post" name="stopPost" value="{{ old('stopBlock', $appsettings->mass_sync_stop_post) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="threads-to-retrieve" class="col-md-7 col-form-label">Threads to Retrieve<br /><small>(# random posts in working block to retrieve)</small></label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="threads-to-retrieve" name="threadsToRetrieve" value="{{ old('threadsToRetrieve', $appsettings->mass_sync_threads_to_retrieve) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="mass-sync-username" class="col-md-7 col-form-label">Mass Sync Username</label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="mass-sync-username" name="massSyncUsername" value="{{ old('massSyncUsername', $appsettings->mass_sync_username) }}">
                                        </div>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="mass-sync-enabled-flag" name="massSyncEnabled" value="massSyncEnabled" {{ $appsettings->mass_post_sync_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="mass-sync-enabled-flag">Automatically Mass Sync Posts</label>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="mass-sync-auto-block" name="MassSyncAutoBlock" value="MassSyncAutoBlock" {{ $appsettings->mass_post_sync_auto_block ? 'checked' : '' }}>
                                        <label class="form-check-label" for="mass-sync-auto-block">Automatically Advance Working Block</label>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="mass-sync-advance-desc" name="massSyncAdvanceDesc" value="massSyncAdvanceDesc" {{ $appsettings->mass_sync_advance_desc ? 'checked' : '' }}>
                                        <label class="form-check-label" for="mass-sync-advance-desc">Auto Advance Descending?</label>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="postSyncSettings" @cannot('update',$appsettings) disabled @endcannot>
                                                <i></i>Save Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right column -->
                <div class="col-sm-6">
                    <!-- Post Count Settings card -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Post Count Settings</h5>
                            <p>Asynchronously update the post counts below. Scheduled every 5 minutes.</p>
                            <div class="row mt-2 mb-2 justify-content-center">
                                <div class="col-sm-10">
                                    <p><span class="mr-4">Last counted:</span> {{ \Carbon\Carbon::parse($lastPostCount)->diffForHumans() }}</p>
                                </div>
                            </div>
                            <form action="{{ url('/posts') }}" method="POST">
                                <div class="form-group">
                                    {{ csrf_field() }}
                                    <div class="form-group row">
                                        <label for="total-post-count" class="col-md-7 col-form-label">Total Post Count<br /><small>(display this many squares)</small></label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="total-post-count" name="totalPostCount" value="{{ old('totalPostCount', $appsettings->post_count_max) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="post-block-size" class="col-md-7 col-form-label">Post Block Size<br /><small>(# posts in each square)</small></label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="post-block-size" name="postBlockSize" value="{{ old('postBlockSize', $appsettings->post_count_bracket_size) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="post-count-username" class="col-md-7 col-form-label">Post Count Username</label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="post-count-username" name="postCountUsername" value="{{ old('postCountUsername', $appsettings->post_count_username) }}">
                                        </div>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="post-count-enabled-flag" name="postCountEnabled" value="postCountEnabled" {{ $appsettings->post_count_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="post-count-enabled-flag">Automatically Update Post Counts</label>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="postCountSettings" @cannot('update',$appsettings) disabled @endcannot>
                                                <i></i>Save Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

            
            <div class="row">

                <!-- Left Column -->
                <div class="col-sm-6">

                    <!-- Count Missing Posts card -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Count Missing Posts</h5>
                            <p>Count missing posts in a range. Check the box to retrieve them from Winchatty.</p>
                            <p class="ml-3"><strong>Missing post count: @if(session('missingCount') >= 0) {{ session('missingCount') }} @endif</strong></p>
                            <form action="{{ url('/posts') }}" method="POST">
                                <div class="form-group">
                                    {{ csrf_field() }}
                                    <div class="form-group row">
                                        <label for="missing-posts-from" class="col-md-7 col-form-label">Missing Posts From</label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="missing-posts-from" name="missingPostsFrom" value="{{ old('missingPostsFrom') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="missing-posts-to" class="col-md-7 col-form-label">Missing Posts To</label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="missing-posts-to" name="missingPostsTo" value="{{ old('missingPostsTo') }}">
                                        </div>
                                    </div>
                                    <div class="form-group form-check">
                                        <input class="form-check-input" type="checkbox" id="retrieve-missing-posts" name="retrieveMissingPosts" value="retrieveMissingPosts">
                                        <label class="form-check-label" for="retrieve-missing-posts">Retrieve Missing Posts</label>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <button class="btn btn-primary" type="submit" name="countMissing" @cannot('create',App\Chatty\post::class) disabled @endcannot>
                                                <i></i>Count Missing
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                </div>

                <!-- Right Column -->
                <div class="col-sm-6">
                    <!-- Post Manual Sync card -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Manual Post Sync</h5>
                            <p>Using Post Sync Settings, perform a one-time adhoc sync. Requires "Automatically Mass Sync Posts" to be checked.</p>                                    
                            <form action="{{ route('posts') }}" method="POST">
                                @csrf
                                <div class="input-group">
                                    <div class="input-group-btn">
                                        <button class="btn btn-primary" type="submit" name="manualSync" @cannot('create',App\Chatty\post::class) disabled @endcannot>
                                            <i></i>Manual Sync
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

        @endcan

        <!-- Database Population card -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Database Population</h5>
                        <p>Each square represents 100,000 posts. Color shifts from red to green as each 100k block of posts is populated.</p>
                        <form action="{{ url('/posts') }}" method="POST">
                            <div class="form-group row justify-content-md-center">
                            {{ csrf_field() }}

                                <table width="100%" style="border: 1px solid #f2f2f2;">
                                    <tbody>
                                        <tr>
                                            @php($squareCount=0)
                                            @foreach($postCounts as $count)
                                                @php($squareCount += 1)
                                                <?php
                                                    $textColor="white";
                                                    $red = round(255 - ($count->percent * 255)/100, 0);
                                                    $green = round(($count->percent * 255)/100, 0);
                                                    if($green > 175) { $textColor="black"; }
                                                    // $red = round(255 - (($postCountBrackets[$arrayIndex]["postsInBracket"] / (100000-$postCountBrackets[$arrayIndex]["excludedInBracket"])) * 255),0);
                                                    // $green = round((($postCountBrackets[$arrayIndex]["postsInBracket"] / (100000-$postCountBrackets[$arrayIndex]["excludedInBracket"])) * 255),0);
                                                    // $percentage = round(($postCountBrackets[$arrayIndex]["postsInBracket"]/(100000-$postCountBrackets[$arrayIndex]["excludedInBracket"]))*100,0);
                                                ?>
                                                <td style="background-color: rgb({{ $red }}, {{ $green }}, 0);border:1px solid #f2f2f2;width:1%;color:{{ $textColor }}">
                                                    <div class="d-block d-sm-none" style="font-size:xx-small;">
                                                        {{ $count->percent }}%
                                                    </div>
                                                    <div class="d-none d-sm-block d-md-none" style="font-size:xx-small;">
                                                        {{ $count->count }} ({{ $count->percent }}%)
                                                    </div>
                                                    <div class="d-none d-sm-none d-md-block">
                                                        {{ $count->count }} ({{ $count->percent }}%)
                                                    </div>
                                                </td>
                                                @if($squareCount % 10 == 0)
                                                </tr><tr>
                                                @endif
                                            @endforeach
                                        </tr>
                                    </tbody>
                                </table>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    @endcannot
</div>
@endsection