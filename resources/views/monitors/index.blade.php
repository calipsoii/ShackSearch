@extends('layouts.app')

@section('content')
<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('viewAll',App\Chatty\monitor::class)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view Monitors.
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
                                Task
                            </div>
                            <div class="col-2">
                                Status
                            </div>
                            <div class="col">
                                Description
                            </div>
                            <div class="col-1">
                                Enabled
                            </div>
                            <div class="col-2">
                                Last Run
                            </div>
                        </div>
                    </li>

                    @if(count($monitors) > 0)
                        @foreach($monitors as $monitor)
                        <li class="list-group-item">
                            <div class="row">
                                <div class="order-1 order-md-1 col-12 col-md-2">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Task: </span><a href="{{ route('monitors.edit',$monitor->id) }}">{{ $monitor->name }}</a>
                                </div>
                                <div class="order-2 order-md-3 col-12 col-md">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Description: </span>{{ $monitor->descr }}
                                </div>
                                <div class="order-4 order-md-2 col-6 col-md-2">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Status: </span>
                                    @if($monitor->last_run_alert_state)
                                        <span style="background-color:red;color:white;">Error</span>
                                    @else
                                        <span style="background-color:green;color:white;">Success</span>
                                    @endif
                                </div>
                                <div class="order-5 order-md-4 col-6 col-md-1">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Enabled: </span>
                                    <input type="checkbox" disabled {{ $monitor->enabled ? 'checked' : '' }}>
                                </div>
                                <div class="order-3 order-md-5 col-12 col-md-2">
                                    <span class="d-inline d-md-none mr-3" style="font-weight: bold;">Last Run: </span>{{ \Carbon\Carbon::parse($monitor->last_run)->diffForHumans() }}
                                </div>
                            </div>
                        </li>
                        @endforeach
                    @endif
                    <li class="list-group-item">
                            <form class="form-group row"action="{{ route('monitors') }}" method="POST">
                            @csrf
                                <div class="order-1 order-md-1 col-12 col-md-2">
                                    <label class="d-md-none mr-3 col-form-label" for="monitorName" style="font-weight: bold;">Name: </label>
                                    <input class="form-control" type="text" id="monitorName" name="monitorName" value="{{ old('monitorName') }}" style="width:100%;" required>
                                </div>
                                <div class="order-2 order-md-2 col-12 col-md">
                                    <label class="d-md-none mr-3 col-form-label" for="monitorDescr" style="font-weight: bold;">Description: </label>
                                    <input class="form-control" type="text" id="monitorDescr" name="monitorDescr" value="{{ old('monitorDescr') }}" style="width:100%;" required>
                                </div>
                                <div class="order-5 order-md-5 col-5 col-md-2 mt-3 mt-md-0">
                                    <button type="submit" class="btn btn-success divTableButton" name="btnAdd" @cannot('create',App\Chatty\monitor::class) disabled @endcannot>{{ __('Create') }}</button>
                                </div>
                            </form>
                    </li>
                </div>

            </div>
        </div>

    @endcannot

</div>
@endsection