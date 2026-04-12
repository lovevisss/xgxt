<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.2.3/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@9.9.0/styles/default.min.css">
    <link rel="stylesheet" href="{{asset('css/main.css')}}">
    <title>{{$title}}</title>
</head>
<body>
    <section class="hero is-medium is-primary is-bold">
        <div class="hero-body">
            <div class="container">
                <h1 class="title">
                    <a href="/">Snippets</a>
                </h1>
                <h2 class="subtitle">
                     A tutorial project for Laravel beginners
                </h2>
                <p>
                    <a href="/snippets/create" class="button">Create Snippets</a>
                </p>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="container">
            {{$slot}}
        </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@9.9.0/lib/highlight.min.js"></script>
    <script>hljs.initHighlightingOnLoad();</script>
</body>
</html>
