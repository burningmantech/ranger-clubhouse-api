<x-html-email>
    <p>
        {!! nl2br($emailMessage) !!}
    </p>
    <x-vc-application-footer :application="$application" />

</x-html-email>
