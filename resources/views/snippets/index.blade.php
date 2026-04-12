@component('layout.main')
    @slot('title')
        title goes here
    @endslot


    @if(count($snippets))
        <ul>
            @foreach($snippets as $snippet)
                <article class="snippet">
                    <div class="is-flex">
                        <a href="/snippets/{{$snippet->id}}" class="flex title">
                            {{$snippet->title}}
                        </a>
                        <a href="/snippets/{{$snippet->id}}/fork">Fork Me</a>
                    </div>

                    <pre>
                        <code>{{$snippet->body}}</code>
                    </pre>
                </article>

            @endforeach
        </ul>
    @endif

@endcomponent
