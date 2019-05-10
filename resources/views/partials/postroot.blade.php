@can('viewNuked',App\Chatty\post::class)
    <?php $viewNuked = TRUE; ?>
@else
    <?php $viewNuked = FALSE; ?>
@endcan

@if ($thread->posts->where('id',$thread->id)->first()->category != 7)
<li class="list-group-item">
    <div class="row">
        <div class="col-xs-5 col-sm-4">
            <h5 style="color:crimson;font-weight:bold;">
                {{ $thread->posts->where('id',$thread->id)->first()->author }}
                @if($thread->posts->where('id',$thread->id)->first()->post_lols->count() > 0)
                    @foreach($thread->posts->where('id',$thread->id)->first()->post_lols as $lol)
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
            </h5>
        </div>
        <div class="col-xs-7 col-sm-offset-4 col-sm-4 text-right">
            <small class="text-muted">{{ $thread->date }}</small>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            {!! $thread->posts->where('id',$thread->id)->first()->body !!}
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 text-right">
            @if(isset($singleThread))
                <?php
                    $hyperlinkTarget = "https://www.shacknews.com/chatty?id=" . $thread->id ."#item_" . $thread->posts->where('id',$thread->id)->first()->id;
                ?>
                <a href={!! $hyperlinkTarget !!}>{{ $thread->id }}</a>
            @else
                <a href="threads/{{ $thread->id }}">{{ $thread->id }}</a>
            @endif
        </div>
    </div>
</li>
@elseif ($thread->posts->where('id',$thread->id)->first()->category == 7 && $viewNuked)
<li class="list-group-item list-group-item-danger">
    <div class="row">
        <div class="col-xs-5 col-sm-4">
            <h5 style="color:crimson;font-weight:bold;">
                {{ $thread->posts->where('id',$thread->id)->first()->author }}
                @if($thread->posts->where('id',$thread->id)->first()->post_lols->count() > 0)
                    @foreach($thread->posts->where('id',$thread->id)->first()->post_lols as $lol)
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
            </h5>
        </div>
        <div class="col-xs-7 col-sm-offset-4 col-sm-4 text-right">
            <small class="text-muted">{{ $thread->date }}</small>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            {!! $thread->posts->where('id',$thread->id)->first()->body !!}
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 text-right">
            @if(isset($singleThread))
                <?php
                    $hyperlinkTarget = "https://www.shacknews.com/chatty?id=" . $thread->id ."#item_" . $thread->posts->where('id',$thread->id)->first()->id;
                ?>
                <a href={!! $hyperlinkTarget !!}>{{ $thread->id }}</a>
            @else
                <a href="threads/{{ $thread->id }}">{{ $thread->id }}</a>
            @endif
        </div>
    </div>
</li>
@endif