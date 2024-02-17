<x-html-email>
    <p>
        {!! nl2br($emailMessage) !!}
    </p>
    <p>
        <b>Questions?</b> Email <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </p>
    Application ID A-{{$application->id}}
</x-html-email>
