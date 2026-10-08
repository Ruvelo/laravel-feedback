@php($tone = \Ruvelo\Feedback\Support\Palette::statusChip($status))
<span class="fb-chip fb-chip--status {{ $tone['class'] }}" @if ($tone['style']) style="{{ $tone['style'] }}" @endif>{{ \Ruvelo\Feedback\Support\Palette::statusLabel($status) }}</span>
