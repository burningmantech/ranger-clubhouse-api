@component('html-email')
    <p>
        Hello from the Black Rock Rangers,
    </p>
    <p>
        Congratulations {{$person->first_name}} {{$person->last_name}} AKA {{$person->callsign}},
        a Clubhouse account has been created for you.
    </p>
    <p>
        The Clubhouse is the website the Rangers use to sign up for training &amp; shifts, and to manage other
        tasks related to rangering on and off the playa.
    </p>
    <p>
        The first thing you need to do RIGHT NOW is to setup your account by clicking the button below:
    </p>
    <div class="btn btn-primary" style="margin-top: 20px;">
        <a href="{{$inviteUrl}}">Setup Your Clubhouse Account</a>
    </div>
    <div style="margin-bottom:20px;color: #ff0000;">
        <b style="">The button will expire within 1 week.</b>
    </div>
    <p>
        Once your account is setup, use the following URL to go directly to the Clubhouse:<br>
        <a href="https://ranger-clubhouse.burningman.org">ranger-clubhouse.burningman.org</a>
    </p>
    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <p>
        Questions? <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </p>
    <p class="margin-top: 25px">
        If you're having trouble clicking the button, copy and paste the URL below
        into your web browser:
    <p>
    <p>
        <a href="{{$inviteUrl}}">{{$inviteUrl}}</a>
    </p>

@endcomponent
