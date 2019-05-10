@extends('layouts.app')

@section('content')

@if($highlightedPost != NULL)
    @php
        $GLOBALS["highlightedPost"] = $highlightedPost->id;
    @endphp
@endif

<div class="container px-0 px-md-3">

    @include('returnMessage')
    @include('errors')
    <div class="col-sm-12">

        @foreach($threads as $thread)
        <div class="list-group">
            @include('partials.postroot')
            @php
                // Have to use a global counter or variable scope resets the count each new child subthread
                $GLOBALS["subthreadCounter"]=0;
                $postCount = \App\Chatty\post::where('thread_id','=',$thread->id)->count()-1; 
                $subthreadsToDisplay = \App\Chatty\app_setting::subthreadsToDisplay();
                $displayThreshold = 0;
                $threadId = $thread->id;
            @endphp
            <script type="text/javascript">
                var threadId = <?php echo $threadId; ?>
            </script>
            @foreach($thread->posts()->where('id',$thread->id)->first()->children()->orderBy('date','asc')->orderBy('id','asc')->get() as $childPost)
                @php($indent=1)
                @include('partials.childpost',$childPost)
            @endforeach
        </div>
        @endforeach

    </div>
</div>

@endsection