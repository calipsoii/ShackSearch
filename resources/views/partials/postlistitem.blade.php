<?php
// If the post body contains two curly braces (such as someone posting sample code), Vue.js tries
// to process it instead of rendering it. A cheesy workaround from StackOverflow is to add an HTML
// comment between the last two curly braces, breaking Vue's recognition. Special thanks to mobab
// and his post 37620426 for totally screwing up my rendering. :P
$childPostBody = strip_tags($childPost->body);
$curlyBraces = "}}";
$pos = strpos($childPostBody, $curlyBraces);
if(!$pos === false) {
    $childPostBody = html_entity_decode(substr_replace($childPostBody, "<!---->", $pos+1, 0));
}
?>

@if ($childPost->category == 7)
    @can('viewNuked',App\Chatty\post::class)
        @php($GLOBALS["subthreadCounter"]+=1)
        @if ($GLOBALS["subthreadCounter"] > $displayThreshold)
            @if ($childPost->category == 7)
                <li class="list-group-item list-group-item-danger" style="margin-left:{{ $indent }}em;" >
            @else
                <li class="list-group-item" style="margin-left:{{ $indent }}em;" >
            @endif
                <div>
                    @if(isset($singleThread))
                        {!! $childPostBody !!}
                    @else
                        {!! $childPostBody !!}
                    @endif
                    <div style="display:inline-block; margin-left:0.75em;">
                        <span style="color:crimson; margin-right:0.75em;">{{ $childPost->author }}</span>
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
                                {{ $lol->count }} </span>
                            @endforeach  
                        @endif
                    </div>
                </div>
                </li>
        @elseif ($GLOBALS["subthreadCounter"] == $displayThreshold)
            <li class="list-group-item list-group-item-sm" style="margin-left:1em;background-color:#F1F1F1;font-weight:bold;" >
                {{ $displayThreshold }} posts hidden.
            </li>
        @endif
        @if (count($childPost->children()->get())> 0)
            @foreach($childPost->children()->orderBy('date','asc')->get() as $childPost)
                @php($indent+=1)
                @include('partials.postlistitem',$childPost)
                @if($indent > 1)
                    @php($indent-=1)
                @endif
            @endforeach
        @endif
    @endcan
@else
    @php($GLOBALS["subthreadCounter"]+=1)
    @if ($GLOBALS["subthreadCounter"] > $displayThreshold)
        @if ($childPost->category == 7)
            <li class="list-group-item list-group-item-sm list-group-item-danger" style="margin-left:{{ $indent }}em; display:inline-block;" >
        @else
            <li class="list-group-item list-group-item-sm" style="margin-left:{{ $indent }}em;" >
        @endif
            <div>
                @if(isset($singleThread))
                    {!! $childPostBody !!}
                @else
                    {!! substr($childPostBody,0,$truncateLength) !!}
                    @if(strlen($childPostBody) > $truncateLength)
                        <span>...</span>
                    @endif
                @endif
                <div style="display:inline-block; margin-left:0.75em;">
                    <span style="color:crimson; margin-right:0.75em;">{{ $childPost->author }}</span>
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
                            {{ $lol->count }} </span>
                        @endforeach  
                    @endif
                </div>
            </div>
            </li>
    @elseif ($GLOBALS["subthreadCounter"] == $displayThreshold)
        <li class="list-group-item list-group-item-sm" style="margin-left:1em;background-color:#F1F1F1;font-weight:bold;" >
            {{ $displayThreshold }} posts hidden.
        </li>
    @endif
    @if (count($childPost->children()->get())> 0)
        @foreach($childPost->children()->orderBy('date','asc')->get() as $childPost)
            @php($indent+=1)
            @include('partials.postlistitem',$childPost)
            @if($indent > 1)
                @php($indent-=1)
            @endif
        @endforeach
    @endif
@endif
