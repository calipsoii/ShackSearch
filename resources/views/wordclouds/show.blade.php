@extends('layouts.app')

@section('content')

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
                            @if($wordCloud->share_table_download == 'Anyone')
                            <div class="col col-md-12">
                                <a href="{{ route('wordclouds.table',['wordcloud' => $wordCloud->id]) }}">View Table</a>
                            </div>
                            @else
                                @can('viewTable',$wordCloud)
                                <div class="col col-md-12">
                                    <a href="{{ route('wordclouds.table',['wordcloud' => $wordCloud->id]) }}">View Table</a>
                                </div>
                                @endcan
                            @endif
                            <div class="col col-md-12">
                                <button type="button" style="padding:0px;" class="btn btn-link" onclick="saveCloudAsPNG()">Show PNG</button>
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
                                @php
                                    $currentFilterName = null;
                                @endphp
                                @foreach($wordCloudFilters as $wordFilter)
                                    <option @if($wordCloud->word_cloud_filter == $wordFilter->id) selected="selected" @php $currentFilterName = $wordFilter->name; @endphp @endif>{{ $wordFilter->name }}</option>
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

        <div id="word-cloud-div" class="row">
            <div class="col">
                <div class="row">
                    <div class="col-12" style="height:70vh;width:100%;" id="word-cloud">
                        <!-- https://github.com/SeregPie/VueWordCloud -->
                        <vue-word-cloud
                            :words="{!! $wordCloudString !!}"
                            :color="{!! $colorString !!}"
                            :spacing="0.15"
                            :animation-duration="0"
                            font-family="Times New Roman">
                            <template slot-scope="{word,text,weight}">
                                <div style="cursor: pointer;" :title="text + ': ' + weight" @click="onWordClick(text,'{{ $from->format('Y-m-d') }}','{{ $to->format('Y-m-d') }}','{{ urlencode($wordCloud->user) }}','{{ urlencode($dailyUser) }}')">
                                    @{{ text }}
                                </div>
                            </template>
                        </vue-word-cloud>
                    </div>
                    <div class="col-12">
                        <div class="row text-sm-center" id="word-cloud-info" style="font-size:x-small;display:none;">
                            <div class="col-sm order-sm-2">
                                {{ $from->format('M j Y') }} - {{ $to->format('M j Y') }} ({{ $diffStr }})
                            </div>
                            <div class="col-sm order-sm-1">
                                {{ $wordCloud->user }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3" id="word-cloud-png-row" style="display:none;">
            <div class="col-md" id="word-cloud-png">
                <p>Right-click (or tap and hold) to save this .PNG image to your device. Click <button type="button" style="padding:0px;" class="btn btn-link" onclick="hideCloudPNGDiv()">here</button> to close.</p>
            </div>
        </div>
    @endif
</div>
@endsection