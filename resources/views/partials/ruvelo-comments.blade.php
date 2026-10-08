{{-- Only included when ruvelo/laravel-comments is installed and chosen (feedback.comments.driver). --}}
<x-comments::thread :for="\Ruvelo\Feedback\Integrations\CommentableRequest::wrap($feature)" />
