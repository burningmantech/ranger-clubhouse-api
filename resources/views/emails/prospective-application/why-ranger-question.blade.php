<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Volunteer Coordinators have reviewed your application and have a question about your response to the
        <i>Why Do You Want to Ranger?</i> section.
    </p>
    <x-vc-application-on-hold />
    <p>
        Here is the message from the Volunteer Coordinators:
    </p>
    <p>
        {!! nl2br($messageToUser) !!}
    </p>
     <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <x-vc-application-footer :application="$application" />
</x-html-email>
