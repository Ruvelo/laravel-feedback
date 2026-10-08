@if (session('feedback.status'))
    <p class="fb-flash" role="status">{{ session('feedback.status') }}</p>
@endif
@if ($errors->has('vote') || $errors->has('feedback') || $errors->has('into'))
    <p class="fb-flash fb-flash--error" role="alert">{{ $errors->first('vote') ?: ($errors->first('feedback') ?: $errors->first('into')) }}</p>
@endif
