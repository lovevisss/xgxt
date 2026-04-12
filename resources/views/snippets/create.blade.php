@component('layout.main')
    @slot('title')
        title goes here
    @endslot
    <h1 class="title">New Snippet</h1>
    <form action="/snippets" method="POST">
        @csrf
        @if($snippet->id)
            <input type="hidden" name="forked_id" value="{{$snippet->id}}">
        @endif
        <div class="control">
            <label class="label" for="title">Title</label>
            <input class="input" type="text" name="title" id="title" value="{{$snippet->title ?? ''}}">
        </div>
        <div class="control">
            <label class="label" for="body">Body</label>
            <textarea class="textarea" name="body" id="body">{{$snippet->body ?? ''}}</textarea>
        </div>
        <div class="control">
            <button class="button is-primary" type="submit">Create Snippet</button>
        </div>

    </form>
@endcomponent
