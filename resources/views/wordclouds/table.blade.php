@extends('layouts.app')

@section('content')

<script>
function toggleWordCloudQuickEdit() {
    var x = document.getElementById("quick-edit");
    if(x.style.display === "none") {
        x.style.display = "flex";
    } else {
        x.style.display = "none";
    }
}
</script>

<div class="container">

    @include('returnMessage')
    @include('errors')

    @if($displayMsg)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                {!! $displayText !!}
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="row">
                    <div class="col-md-6" style="z-index:2;">
                        <h5>Word Cloud for {{ $wordCloud->user }}</h5>
                        <p>{{ $from->format('M j Y') }} - {{ $to->format('M j Y') }} ({{ $diffStr }})<br />
                        @if($sentEnabled)
                            {{ $sentCounts["pos"] }} <span style="background-color:PaleGreen;">positive</span>, {{ $sentCounts["neg"] }} <span style="background-color:Tomato;color:white;">negative</span> and {{ $sentCounts["neu"] }} neutral words.<br />
                        @endif
                        {{ count($wordCloudWork) }} words over {{ $wordCloud->post_count }} posts.
                        @if( $wordCloud->created_by == $dailyUser)
                        <br />Created {{ \Carbon\Carbon::parse($wordCloud->created_at)->diffForHumans() }}
                        @endif
                        </p>
                        <div class="row" style="display:flex;">
                            <div class="col">
                                Terms Mean<br />{{ round($statsArr["term"]["mean"],2) }}<br />
                                Freq. Mean<br />{{ round($statsArr["freq"]["mean"],2) }}
                            </div>
                            <div class="col">
                                Terms Median<br />{{ round($statsArr["term"]["median"],2) }}<br />
                                Freq. Median<br />{{ round($statsArr["freq"]["median"],2) }}
                            </div>
                            <div class="col">
                                Terms Std. Dev.<br />{{ round($statsArr["term"]["stdDev"],2) }}<br />
                                Freq. Std. Dev.<br />{{ round($statsArr["freq"]["stdDev"],2) }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-right" style="z-index:2;">
                        <div class="row">
                            @can('update',$wordCloud)
                            <div class="col col-md-12">
                                <button type="button" style="padding:0px;" class="btn btn-link" onclick="toggleWordCloudQuickEdit()">Quick Edit</button>
                            </div>
                            <div class="col col-md-12">
                                <a href="{{ route('wordclouds.edit', $wordCloud->id) }}">Edit Cloud</a>
                            </div>
                            @endcan
                            @if($wordCloud->share_cloud == 'Anyone')
                            <div class="col col-md-12">
                                <a href="{{ route('wordclouds.show',['wordcloud' => $wordCloud->id]) }}">View Cloud</a>
                            </div>
                            @else
                                @can('viewCloud',$wordCloud)
                                <div class="col col-md-12">
                                    <a href="{{ route('wordclouds.show',['wordcloud' => $wordCloud->id]) }}">View Cloud</a>
                                </div>
                                @endcan
                            @endif
                            <div class="col col-md-12">
                                <a href="{{ route('wordclouds.downloadCSV',['wordcloud' => $wordCloud->id]) }}">Download .CSV</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @can('update',$wordCloud)
        <div class="row" id="quick-edit" style="display:none;">
            <div class="col-md-10 offset-md-1" style="z-index:2;">
                <form method="POST" action={{ route('wordclouds.update', ["wordcloud" => $wordCloud]) }} >
                    @csrf
                    @method('PUT')
                    
                    <div class="form-group row">
                        <div class="col-sm-3">
                            <select class="form-control" name="inlineWordFilter">
                                @foreach($wordCloudFilters as $wordFilter)
                                    <option @if($wordCloud->word_cloud_filter == $wordFilter->id) selected="selected" @endif>{{ $wordFilter->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select class="form-control" name="inlineWordColorset">
                                @foreach($wordCloudColorsets as $colorset)
                                    <option @if($wordCloud->word_cloud_colorset == $colorset->id) selected="selected" @endif>{{ $colorset->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <button type="submit" class="btn btn-primary divTableButton" name="btnSave" @cannot('update',$wordCloud) disabled @endcannot>
                                {{ __('Save') }}
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
        @endcan

        <div class="row mt-3">
            <div class="col-md-10 offset-md-1">

                <div class="divTable">
                    <div class="divTableHeading">
                        <div class="divTableRow">
                            <div class="divTableHead divNoRightBorder">Word</div>
                            <div class="divTableHead divNoRightBorder">Count</div>
                            <div class="divTableHead divNoRightBorder">Score</div>
                            <div class="divTableHead divNoRightBorder">Freq</div>
                            <div class="divTableHead">Computed Score</div>
                        </div>
                    </div>
                    <div class="divTableBody">
                        @if(count($wordCloudWork) > 0)
                            @foreach($wordCloudWork as $wordCloudTerm)
                                @php
                                    $backgroundColor = "white";
                                @endphp
                                @if(App\Chatty\app_setting::displayWordCloudSentiment())
                                    @php
                                        switch($wordCloudTerm->sentiment) {
                                            case "pos" :
                                                $backgroundColor = "#CEF6D8";
                                                break;
                                            case "neg":
                                                $backgroundColor = "#F8E0E0";
                                                break;
                                            default:
                                                
                                                break;
                                        }
                                    @endphp
                                @endif

                            <div class="divTableRow">
                                <div class="divTableCell divNoRightBorder divNoTopBorder" style="background-color: {{ $backgroundColor }};">
                                    {{ $wordCloudTerm->term }}
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $wordCloudTerm->count }}
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $wordCloudTerm->score }}
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $wordCloudTerm->doc_freq }}
                                </div>
                                <div class="divTableCell divNoTopBorder">
                                    {{ $wordCloudTerm->computed_score }}
                                </div>
                            </div>
                            @endforeach
                        @endif
                    </div>
                </div>

            </div>
        </div>

    @endif

</div>
@endsection