@extends('layouts.app')

@section('content')
<div class="container">

    @include('returnMessage')
    @include('errors')

    <div class="row mb-3">
        <div class="col-md">
            <div class="list-group">
                @if(count($threads) > 0)
                    @foreach($threads as $thread)
                        @include('partials.chattylistitem')
                    @endforeach
                @endif
            </div>
        </div>
    </div>

</div>
@endsection