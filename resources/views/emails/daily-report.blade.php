@component('html-email')
<h1>Daily Report for {{date('Y-m-d')}}</h1>

<h3>Error Logs ({{count($errorLogs)}})</h3>
@if (count($errorLogs) > 0)
<table class="table table-sm table-striped">
  <thead>
    <tr>
      <th>Timestamp</th>
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
<b class="color: green">Congratulations! No error logs were recorded.</b>
@endif


<h3>Failed Broadcasts ({{count($failedBroadcasts)}})</h3>
@if (count($failedBroadcasts) == 0)
<b class="color: green">No broadcast failures.</b>
@else
<table class="table table-sm table-striped">
  <thead>
    <tr>
      <th>#</th>
      <th>Timestamp</th>
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
      <td>{{$log->id}}</td>
      <td>{{$log->created_at}}</td>
      <td>{{$log->sender->callsign}}</td>
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
        Retry at {{$log->retry_at}} by {{$log->retry_person->callsign}}
        @else
        -
        @endif
      </td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif


<h3>Role Changes ({{count($roleLogs)}})</h3>

@if (count($roleLogs) == 0)
<b class="color: green">No role changes happened.</b>
@else
<table class="table table-sm table-striped">
  <thead>
    <tr>
      <th>Timestamp</th>
      <th>Callsign</th>
      <th>Source</th>
      <th>Roles Added</th>
      <th>Roles Removed</th>
      <th>Reason</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($roleLogs as $log)
    <tr>
      <td>{{$log->created_at}}</td>
      <td>{{$log->target_person->callsign}}</td>
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
        {{$log->roles->implode('title', ', ')}}
        @else
        -
        @endif
      </td>
      <td>
        @if ($log->event != 'person-role-add')
        {{$log->roles->implode('title', ', ')}}
        @else
        -
        @endif
      </td>
      <td>{{$log->message}}</td>
    </tr>
    @endforeach
</table>
@endif

<h3>Status Changes ({{count($statusLogs)}})</h3>
@if (count($statusLogs) == 0)
<b>No status changes happened.</b>
@else
<table class="table table-sm table-striped">
  <thead>
    <tr>
      <th>Timestamp</th>
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
      <td>{{$log->person->callsign}}</td>
      <td>{{$log->person_source->callsign}}</td>
      <td>{{$log->old_status}}</td>
      <td>{{$log->new_status}}</td>
      <td>{{$log->reason}}</td>
    </tr>
    @endforeach
</table>
@endif

@endcomponent
