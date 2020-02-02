@component('html-email')
<p>
  Hello from the Ranger Volunteer Coordinators,
</p>

<p>
  Congratulations! The uploaded photo for your BMID has been APPROVED!
</p>

@if ($person->isPNV())
<p>
  Visit the <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a> to see what additional
   tasks you need to complete in order to become a Black Rock Ranger.
</p>
@endif
<p>
  <b>Questions?</b> Contact the Ranger Volunteer Coordinators at
  <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
</p>
<p>
  Yours sincerely,<br>
  The Volunteer Coordinators
</p>
@endcomponent
