{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('feedback.name') }} changelog</title>
        <link>{{ route('feedback.changelog') }}</link>
        <description>New features, improvements and fixes in {{ config('feedback.name') }}.</description>
        <atom:link href="{{ route('feedback.changelog.rss') }}" rel="self" type="application/rss+xml"/>
        <lastBuildDate>{{ $updated->toRssString() }}</lastBuildDate>
@foreach ($entries as $entry)
        <item>
            <title>{{ $entry->title }}</title>
            <link>{{ $entry->url() }}</link>
            <guid isPermaLink="true">{{ $entry->url() }}</guid>
            <pubDate>{{ $entry->published_at?->toRssString() }}</pubDate>
@foreach ($entry->labelKeys() as $label)
            <category>{{ \Ruvelo\Feedback\Support\Palette::changelogLabel($label) }}</category>
@endforeach
            <description>{{ ($entry->cover_url ? '<p><img src="'.e($entry->cover_url).'" alt=""></p>' : '').$entry->html() }}</description>
        </item>
@endforeach
    </channel>
</rss>
