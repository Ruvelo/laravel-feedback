{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ config('feedback.name') }} changelog</title>
    <subtitle>New features, improvements and fixes in {{ config('feedback.name') }}.</subtitle>
    <id>{{ route('feedback.changelog') }}</id>
    <link rel="alternate" type="text/html" href="{{ route('feedback.changelog') }}"/>
    <link rel="self" type="application/atom+xml" href="{{ route('feedback.changelog.atom') }}"/>
    <updated>{{ $updated->toAtomString() }}</updated>
@foreach ($entries as $entry)
    <entry>
        <title>{{ $entry->title }}</title>
        <id>{{ $entry->url() }}</id>
        <link rel="alternate" type="text/html" href="{{ $entry->url() }}"/>
        <published>{{ $entry->published_at?->toAtomString() }}</published>
        <updated>{{ $entry->updated_at->max($entry->published_at ?? $entry->updated_at)->toAtomString() }}</updated>
        <author><name>{{ $entry->authorName() }}</name></author>
@foreach ($entry->labelKeys() as $label)
        <category term="{{ $label }}" label="{{ \Ruvelo\Feedback\Support\Palette::changelogLabel($label) }}"/>
@endforeach
        <summary>{{ $entry->excerpt(300) }}</summary>
        <content type="html">{{ ($entry->cover_url ? '<p><img src="'.e($entry->cover_url).'" alt=""></p>' : '').$entry->html() }}</content>
    </entry>
@endforeach
</feed>
