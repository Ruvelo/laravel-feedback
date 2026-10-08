@php($viewer = auth()->user() instanceof \Illuminate\Database\Eloquent\Model ? auth()->user() : null)
<li class="fb-comment {{ $comment->official ? 'is-official' : '' }}" id="comment-{{ $comment->id }}">
    @include('feedback::partials.avatar', ['name' => $comment->authorName()])
    <div>
        <div class="fb-comment-head">
            <strong>{{ $comment->authorName() }}</strong>
            @if ($comment->official)
                <span class="fb-chip fb-chip--team">Team</span>
            @endif
            <a href="#comment-{{ $comment->id }}" style="color: inherit"><time datetime="{{ $comment->created_at->toIso8601String() }}" title="{{ $comment->created_at->toDayDateTimeString() }}">{{ $comment->created_at->diffForHumans() }}</time></a>
        </div>
        <div class="fb-prose">{!! $comment->html() !!}</div>
        @if ($viewer !== null)
            <div class="fb-comment-tools">
                @if ($comment->parent_id === null && Route::has('feedback.comments.store') && ! $feature->isMerged())
                    <a href="{{ $feature->url() }}?reply={{ $comment->id }}#reply-{{ $comment->id }}" data-feedback-reply="{{ $comment->id }}">Reply</a>
                @endif
                @if (Route::has('feedback.comments.destroy') && ($comment->isAuthoredBy($viewer) || $canManage))
                    <form method="post" action="{{ route('feedback.comments.destroy', $comment) }}" onsubmit="return confirm('Delete this comment{{ $comment->parent_id === null ? ' and its replies' : '' }}?')">
                        @csrf
                        @method('delete')
                        <button type="submit" class="fb-link fb-link--danger">Delete</button>
                    </form>
                @endif
            </div>
        @endif

        @if ($comment->parent_id === null && ($comment->replies->isNotEmpty() || $replyTo === $comment->id || $viewer !== null))
            <ol class="fb-comments">
                @foreach ($comment->replies as $reply)
                    @include('feedback::partials.comment', ['comment' => $reply, 'feature' => $feature, 'replyTo' => null])
                @endforeach
            </ol>
            @if ($viewer !== null && Route::has('feedback.comments.store') && ! $feature->isMerged())
                <div id="reply-{{ $comment->id }}" @if ($replyTo !== $comment->id) hidden @endif data-feedback-reply-form="{{ $comment->id }}">
                    @include('feedback::partials.comment-form', ['parent' => $comment])
                </div>
            @endif
        @endif
    </div>
</li>
