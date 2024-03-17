@php
    use App\Models\PersonPhoto;
@endphp
<x-html-email :isPublicEmail="true">
    <p>
        Hello from the Ranger Volunteer Coordinators,
    </p>

    <p>
        We regret to inform you that your photo submission has been rejected due to non-compliance with the BMID
        guidelines. Please review the guidelines carefully and resubmit your photo to avoid any further delays.
        Thank you for your cooperation.
    </p>
    <p>
        The BMID guidelines can be view by using <a href="https://docs.google.com/document/d/1hUqrJwdMG6XB5nk7Eh8nY2GH4uvAFTppmCKCmfidIvQ/edit">THIS LINK</a>.
    </p>

    <p>
        Log back into the <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a> to submit a new
        photo.
    </p>

    @if ($person->isPNV())
        <p>
            <b>
                You must upload a photo for approval before being able to sign up for an In-Person Training or Alpha
                shift. No exceptions will be made.
            </b>
        </p>
    @endif

    <p>
        The following problem(s) were found with your photo submission:
    <ul>
        @foreach ($rejectReasons as $reason)
            <li>
                @if (!empty(PersonPhoto::REJECTIONS[$reason]))
                    {{PersonPhoto::REJECTIONS[$reason]['message']}}
                @else
                    Uh oh, the reason [{{$reason}}] was marked, and I don't know what that is. This is a bug.
                @endif
            </li>
        @endforeach
    </ul>
    </p>
    @if (!empty($rejectMessage))
        <p>
            The photo reviewer has left additional information:
        </p>
        <p>
            @hyperlinktext($rejectMessage)
        </p>
    @endif

    <p>

        <b>Questions?</b> Contact the Ranger Volunteer Coordinators at
        <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </p>
    <p>
        Yours sincerely,<br>
        The Ranger Volunteer Coordinators
    </p>
</x-html-email>
