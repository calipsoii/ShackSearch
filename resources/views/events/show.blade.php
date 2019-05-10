@extends('layouts.app')

@section('content')

<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('viewAll',App\Chatty\event::class)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view Events.
            </div>
        </div>
    @else

        <div class="row justify-content-center">
            <div class="col-md-10 mb-3">
                <div class="card">
                    <div class="card-header">{{ __('Event Details') }}</div>

                    <div class="card-body">

                        <div class="row">
                            <div class="col-md-3 text-md-right">
                                <h6 style="font-weight:bold;">Event ID</h6>
                            </div>
                            <div class="col-sm">
                                <p>{{ $event->event_id }}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 text-md-right">
                                <h6 style="font-weight:bold;">Event Type</h6>
                            </div>
                            <div class="col-sm">
                                <p>{{ $event->event_type }}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 text-md-right">
                                <h6 style="font-weight:bold;">Event Date</h6>
                            </div>
                            <div class="col-sm">
                                <p>{{ $event->event_date }} UTC</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 text-md-right">
                                <h6 style="font-weight:bold;">Event Processed?</h6>
                            </div>
                            <div class="col-sm">
                                <p>
                                    @if ($event->processed)
                                        <span style="color:green;font-weight:bold;">TRUE</span>
                                    @else
                                        <span style="color:red;font-weight:bold;">FALSE</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 text-md-right">
                                <h6 style="font-weight:bold;">Processed Date</h6>
                            </div>
                            <div class="col-sm">
                                <p>{{ $event->created_at }} UTC</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 text-md-right">
                                <h6 style="font-weight:bold;">Raw Event Data</h6>
                            </div>
                            <div class="col-sm-9">
                                <p>{{ $event->event_data }}</p>
                            </div>
                        </div>

                        <!-- Return to Events link -->
                        <div class="mt-3">
                            <a href="{{ route('events') }}">< Return to Events</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
    @endcannot

</div>

@endsection