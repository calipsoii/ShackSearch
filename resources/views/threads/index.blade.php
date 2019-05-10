@extends('layouts.app')

@section('content')
<div class="container">

    @include('returnMessage')
    @include('errors')

    @can('viewAdmin',App\Chatty\thread::class)
        <div class="row">
            <div class="col-sm-12">

                <!-- Import Thread card -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Import Thread(s)</h5>
                        <p>Retrieve thread(s) from WinChatty, overwriting them if they exist in local DB. Separate multiple thread ID's with commas. Limit: 50.</p>
                        <form action="{{ route('threads') }}" method="POST">
                            @csrf
                            <div class="form-group row">
                                <label for="threads-to-retrieve" class="col ml-md-3">Thread IDs to Retrieve:</label>
                                <div class="col-md-9 mr-md-3">
                                    <input type="text" class="form-control" id="threads-to-retrieve" name="threadsToRetrieve" value="{{ old('threadsToRetrieve') }}">
                                </div>
                            </div>
                            <div class="input-group">
                                <div class="input-group-btn">
                                    <button class="btn btn-primary" type="submit" name="importThreads" @cannot('create',App\Chatty\thread::class) disabled @endcannot>
                                        <i></i>Import Thread(s)
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    @endcan

    <!-- Collapsible Thread listing -->
    <div class="row mb-3">
        <div class="col-md">
            <div class="list-group">
                @if(count($threads) > 0)
                    @foreach($threads as $thread)
                        @include('partials.threadlistitem')
                    @endforeach
                @endif
            </div>
        </div>
    </div>

</div>
@endsection