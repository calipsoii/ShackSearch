@extends('layouts.app')

@section('content')
    <div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('viewAll',App\Chatty\app_setting::class)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view App Settings.
            </div>
        </div>
    @else

        <!-- First row of setting cards -->
        <div class="row">

            <!-- Application Settings -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Application Settings</h5>
                        <form action="{{ route('appsettings') }}" method="POST">
                        @csrf
                            <div class="form-group row">
                                <label for="subthread-truncate-length" class="col-md-7 col-form-label">Subthread Truncate Length</label>
                                <div class="col">
                                    <input type="text" class="form-control" id="subthread-truncate-length" name="subthreadTruncateLength" value="{{ old('subthreadTruncateLength', $appsettings->subthread_truncate_length) }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="subthreads-to-display" class="col-md-7 col-form-label">Subthreads to Display</label>
                                <div class="col">
                                    <input type="text" class="form-control" id="subthreads-to-display" name="subthreadsToDisplay" value="{{ old('subthreadsToDisplay', $appsettings->chatty_view_subthreads_to_display) }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="hours-to-display" class="col-md-7 col-form-label">Hours to Display Thread</label>
                                <div class="col">
                                    <input type="text" class="form-control" id="hours-to-display" name="hoursToDisplay" value="{{ old('hoursToDisplay', $appsettings->chatty_view_hours_to_display_thread) }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="events-per-page" class="col-md-7 col-form-label">Events Per Page (pagination)</label>
                                <div class="col">
                                    <input type="text" class="form-control" id="events-per-page" name="eventsPerPage" value="{{ old('eventsPerPage', $appsettings->events_to_display_per_page) }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="logs-per-page" class="col-md-7 col-form-label">Logs Per Page (pagination)</label>
                                <div class="col">
                                    <input type="text" class="form-control" id="logs-per-page" name="logsPerPage" value="{{ old('logsPerPage', $appsettings->logs_to_display_per_page) }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="logging-level" class="col-md-7 col-form-label">Logging Level (1:minimal; 5:verbose)</label>
                                <div class="col">
                                    <input type="text" class="form-control" id="logging-level" name="loggingLevel" value="{{ old('loggingLevel', $appsettings->logging_level) }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="monitor-user" class="col-md-7 col-form-label">Monitoring User</label>
                                <div class="col">
                                    <input type="text" class="form-control" id="monitor-user" name="monitorUser" value="{{ old('monitorUser', $appsettings->monitor_username) }}">
                                </div>
                            </div>
                            <div class="form-group form-check">
                                <input class="form-check-input" type="checkbox" id="allow-winchatty-regs" name="allowWinchattyRegs" value="allowWinchattyRegs" {{ $appsettings->winchatty_registration_allowed ? 'checked' : '' }}>
                                <label class="form-check-label" for="allow-winchatty-regs">Allow Winchatty Authentication</label>
                            </div>
                            <div class="form-group row">
                                <label for="proxy-password" class="col-md-7 col-form-label">Proxy Password:<br /><small>(Enter value to update, else leave blank)</small></label>
                                <div class="col">
                                    <input type="password" class="form-control" id="proxy-password" name="proxyPassword" value="{{ old('proxyPassword', '') }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="proxy-email" class="col-md-7 col-form-label">Proxy Email:<br /><small>(Enter value to update, else leave blank)</small></label>
                                <div class="col">
                                    <?php
                                        $displayEmail = '';
                                        if(Auth::user()->can('update',$appsettings)) {
                                            $displayEmail = $appsettings->proxy_email;
                                        }
                                    ?>
                                    <input type="text" class="form-control" id="proxy-email" name="proxyEmail" value="{{ old('proxyEmail', $displayEmail) }}">
                                </div>
                            </div>
                            <button type="submit" name="applicationSettings" class="btn btn-primary" @cannot('update',$appsettings) disabled @endcannot>Save Settings</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                
                <!-- Cleanup Settings -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Cleanup Settings</h5>
                                <p>Scheduled job runs each morning at 02:00.</p>
                                <form action="{{ route('appsettings') }}" method="POST">
                                @csrf
                                    <div class="form-group row">
                                        <label for="events-days-to-keep" class="col-md-5 col-form-label">Events Days to Keep:<br /><small>({{ $eventsToDelete }} deletions pending)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="events-days-to-keep" name="eventsDaysToKeep" value="{{ old('eventsDaysToKeep',$appsettings->events_days_to_keep) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="logs-days-to-keep" class="col-md-5 col-form-label">Logs Days to Keep:<br /><small>({{ $logsToDelete }} deletions pending)</small></label>
                                        <div class="col-md">
                                            <input type="text" class="form-control" id="logs-days-to-keep" name="logsDaysToKeep" value="{{ old('logsDaysToKeep',$appsettings->logs_days_to_keep) }}">
                                        </div>
                                    </div>
                                    <button type="submit" name="cleanupSettings" class="btn btn-primary" @cannot('update',$appsettings) disabled @endcannot>Save Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Winchatty Utilities -->
                    <div class="col-sm-12">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Winchatty Utilities</h5>
                                <form action="{{ route('appsettings') }}" method="POST">
                                @csrf
                                    <div class="form-group row">
                                        <label for="gzip-ssl-test" class="col-md-7 col-form-label">GZIP and SSL Verification</label>
                                        <div class="col">
                                            <button type="submit" id="gzip-ssl-test"name="btnCheckGzipSSL" class="btn btn-primary" @cannot('update',$appsettings) disabled @endcannot>Verify Settings</button>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="winchatty-username" class="col-md-7 col-form-label">Username</label>
                                        <div class="col">
                                            <input type="text" class="form-control" id="winchatty-username" name="winchattyUsername" value="{{ old('winchattyUsername') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="winchatty-password" class="col-md-7 col-form-label">Password</label>
                                        <div class="col">
                                            <input type="password" class="form-control" id="winchatty-password" name="winchattyPassword" value="{{ old('winchattyPassword') }}">
                                        </div>
                                    </div>
                                    <button type="submit" name="btnWinchattyLoginTest" class="btn btn-primary" @cannot('update',$appsettings) disabled @endcannot>Test Credentials</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

    @endcannot
</div>

@endsection