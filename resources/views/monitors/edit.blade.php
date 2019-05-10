@extends('layouts.app')

@section('content')

<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('view',$monitor)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view this Monitor.
            </div>
        </div>
    @else
        <div class="row justify-content-center">
            <div class="col-md-8 mb-3">
                <div class="card">
                    <div class="card-header">{{ __('Edit Monitor') }}</div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('monitors.update', $monitor->id) }}">
                        @csrf
                        @method('PUT')

                            <!-- Form for editing existing monitor -->
                            <div class="form-group row">
                                <label for="edit-monitor-name"  class="col-md-4 col-form-label text-md-right">{{ __('Name') }}</label>
                                <div class="col-md-8">
                                    <input id="edit-monitor-name" type="text" class="form-control{{ $errors->has('name') ? 'is-invalid' : '' }}" name="name" value="{{ old('name', $monitor->name) }}" required>
                                    @if ($errors->has('name'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('name') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="edit-monitor-descr"  class="col-md-4 col-form-label text-md-right">{{ __('Description') }}</label>
                                <div class="col-md-8">
                                    <input id="edit-monitor-descr" type="text" class="form-control{{ $errors->has('descr') ? 'is-invalid' : '' }}" name="descr" value="{{ old('descr', $monitor->descr) }}" required>
                                    @if ($errors->has('descr'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('descr') }}</strong>
                                        </span>
                                    @endif
                                    </div>
                            </div>
                            <div class="form-group row">
                                <label for="mins-before-alert"  class="col-md-4 col-form-label text-md-right">{{ __('Minutes Before Alert') }}<br /><small>(Alert after this many minutes)</small></label>
                                <div class="col-md-8">
                                    <input id="mins-before-alert" type="text" class="form-control{{ $errors->has('mins-before-alert') ? 'is-invalid' : '' }}" name="mins-before-alert" value="{{ old('mins-before-alert', $monitor->max_mins_since_task_last_exec) }}">
                                    @if ($errors->has('mins-before-alert'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('mins-before-alert') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="run-freq"  class="col-md-4 col-form-label text-md-right">{{ __('Monitor Run Frequency') }}<br /><small>(Informational: set in kernel)</small></label>
                                <div class="col-md-8">
                                    <input id="run-freq" type="text" class="form-control{{ $errors->has('run_freq_mins') ? 'is-invalid' : '' }}" name="run-freq" value="{{ old('run-freq', $monitor->run_freq_mins) }}">
                                    @if ($errors->has('run_freq_mins'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('run_freq_mins') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 col-form-label text-md-right">{{ __('Monitor Last Executed') }}</div>
                                <div class="col-md-8">{{ \Carbon\Carbon::parse($monitor->last_run)->diffForHumans() }}</div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 col-form-label text-md-right">{{ __('Monitor Alert Status') }}</div>
                                <div class="col-md-8">
                                    @if($monitor->last_run_alert_state)
                                        <span style="background-color:red;color:white;">true</span>
                                    @else
                                        <span style="background-color:green;color:white;">false</span>
                                    @endif</div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 col-form-label text-md-right">{{ __('Monitor Email Sent') }}</div>
                                <div class="col-md-8">
                                    @if($monitor->last_run_email_sent)
                                        <span style="background-color:red;color:white;">true</span>
                                    @else
                                        <span style="background-color:green;color:white;">false</span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="monitor-enabled" class="col-4 col-form-label text-md-right">{{ __('Enabled?') }}</label>
                                <div class="col-4">
                                    <input class="ml-1 mr-3" type="checkbox" id="monitor-enabled" name="enabled" value="enabled" {{ $monitor->enabled ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-4 offset-md-4">
                                    <button type="submit" class="btn btn-primary divTableButton" @cannot('update',$monitor) disabled @endcannot>
                                        {{ __('Save') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="form-group row">
                            <div class="col-md-4 offset-md-4">
                                <form action="{{ route('monitors.destroy', ['monitor' => $monitor->id]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger divTableButton" name="btnDelete" onclick="return confirm('Are you sure you want to delete monitor {{ $monitor->name }}?');" @cannot('delete',$monitor) disabled @endcannot>{{ __('Delete')}}</button>
                                </form>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('monitors') }}">< Return to Monitors</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endcannot
</div>

@endsection