@extends('layouts.app')

@section('content')
<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('viewAll',App\Chatty\word_cloud_colorset::class)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view Word Cloud Colorsets.
            </div>
        </div>
    @else

        
        <!-- Non-table fluid view -->
        <div class="row">
            <div class="col-md">

                <div class="list-group">
                    <li class="list-group-item d-none d-md-block"> 
                        <div class="row" style="font-weight:bold;">
                            <div class="col-2">
                                Name
                            </div>
                            <div class="col">
                                Description
                            </div>
                            <div class="col-1">
                                Default
                            </div>
                            <div class="col-1">
                                Active
                            </div>
                        </div>
                    </li>

                    @if(count($colorsets) > 0)
                        @foreach($colorsets as $colorset)
                        <li class="list-group-item">
                            <div class="row">
                                <div class="order-1 order-md-1 col-12 col-md-2">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Name: </span><a href="{{ route('colorsets.edit',$colorset->id) }}">{{ $colorset->name }}</a>
                                </div>
                                <div class="order-2 order-md-2 col-12 col-md">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Description: </span>{{ $colorset->descr }}
                                </div>
                                <div class="order-4 order-md-3 col-6 col-md-1">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Default: </span>
                                    <input type="checkbox" disabled {{ $colorset->is_default ? 'checked' : '' }}>
                                </div>
                                <div class="order-3 order-md-4 col-6 col-md-1">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Active: </span>
                                    <input type="checkbox" disabled {{ $colorset->active ? 'checked' : '' }}>
                                </div>
                            </div>
                        </li>
                        @endforeach
                    @endif
                    <li class="list-group-item">
                            <form class="form-group row"action="{{ route('colorsets') }}" method="POST">
                            @csrf
                                <div class="order-1 order-md-1 col-12 col-md-2">
                                    <label class="d-md-none mr-3 col-form-label" for="colorsetName" style="font-weight: bold;">Name: </label>
                                    <input class="form-control" type="text" id="colorsetName" name="colorsetName" value="{{ old('colorsetName') }}" style="width:100%;" required>
                                </div>
                                <div class="order-2 order-md-2 col-12 col-md">
                                    <label class="d-md-none mr-3 col-form-label" for="colorsetDescr" style="font-weight: bold;">Description: </label>
                                    <input class="form-control" type="text" id="colorsetDescr" name="colorsetDescr" value="{{ old('colorsetDescr') }}" style="width:100%;" required>
                                </div>
                                <div class="order-5 order-md-5 col-5 col-md-2 mt-3 mt-md-0">
                                    <button type="submit" class="btn btn-success divTableButton" name="btnAdd" @cannot('create',App\Chatty\word_cloud_colorset::class) disabled @endcannot>{{ __('Create') }}</button>
                                </div>
                            </form>
                    </li>
                </div>

            </div>
        </div>

    @endcannot

</div>
@endsection