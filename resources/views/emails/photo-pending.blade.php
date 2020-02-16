@component('html-email')
    <p>
        Hello Photo Reviewer,
    </p>
    <p>
        One or more photos are <a href="https://ranger-clubhouse.burningman.org/client/vc/photo-review">queued for reviewed.</a>
    </p>
    <p>
    <table class="table table-sm table-striped">
        <thead>
        <tr>
            <th>Callsign</th>
            <th>Status</th>
            <th>Uploaded At</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($pendingPhotos as $photo)
            <tr>
                <td>{{$photo->person->callsign}}</td>
                <td>{{$photo->person->status}}</td>
                <td>{{$photo->created_at}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </p>
    <p>
        Forever your humble digital servant,<br>
        The Clubhouse Bot
    </p>
@endcomponent
