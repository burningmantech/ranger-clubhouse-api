<x-html-email>
    <h2>Daily Report for {{date('Y-m-d')}}</h2>

    <p>
        Report generated at {{now()}} (server time UTC-7)
    </p>
    <h3>Error Logs ({{count($errorLogs)}})</h3>
    @if (count($errorLogs) > 0)
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Timestamp (UTC-7)</th>
                <th>Person</th>
                <th>Type</th>
                <th>IP</th>
                <th>URL</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($errorLogs as $log)
                <tr>
                    <td>{{$log->created_at}}</td>
                    <td>
                        @if ($log->person)
                            {{$log->person->callsign}}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        {{$log->error_type}}
                    </td>
                    <td>
                        {{$log->ip}}
                    </td>
                    <td>
                        {{$log->url}}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>No errors were logged.</p>
    @endif
    <h3>Failed Background Jobs ({{count($failedJobs)}})</h3>
    @if (count($failedJobs) > 0)
        <table class="table table-sm table-striped table-width-auto">
            <thead>
            <tr>
                <th>Timestamp (UTC-7)</th>
                <th>UUID</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($failedJobs as $job)
                <tr>
                    <td>{{$job->failed_at}}</td>
                    <td>{{$job->uuid}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>
            No failed jobs.
        </p>
    @endif

    <h3>Unknown Phone Numbers ({{count($unknownPhones)}})</h3>
    @if (count($unknownPhones) > 0)
        <table class="table table-sm table-striped table-width-auto">
            <thead>
            <tr>
                <th>Timestamp (UTC-7)</th>
                <th>Phone</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($unknownPhones as $phone)
                <tr>
                    <td>{{$phone->created_at}}</td>
                    <td>{{$phone->address}}</td>
                    <td>{{$phone->message}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>
            No unknown phones numbers texted to the RBS number.
        </p>
    @endif

    <h3>Email Issues ({{count($emailIssues)}})</h3>
    @if (count($emailIssues) > 0)
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Timestamp (UTC-7)</th>
                <th>Person</th>
                <th>Event</th>
                <th>To Email</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($emailIssues as $log)
                <tr>
                    <td>{{$log->created_at}}</td>
                    <td>
                        @if ($log->target_person)
                            {{$log->target_person->callsign}}
                        @elseif ($log->target_person_id)
                            Person #{{$log->target_person_id}}
                        @else
                            <i>- unknown -</i>
                        @endif
                    </td>
                    <td>
                        {{$log->event}}
                    </td>
                    <td>
                        {{$log->data['to_email']}}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>No errors were logged.</p>
    @endif

    <h3>Setting Changes ({{count($settingLogs)}})</h3>
    @if (count($settingLogs) == 0)
        <p>No settings were changed</p>
    @else
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Timestamp (UTC-7)</th>
                <th>User</th>
                <th>Setting</th>
                <th>Values</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($settingLogs as $log)
                <tr>
                    <td>{{$log->created_at}}</td>
                    <td>
                        @if ($log->person)
                            {{$log->person->callsign}}
                        @elseif ($log->person_id)
                            {{$log->person_id}}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{$log->data['name']}}</td>
                    <td>{{$log->data['value'][0]}} -> {{$log->data['value'][1]}}</td>
                </tr>
            @endforeach
        </table>
    @endif
    <h3>Manual Permission Changes ({{count($roleLogs)}})</h3>
    @if (count($roleLogs) == 0)
        <p>No manual permission changes happened.</p>
    @else
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Timestamp (UTC-7)</th>
                <th>Callsign</th>
                <th>Source</th>
                <th>Permission(s)</th>
                <th>Reason</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($roleLogs as $log)
                <tr>
                    <td>{{$log->created_at}}</td>
                    <td>
                        @if ($log->target_person)
                            {{$log->target_person->callsign}}
                        @else
                            Deleted #{{$log->target_person_id}}
                        @endif
                    </td>
                    <td>
                        @if ($log->person)
                            {{$log->person->callsign}}
                        @elseif ($log->person_id)
                            {{$log->person_id}}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if ($log->event == 'person-role-add')
                            ADDED:
                            @if (!$log->roles || $log->roles->isEmpty())
                                (deleted roles) {{json_encode($log->data['role_ids'])}}
                            @else
                                {{$log->roles->implode('title', ', ')}}
                            @endif
                        @endif
                        @if ($log->event == 'person-role-remove')
                            REMOVED:
                            @if (!$log->roles || $log->roles->isEmpty())
                                (deleted roles) {{json_encode($log->data['role_ids'])}}
                            @else
                                {{$log->roles?->implode('title', ', ')}}
                            @endif

                        @endif
                    </td>
                    <td>{{$log->message}}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h3>Status Changes ({{count($statusLogs)}})</h3>
    @if (count($statusLogs) == 0)
        <p>No status changes happened.</p>
    @else
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Timestamp(UTC-7)</th>
                <th>Callsign</th>
                <th>Source</th>
                <th>Old</th>
                <th>New</th>
                <th>Reason</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($statusLogs as $log)
                <tr>
                    <td>{{$log->created_at}}</td>
                    <td>
                        @if ($log->person)
                            {{$log->person->callsign}}
                        @else
                            Deleted #{{$log->person_id}}
                        @endif
                    </td>
                    <td>
                        @if ($log->person_source)
                            {{$log->person_source->callsign}}
                        @elseif ($log->person_source_id)
                            {{$log->person_source_id}}
                        @else
                            Uknown source, no id
                        @endif
                    </td>
                    <td>{{$log->old_status}}</td>
                    <td>{{$log->new_status}}</td>
                    <td>{{$log->reason}}</td>
                </tr>
            @endforeach
        </table>
    @endif
    <h3>Failed Broadcasts ({{count($failedBroadcasts)}})</h3>
    @if (!count($failedBroadcasts))
        <p>No broadcast failures.</p>
    @else
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Timestamp (UTC-7)</th>
                <th>Sender</th>
                <th>Alert</th>
                <th>People</th>
                <th>Delivery</th>
                <th>Retried?</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($failedBroadcasts as $log)
                <tr>
                    <td>{{$log->created_at}}</td>
                    <td>{{$log->sender->callsign ?? "Deleted #{$log->sender_id}"}}</td>
                    <td>
                        {{$log->alert->on_playa ? "On Playa" : "Pre-Event"}}: {{$log->alert->title}}
                    </td>
                    <td>
                        {{count($log->people)}}
                    </td>
                    <td>
                        {{$log->sent_sms ? 'SMS' : ''}}
                        {{$log->sent_email ? 'email' : ''}}
                        {{$log->sent_clubhouse ? 'CH' : ''}}
                    </td>
                    <td>
                        @if ($log->retry_at)
                            Retry at {{$log->retry_at}}
                            by {{$log->retry_person->callsign ?? "Deleted #{$log->retry_person_id}"}}
                        @else
                            No retry attempt
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    <table class="table table-striped table-width-auto" style="margin-top: 1vh;">
        <thead>
        <tr>
            <th>Clubhouse Setting</th>
            <th>Value / Indicator</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Dashboard Period</td>
            <td>
                {!! ($settings['DashboardPeriod'] != 'auto') ? $dashboardPeriod : "<b style='color: red'>FORCED: {$settings['DashboardPeriod']}</b>" !!}
            </td>
        </tr>
        <tr>
            <td>Online Course</td>
            <td>
                @if ($dashboardPeriod == 'after-event' || $dashboardPeriod == 'post-event')
                    {!!($settings['OnlineCourseEnabled'] ?? false) ? 'Enabled' : 'Disabled'  !!}
                    (ok for After Event period)
                @else
                    {!! ($settings['OnlineCourseEnabled'] ?? false) ? "Enabled (normal for pre-event/event)" : '<b style="color: red">DISABLED (NOT NORMAL)</b>' !!}
                @endif
            </td>
        </tr>
        <tr>
            <td>Photo Uploading</td>
            <td>
                {!! ($settings['PhotoUploadEnable'] ?? false) ? "Enabled (normal)" : '<b style="color: red">DISABLED (NOT NORMAL)</b>' !!}
            </td>
        </tr>
        <tr>
            <td>Signups without OT</td>
            <td>
                {!! ($settings['OnlineCourseDisabledAllowSignups'] ?? false) ? '<b style="color: red">ENABLED (NOT NORMAL)</b>' : 'Disabled (normal)' !!}
            </td>
        </tr>
        <tr>
            <td style="width: 25%;">Ticketing Period</td>
            <td>
                {{$settings['TicketingPeriod'] ?? "NOT SET"}}
            </td>
        </tr>
        <tr>
            <td style="width: 25%;">Timesheet Corrections</td>
            <td>
                {{($settings['TimesheetCorrectionEnable'] ?? false) ? "Enabled" : "Disabled"}}
            </td>
        </tr>
        <tr>
            <td>Training Seasonal</td>
            <td>
                @if ($dashboardPeriod == 'after-event' || $dashboardPeriod == 'post-event')
                    {!!($settings['TrainingSeasonalRoleEnabled'] ?? false) ? '<b style="color: red">ENABLED (NOT NORMAL for post / after event)</b>' : 'Disabled (normal for after event)'  !!}
                @else
                    {!! ($settings['TrainingSeasonalRoleEnabled'] ?? false) ? "Enabled (normal)" : '<b style="color: red">DISABLED (NOT NORMAL for pre-event/event)</b>' !!}
                @endif
            </td>
        </tr>
        <tr>
            <td>Login Mgmt On Playa</td>
            <td>
                @if ($dashboardPeriod == 'event')
                    {!! ($settings['EventManagementOnPlayaEnabled'] ?? false) ? "Enabled (normal for event)" : '<b style="color: red">DISABLED (NOT NORMAL)</b>' !!}
                @else
                    {!!($settings['EventManagementOnPlayaEnabled'] ?? false) ? '<b style="color: red">ENABLED (NOT NORMAL)</b>' : 'Disabled (normal for pre-event/after-event)'  !!}
                @endif
            </td>
        </tr>
        </tbody>
    </table>
</x-html-email>
