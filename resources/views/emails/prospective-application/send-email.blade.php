<x-html-email>
    <p>
        {!! nl2br($emailMessage) !!}
    </p>
    <x-vc-questions />
    Application ID A-{{$application->id}}
</x-html-email>
