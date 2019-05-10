<?php
    $eventColor = NULL;
    switch($event->event_type)
    {
        case "newPost":
            $eventColor = 'blue';
            break;
        case "lolCountsUpdate":
            $eventColor = '#EC7E01';
            break;
        case "categoryChange":
            $eventColor = 'crimson';
            break;
        default:
            $eventColor = NULL;
            break;
    }
?>

<a title="{{ $event->event_id }}" href="{{ route('events.show', ['event' => $event]) }}" class="list-group-item list-group-item-action">

    <div class="row">
        <div class="col-6 col-md-2 order-1 order-md-1">
            <h6 style="display:inline;margin-right:1em;color:{{ $eventColor }};">{{ $event->event_type }}</h6>
        </div>
        <div class="col-12 col-md order-3 order-md-2 event-list-item-body">
            <p style="word-break: break-all;white-space: normal;">{{ trim(strip_tags($event->event_data)) }}</p>
        </div>
        <div class="col-6 col-md-2 col-lg-2  order-2 order-md-3 text-right">
            <small>{{ \Carbon\Carbon::parse($event->created_at)->diffForHumans() }}</small>
        </div>
    </div>

</a>