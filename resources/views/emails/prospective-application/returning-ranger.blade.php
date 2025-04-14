<x-html-email>
    <p>
        Hey {{$application->first_name}},
    </p>
    <p>
        We received your Ranger application, however, it appears you are already a Black Rock Ranger! Any returning
        Ranger does not need to re-apply to the department.
    </p>
    <p>
        Every year, all Rangers must complete the Online Course, and attend an In-Person Training session before
        being allowed to work. The Clubhouse dashboard will guide you through all the steps required.
    </p>
    <p>
        <a href="https://ranger-clubhouse.burningman.org">https://ranger-clubhouse.burningman.org</a>
    </p>
    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <x-vc-application-footer :application="$application" />
</x-html-email>
