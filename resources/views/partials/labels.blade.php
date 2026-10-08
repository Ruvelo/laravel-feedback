@if ($entry->labelKeys() !== [])
    <div class="fb-labels">
        @foreach ($entry->labelKeys() as $label)
            @php($tone = \Ruvelo\Feedback\Support\Palette::labelChip($label))
            @if (! empty($link) && Route::has('feedback.changelog'))
                <a class="fb-chip {{ $tone['class'] }}" @if ($tone['style']) style="{{ $tone['style'] }}" @endif href="{{ route('feedback.changelog', ['label' => $label]) }}">{{ \Ruvelo\Feedback\Support\Palette::changelogLabel($label) }}</a>
            @else
                <span class="fb-chip {{ $tone['class'] }}" @if ($tone['style']) style="{{ $tone['style'] }}" @endif>{{ \Ruvelo\Feedback\Support\Palette::changelogLabel($label) }}</span>
            @endif
        @endforeach
    </div>
@endif
