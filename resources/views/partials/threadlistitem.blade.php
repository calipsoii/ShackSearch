@php($viewNuked=false)
@can('viewNuked',App\Chatty\post::class)
    @php($viewNuked = true)
@endcan


<?php
    /* If the post body contains two curly braces (such as someone posting sample code), Vue.js tries
       to process it instead of rendering it. A cheesy workaround from StackOverflow is to add an HTML
       comment between the last two curly braces, breaking Vue's recognition. Special thanks to mobab
       and his post 37620426 for totally screwing up my rendering. :P */
    $rootPostBody = $thread->posts->where('id',$thread->id)->first()->body;
    $curlyBraces = "}}";
    $pos = strpos($rootPostBody, $curlyBraces);
    if(!$pos === false) {
        $rootPostBody = substr_replace($rootPostBody, "<!---->", $pos+1, 0);
    }

    /* People use a lot of line breaks for formatting in their root post, and with only displaying 3
       lines it's very difficult to see much of the topic at a glance. JUST for the threadlistitem
       I'm replacing breaks with spaces so that we can see more. Once they click on the thread they'll
       get the original, unedited body.

       To replace all breaks with spaces:
       $rootPostBody = str_replace('<br \/>',' ', str_replace('<br />',' ',$rootPostBody));

       To replace multiple sequential breaks with a single break, see below.
    */
    $rootPostBody = str_replace('<br \/>','<br />', ltrim(rtrim($rootPostBody)));
    $rootPostBody = preg_replace('#(<br */?>\s*)+#i', '<br />', $rootPostBody);

    /* Since each threadlistitem is an anchor, and we cannot have anchors within anchors, we have to
       strip the <a> tags off any links the user included. This is not an issue in the SHOW view since
       each post there is displayed in a <div> not a <a>.
    */
    $rootPostBody = preg_replace('#<a.*?>([^>]*)</a>#i', '$1', $rootPostBody); 

    // Display nuked threads only to those who should see them. Set the flag so they're colored appropriately.
    $showThread = true;
    $nukedThread = false;
    if($thread->posts->where('id',$thread->id)->first()->category == 7) {
        $nukedThread = true;
        if(!$viewNuked) {
            $showThread = false;
        }
    }
?>

@if($showThread)
    <a title="{{ $thread->id }}" href="{{ route('threads.show', ['thread' => $thread]) }}" class="list-group-item list-group-item-action @if($nukedThread) list-group-item-danger @endif thread-list-item">

        <div class="row">
            <div class="col-7">
                <h6 class="thread-list-item-author">{{ $thread->posts->where('id',$thread->id)->first()->author }}</h6>
                @if($thread->posts->where('id',$thread->id)->first()->post_lols->count() > 0)
                    @foreach($thread->posts->where('id',$thread->id)->first()->post_lols as $lol)
                        @if($lol->tag == "lol")
                            <span class="badge lol-badge lol-badge-lol">
                        @elseif($lol->tag == "inf")
                            <span class="badge lol-badge lol-badge-inf">
                        @elseif($lol->tag == "tag")
                            <span class="badge lol-badge lol-badge-tag">
                        @elseif($lol->tag == "unf")
                            <span class="badge lol-badge lol-badge-unf">
                        @elseif($lol->tag == "wtf")
                            <span class="badge lol-badge lol-badge-wtf">
                        @else
                            <span class="badge lol-badge-default">
                        @endif
                        {{ $lol->count }}</span>
                    @endforeach  
                @endif
            </div>
            <div class="col text-right">
                <small>{{ \Carbon\Carbon::parse($thread->posts->where('id',$thread->id)->first()->date)->diffForHumans() }}</small>
            </div>
        </div>

        <div class="row thread-list-item-body">
            <div class="col-sm-12" style="padding-right:24px;">
                <?php echo trim($rootPostBody) ?>
                <div class="click-for-more justify-content-center">
                    <i class="fas fa-chevron-circle-right my-auto" aria-hidden="true"></i>
                </div>
            </div>
            
        </div>

    </a>
@endif