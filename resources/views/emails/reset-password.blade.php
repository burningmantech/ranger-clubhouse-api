@component('html-email')
    <p>
        Hello {{$greeting}},
    </p>

    <p>
        You recently requested to reset your password for your Black Rock Ranger Secret Clubhouse account.
        Click the button below to reset your password.
    </p>

    <p style="text-align: center">
        <div class="btn btn-primary"><a href="{{$resetURL}}">Reset Your Password</a></div>
    </p>

    <p>
        If you did not request a password reset, please ignore this email or contact us at
        <a href="mailto:{{$adminEmail}}">{{$adminEmail}}</a> to let us know someone is up to no good.
    </p>
    <p>
        This password reset is only valid for the next 2 hours.
    </p>

    <p>
        Sincerely,
    </p>
    <p>
        The Black Rock Ranger Tech Team<br>
        <a href="mailto:{{$adminEmail}}">{{$adminEmail}}</a>
    </p>
    <p>
        If you're having trouble clicking the password reset button, copy and paste the URL below
        into your web browser.
        <br>
        <br>
        <a href="{{$resetURL}}">{{$resetURL}}</a>
    </p>

@endcomponent
