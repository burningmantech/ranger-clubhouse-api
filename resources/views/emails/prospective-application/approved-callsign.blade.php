<x-html-email>
    <div style="max-width: 650px; padding: 2px; border: 5px solid #f0f0f0">
        <x-ranger-header-image/>

        <p>
            Hey Ranger Applicant {{$application->first_name}} {{$application->last_name}},
        </p>

        <p>
            The Ranger Handle Wranglers have been hard at work sorting through stacks of radio callsigns, and they've
            found one of yours that works.
        </p>

        <h3>You shall hereby be known as: {{$application->approved_handle}}. Congratulations!</h3>

        <p>
            This is the handle you will be using in training, on the BRC Ranger radios, and as a way to identify
            yourself in person while Rangering. As a courtesy to the hard working Handle Wranglers, please do not
            request changes to your handle between now and the conclusion of this year’s event.
        </p>

        <p>
            Our next step is to load your information into the Ranger scheduling tool called <i>The Secret
                Clubhouse.</i>
            Look for an email from the Clubhouse asking you to create a password for your account. Please note there
            will be a delay of up to two weeks before you receive an email from the Clubhouse. Thank you for your
            patience.
        </p>
        <p>
            We will also subscribe you to the Ranger Applicant News email group, which we use for all of our important
            communication to Ranger Applicants.
        </p>

        <h3>What can you do now?</h3>
        <p>
            <b>Take a look at these guidelines for BMID (Burning Man ID) photos</b>
            <a href="https://docs.google.com/document/d/1hUqrJwdMG6XB5nk7Eh8nY2GH4uvAFTppmCKCmfidIvQ/edit">HERE</a>.
            You won't be able to upload one just yet, but you'll need to do so before you can sign up for training.
        </p>

        <p>
            Read the most recent version of the Ranger Manual and Burn Perimeter Briefing
            (links at the top, and in the right sidebar of
            <a href="http://rangers.burningman.org/training/">http://rangers.burningman.org/training/</a>)
        </p>

        <p>
            Check out Ranger trainings near you, or on Playa, and plan on attending one:
            <a href="https://rangers.burningman.org/training/">https://rangers.burningman.org/training/</a>
            Trainings will be posted between April and May, as they are scheduled.
        </p>

        <p>
            <b style="color: red;">
                Note: You are required to complete the Online Course prior to signing up for In Person Training.
            </b>
            In Person Training Sign-Ups will not be visible to you in the Clubhouse until the Online Course is
            completed.
        </p>

        <p>
            Practice your radio skills until they are second nature. Reviewing the ART of Radio (link in the right
            sidebar of <a href="https://rangers.burningman.org">https://rangers.burningman.org</a>) will help. Also
            watch your email for information on off-playa virtual radio practice opportunities, coming your way sometime
            soon.
        </p>

        <p>
            Your Friendly Black Rock Ranger Volunteer Coordinators
        </p>
        <b>Questions?</b> Email <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </div>
</x-html-email>
