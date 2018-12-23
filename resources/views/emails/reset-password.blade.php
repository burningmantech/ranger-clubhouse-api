@component('html-email')
<p>
    Dear Ranger,
</p>

<p>
    You have been issued a temporary password to the Ranger Secret Clubhouse:
</p>

<p>
<b>{{ $password }}</b>
</p>

<p>
    Please use it log in and change it to a password of your choice.
</p>

<p>
    If you are still unable to log in, or if you were not expecting this
    message, please contact us at <a href="mailto:{{$adminEmail}}">{{$adminEmail}}</a>.
</p>

<p>
    Sincerely,
</p>
<p>
    The Black Rock Ranger Tech Team
</p>
@endcomponent
