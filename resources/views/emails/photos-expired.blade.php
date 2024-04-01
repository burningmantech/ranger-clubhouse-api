<x-html-email>
    <p>
        Hello Photo Maintainer,
    </p>
    <p>
        The following archived photos have been deleted.
    </p>
    <table class="table table-sm table-striped table-width-auto" style="margin-bottom: 20px">
        <thead>
        <tr>
            <th>Person #</th>
            <th>Callsign</th>
            <th>Photo Id</th>
            <th>Created At</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($expiredPhotos as $photo)
            <tr>
                <td>{{$photo['person_id']}}</td>
                <td>{{$photo['callsign']}}</td>
                <td>{{$photo['person_photo_id']}}</td>
                <td>{{$photo['created_at']}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p>
        Forever your humble digital servant,<br>
        The Clubhouse Bot
    </p>
</x-html-email>
