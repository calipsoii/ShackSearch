@extends('layouts.app')

@section('content')
<div class="container">

    @cannot('viewAll',App\Chatty\dbAction::class)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view Logs.
            </div>
        </div>
    @else

        <div class="row mb-3">
            <div class="col-md">
                <div class="list-group">
                    @if(count($dbActions) > 0)
                        @foreach($dbActions as $dbAction)
                            @include('partials.dbActionlistitem')
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <!-- Pagination links -->
        <div class="row">
            <div class="col-sm-12">
                {{ $dbActions->links() }}
            </div>
        </div>

    @endcannot

</div>
@endsection