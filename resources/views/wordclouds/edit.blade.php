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
        <div class="row justify-content-center">
            <div class="col-md-10 mb-3">
                <div class="card">
                    <div class="card-header">{{ __('Edit Word Cloud') }}</div>

                    <div class="card-body">
                        <form method="POST" action={{ route('wordclouds.update', ["wordcloud" => $cloud]) }} >
                            @csrf
                            @method('PUT')

                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Cloud ID</h6>
                                </div>
                                <div class="col-sm">
                                    <p>@php echo substr($cloud->id,-8) @endphp</p>
                                </div>
                            </div>
                            @can('createForOthers',App\Chatty\word_cloud::class)
                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">User</h6>
                                </div>
                                <div class="col-sm">
                                    <p>{{ $cloud->user }}</p>
                                </div>
                            </div>
                            @endcan
                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Status</h6>
                                </div>
                                <div class="col-sm">
                                    <p>{{ $cloud->status }} ({{ $cloud->percent_complete }}%)</p>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Date Range</h6>
                                </div>
                                <div class="col-sm">
                                    <p>
                                        {{ $from }} &nbsp; <small>to</small> &nbsp; {{ $to }} ({{ $diffInDays }} days)
                                    </p>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Date Created</h6>
                                </div>
                                <div class="col-sm">
                                    <p>{{ $cloud->created_at }} UTC</p>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Phrases?</h6>
                                </div>
                                <div class="col-8">
                                    <p><input type="checkbox" name="chkPhrases" value="chkPhrases" disabled {{ $cloud->generate_phrases ? 'checked' : '' }}></p>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Word Filter<br /><small id="word-filter-description">{{ $defaultDescr }}</small></h6>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control" id="word-filter" name="wordFilter" value="{{ old('wordFilter') }}" onchange="updateWordCloudFilterDescr()">
                                        @foreach($wordCloudFilters as $wordFilter)
                                            <option @if($cloud->word_cloud_filter == $wordFilter->id) selected="selected" @endif data-descr="{{ $wordFilter->descr }}">{{ $wordFilter->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Colorset<br /><small id="word-colorset-description">{{ $defaultColorsetDescr }}</small></h6>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control" id="colorset" name="wordColorset" value="{{ old('wordColorset') }}" onchange="updateWordCloudcolorsetDescr()">
                                        @foreach($wordCloudColorsets as $colorset)
                                            <option @if($cloud->word_cloud_colorset == $colorset->id) selected="selected" @endif data-descr="{{ $colorset->descr }}">{{ $colorset->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Cloud Permissions<br /><small>(Who can view cloud & save PNG?)</small></h6>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control" id="sharing-view" name="viewPermissions" value="{{ old('viewPermissions') }}">
                                        <option @if($view == 'Self') selected="selected" @endif>Self</option>
                                        <option @if($view == 'Chatty') selected="selected" @endif>Chatty</option>
                                        <option @if($view == 'Anyone') selected="selected" @endif>Anyone</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 text-md-right">
                                    <h6 style="font-weight:bold;">Table Permissions<br /><small>(Who can view table & save CSV?)</small></h6>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control" id="download-view" name="downloadPermissions" value="{{ old('downloadPermissions') }}">
                                        <option @if($download == 'Self') selected="selected" @endif>Self</option>
                                        <option @if($download == 'Chatty') selected="selected" @endif>Chatty</option>
                                        <option @if($download == 'Anyone') selected="selected" @endif>Anyone</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 offset-md-4">
                                    <button type="submit" class="btn btn-primary divTableButton" name="btnSave" @cannot('update',$cloud) disabled @endcannot>
                                        {{ __('Save') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                            @if($cloud->status == "Success")
                            <div class="form-group row">
                                <div class="col-md-4 offset-md-4">
                                    <form action={{ route('wordclouds.destroy', ["wordcloud" => $cloud]) }} method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger divTableButton" name="btnDelete" @cannot('delete',$cloud) disabled @endcannot>{{ __('Delete')}}</button>
                                    </form>
                                </div>
                            </div>
                            @endif

                        <div class="mt-3">
                            <a href="{{ route('wordclouds') }}">< Return to Word Clouds</a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    @endif

        

</div>

@endsection