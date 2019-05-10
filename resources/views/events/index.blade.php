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

        
        <div class="row">

            <!-- Event Poll Settings card -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Event Poll Settings</h5>
                        <p>Events automatically retrieved from Winchatty and processed to populate local DB with new actions. Task scheduled every 1 minute.</p>
                        
                        <div class="row mt-2 mb-2 justify-content-center">
                            <div class="col-sm-10">
                                
                                <p><span class="mr-4">Last polled:</span>{{ \Carbon\Carbon::parse($lastEventPoll)->diffForHumans() }}</p>
                            </div>
                        </div>

                        <form action="{{ url('/events') }}" method="POST">
                            <div class="form-group">
                            {{ csrf_field() }}
                                <div class="form-group row">
                                    <label for="event-poll-username" class="col-md-5 col-form-label">Event Poll Username</label>
                                    <div class="col">
                                        <input type="text" class="form-control" id="event-poll-username" name="eventPollUsername" value="{{ old('eventPollUsername', $appsettings->event_poll_username) }}">
                                    </div>
                                </div>
                                <div class="form-group form-check">
                                    <input class="form-check-input" type="checkbox" id="event-poll-enabled-flag" name="eventPollEnabled" value="eventPollEnabled" {{ $appsettings->event_poll_enabled ? 'checked' : '' }}>
                                    <label class="form-check-label" for="event-poll-enabled-flag">Automatically Poll for Events</label>
                                </div>
                                <div class="form-group form-check">
                                    <input class="form-check-input" type="checkbox" id="actively-create-flag" name="activelyCreateflag" value="activelyCreateflag" {{ $appsettings->actively_create_threads_posts ? 'checked' : '' }}>
                                    <label class="form-check-label" for="actively-create-flag">Actively Retrieve Missing Posts and Threads</label>
                                </div>
                                <div class="row">
                                    <div class="col-sm-7">
                                        <button type="submit" name="saveEventSettings" class="btn btn-primary" @cannot('update',$appsettings) disabled @endcannot>Save Settings</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Event Manual Poll card -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Manual Event Poll</h5>
                        <p>Manually retrieve events from Winchatty starting at this number. Technical limit: 10k events. Functional limit: 1k.</p>
                        <form action="{{ route('events') }}" method="POST">
                            @csrf
                            <div class="form-group row">
                                <label for="last-event-id" class="col-md-4 col-form-label">Last Event ID:</label>
                                <div class="col">
                                    <input type="text" class="form-control" id="last-event-id" name="eventId" value="{{ old('eventId', $appsettings->last_event_id) }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-sm-8">
                                    <button type="submit" name="importEvents" class="btn btn-primary" @cannot('create',App\Chatty\event::class) disabled @endcannot>Import Events</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Collapsible Event listing -->
        <div class="row mb-3">
            <div class="col-md">
                <div class="list-group">
                    @if(count($events) > 0)
                        @foreach($events as $event)
                            @include('partials.eventlistitem')
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <!-- Pagination links -->
        <div class="row">
            <div class="col-sm-12">
                {{ $events->links() }}
            </div>
        </div>

    @endcannot

</div>

@endsection