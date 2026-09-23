<x-html-email>
    <p>Hello from the Clubhouse Bot,</p>
    <p>
        Google Groups (mailing list) updates for {{$person->callsign}} ({{$reason}}):
    </p>
    <ul>
        @foreach ($results as $result)
            <li>
                @if ($result['success'])
                    {{$result['action'] == 'add' ? 'Add' : 'Remove'}} {{$result['email']}}
                    {{$result['action'] == 'add' ? 'to' : 'from'}} {{$result['group']}}: {{$result['status']}}
                @else
                    <b style="color: red;">
                        FAILED to {{$result['action']}} {{$result['email']}}
                        {{$result['action'] == 'add' ? 'to' : 'from'}} {{$result['group']}}: {{$result['status']}}
                    </b>
                @endif
            </li>
        @endforeach
    </ul>
    @if (collect($results)->contains(fn($r) => !$r['success']))
        <p>
            <b>One or more changes failed and will need to be made by hand.</b>
        </p>
    @endif
    <p>
        <a href="https://ranger-clubhouse.burningman.org/person/{{$person->id}}">
            https://ranger-clubhouse.burningman.org/person/{{$person->id}}
        </a>
    </p>
</x-html-email>
