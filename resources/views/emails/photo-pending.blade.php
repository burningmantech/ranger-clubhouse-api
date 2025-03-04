<x-html-email>
    <p>
        Hello Photo Reviewer,
    </p>
    <p>
        One or more photos are <a href="https://ranger-clubhouse.burningman.org/vc/photo-review">queued for review.</a>
    </p>
    <table class="table table-sm table-striped" style="margin-bottom: 20px">
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
                <td>{{$photo->created_at->diffForHumans(['options' => 0 ])}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p>
        Forever your humble digital servant,<br>
        The Clubhouse Bot
    </p>
</x-html-email>
