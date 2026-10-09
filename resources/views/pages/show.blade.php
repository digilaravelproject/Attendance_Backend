<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $page->page_name }}">
    <title>{{ $page->page_name }} | {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #1f2937;
            background: #f3f4f6;
        }
        * { box-sizing: border-box; }
        body { margin: 0; line-height: 1.7; }
        header { padding: 1.25rem 1.5rem; color: #fff; background: #1d4ed8; }
        header div, main { width: min(900px, 100%); margin: 0 auto; }
        header strong { font-size: 1.1rem; }
        main {
            margin-top: 2.5rem;
            margin-bottom: 2.5rem;
            padding: clamp(1.5rem, 4vw, 3rem);
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgb(15 23 42 / 8%);
        }
        h1, h2, h3 { color: #111827; line-height: 1.25; }
        h1 { margin-top: 0; font-size: clamp(2rem, 5vw, 3rem); }
        h2 { font-size: 1.5rem; }
        h3 { margin-top: 2rem; font-size: 1.15rem; }
        p { color: #4b5563; }
        @media (max-width: 940px) { main { margin: 1rem; border-radius: 0.5rem; } }
    </style>
</head>
<body>
    <header><div><strong>{{ config('app.name') }}</strong></div></header>
    <main>
        <h1>{{ $page->page_name }}</h1>
        <article>{!! $page->description !!}</article>
    </main>
</body>
</html>
