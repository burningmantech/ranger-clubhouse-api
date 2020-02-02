@component('html-email')
<p>
  Hello from the Ranger Volunteer Coordinators,
</p>

<p>
  We regret to inform you that your BMID photo submission has been REJECTED.
</p>

<p>
  Log back into the <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a> to submit a new photo.
</p>

@if ($person->isPNV())
<p>
  <b>You will not be allowed to sign up for training or an Alpha shift until a photo is submitted and approved.</b>
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
@endcomponent
