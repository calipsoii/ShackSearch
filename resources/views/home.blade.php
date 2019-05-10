@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col text-center mb-2" style="z-index:2;">
            <h5><a href="{{ route('wordclouds.show',['wordcloud' => $wordCloud->id]) }}">Daily Chatty</a></h5>
            <small>What have Shackers been talking about over the last {{ $dailyCloudHours }} hours?</small>
        </div>
    </div>
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
            </div>
        </div>
    </div>
</div>
@endsection
