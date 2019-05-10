@php($viewNuked=false)
@can('viewNuked',App\Chatty\post::class)
    @php($viewNuked = true)
@endcan


<?php
    // If the post body contains two curly braces (such as someone posting sample code), Vue.js tries
    // to process it instead of rendering it. A cheesy workaround from StackOverflow is to add an HTML
    // comment between the last two curly braces, breaking Vue's recognition. Special thanks to mobab
    // and his post 37620426 for totally screwing up my rendering. :P
    //$childPostBody = strip_tags($childPost->body);
    $childPostBody = $childPost->body;
    $curlyBraces = "}}";
    $pos = strpos($childPostBody, $curlyBraces);
    if(!$pos === false) {
        $childPostBody = substr_replace($childPostBody, "<!---->", $pos+1, 0);
    }

    // Display nuked threads only to those who should see them. Set the flag so they're colored appropriately.
    $showThread = true;
    $nukedThread = false;
    if($childPost->category == 7) {
        $nukedThread = true;
        if(!$viewNuked) {
            $showThread = false;
        }
    }

?>

@if($showThread)

    @php($GLOBALS["subthreadCounter"]+=1)

    <li class="list-group-item @if($nukedThread) list-group-item-danger @endif collapsible-post collapsible-post-collapsed" style="margin-left:{{ $indent }}em;" id="{{ $childPost->id }}" data-parentid="{{ $childPost->parent_id }}" data-subthreadid="{{ $GLOBALS["subthreadCounter"] }}">
        <div class="row d-none d-md-flex collapsible-post-header">
            <div class="col-7">
                <h6 style="color:crimson;display:inline;margin-right:1em;">{{ $childPost->author }}</h6>
                @if($childPost->post_lols->count() > 0)
                    @foreach($childPost->post_lols as $lol)
                        @if($lol->tag == "lol")
                            <span class="badge" style="background-color:orange;color:white;">
                        @elseif($lol->tag == "inf")
                            <span class="badge" style="background-color:blue;color:white;">
                        @elseif($lol->tag == "tag")
                            <span class="badge" style="background-color:green;color:white;">
                        @elseif($lol->tag == "unf")
                            <span class="badge" style="background-color:red;color:white;">
                        @elseif($lol->tag == "wtf")
                            <span class="badge" style="background-color:purple;color:white;">
                        @else
                            <span class="badge badge-default">
                        @endif
                        {{ $lol->count }}</span>
                    @endforeach  
                @endif
            </div>
            <div class="col text-right">
                <small>{{ \Carbon\Carbon::parse($childPost->date)->diffForHumans() }}</small>
            </div>
        </div>

        <div class="row collapsible-post-body">
            <div class="col-sm-12" style="padding-right:24px;">
                {!! $childPostBody !!}
                <div class="click-for-more justify-content-center">
                    <i class="fas fa-chevron-circle-right my-auto" aria-hidden="true"></i>
                </div>
            </div>
        </div>
        <svg width="0" height="0" style="z-index:-1;position:absolute;left:1px;top:100%;" class="parent-svg">
            <line x1="0" y1="0" x2="0" y2="4400" stroke="#026e3cef"></line>
        </svg>
    </li>

    @if (count($childPost->children()->get())> 0)
        @foreach($childPost->children()->orderBy('date','asc')->get() as $childPost)
            @php($indent+=1)
            @include('partials.childpost',$childPost)
            @if($indent > 1)
                @php($indent-=1)
            @endif
        @endforeach
    @endif

@endif