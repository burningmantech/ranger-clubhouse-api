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
            This is the handle you will be using in training, on the Black Rock Ranger radios, and as a way to identify
            yourself in person while Rangering. As a courtesy to the hard working Handle Wranglers, please do not
            request changes to your handle between now and the conclusion of this year's event.
        </p>

        <p>
            Our next step is to load your information into the Ranger scheduling tool called
            <i>The Ranger Secret Clubhouse.</i>
            Look for an email from the Clubhouse asking you to create a password for your account. Please note there
            may be a delay of up to two weeks before you receive an email from the Clubhouse. Thank you for your
            patience.
        </p>
        <p>
            We will also subscribe you to the Ranger Applicant News email group, which we use for all of our important
            communication to Ranger applicants.
        </p>

        <h3>What can you do now?</h3>
        <p>
            <b>Take a look at these guidelines for BMID (Burning Man ID) photos</b>
            <a href="https://docs.google.com/document/d/1hUqrJwdMG6XB5nk7Eh8nY2GH4uvAFTppmCKCmfidIvQ/edit">HERE</a>.
            You won't be able to upload one just yet, but you'll need to do so before you can sign up for training.
        </p>

        <p>
            <b>Read the most recent version of the Ranger Manual</b> found on the main Ranger website.
            (<a href="http://rangers.burningman.org/">http://rangers.burningman.org/</a>)
        </p>

        <p>
            Check out Ranger trainings near you, or on Playa, and plan on attending one:
            <a href="https://rangers.burningman.org/training/">https://rangers.burningman.org/training/</a>
            Trainings will be posted between April and June, as they are scheduled.
        </p>

        <p>
            <b style="color: red;">
                Note: You are required to complete the Online Course prior to signing up for In Person Training.
            </b>
            In-Person Training sign-ups will not be available to you in the Clubhouse until the Online Course
            is completed.
        </p>

        {{--
                <p>
                    <b>Practice your radio skills until they are second nature.</b> Reading the <i>ART of Radio</i> will help.
                    Click the link <a href="https://drive.google.com/drive/folders/1UHtAd7wQAM0Tn7FjVDhIuNjBmoDN3rHJ">HERE</a>
                    to begin reading. Also watch your email for information on off-playa virtual radio practice opportunities,
                    coming your way sometime soon.
                </p>
        --}}

        <p>
            Your Friendly Black Rock Ranger Volunteer Coordinators
        </p>
        <x-vc-application-footer :application="$application" />
    </div>
</x-html-email>
