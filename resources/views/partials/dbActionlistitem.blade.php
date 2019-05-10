<li title="{{ $dbAction->created_at }}" class="list-group-item">

    <div class="row">
        <div class="col-6 col-md-2 order-1 order-md-1">
            <h6 style="color:crimson;display:inline;margin-right:1em;">{{ $dbAction->username }}</h6>
        </div>
        <div class="col-12 col-md order-3 order-md-2">
            <?php
                $message = $dbAction->message;
                echo preg_replace('#<a.*?>([^>]*)</a>#i', '$1', $message);
            ?>
        </div>
        <div class="col-6 col-md-2 col-lg-2  order-2 order-md-3 text-right">
            <small>{{ \Carbon\Carbon::parse($dbAction->created_at)->diffForHumans() }}</small>
        </div>
    </div>


</li>