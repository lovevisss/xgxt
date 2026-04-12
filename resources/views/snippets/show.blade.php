@component('layout.main')
    @slot('title')
        title goes here
    @endslot
    <div class="is-flex">
        <h1 class="title ">
            {{$snippet->title}}
        </h1>
        <a href="/snippets/{{$snippet->id}}/fork">Fork Me</a>
    </div>


    <pre>
        <code>{{$snippet->body}}</code>
    </pre>

    <p>
        <a href="/snippets     ">Back</a>
    </p>
{{--    @if($snippet->isAfork())--}}
{{--        <p>--}}
{{--            Forked from--}}
{{--            <a href="/snippets/{{$snippet->parent->id}}">--}}
{{--                {{$snippet->parent->title}}--}}
{{--            </a>--}}
{{--        </p>--}}
{{--    @endif--}}
    @if($snippet->forks->count())
        <h2 class="subtitle">Forks</h2>
        <ul>
            @foreach($snippet->forks as $fork)
                <li>
                    <a href="/snippets/{{$fork->id}}">
                        {{$fork->title}}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

@endcomponent
