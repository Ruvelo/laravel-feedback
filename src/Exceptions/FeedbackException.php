<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Base class for the package's own exceptions, so callers can catch them
 * all. Thrown during a request, each renders itself: JSON with the right
 * status for API calls; for browsers, back to the form with the message,
 * or your app's error page for 403s and 404s.
 */
abstract class FeedbackException extends RuntimeException implements HttpExceptionInterface
{
    public function status(): int
    {
        return 422;
    }

    /**
     * The form field the message belongs to.
     */
    public function field(): string
    {
        return 'feedback';
    }

    public function getStatusCode(): int
    {
        return $this->status();
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    public function render(Request $request): JsonResponse|RedirectResponse|null
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'errors' => [$this->field() => [$this->getMessage()]]], $this->status());
        }

        if (in_array($this->status(), [403, 404], true)) {
            return null; // Laravel's own error page
        }

        return back()
            ->withInput($request->except(['_token', '_method']))
            ->withErrors([$this->field() => $this->getMessage()]);
    }
}
