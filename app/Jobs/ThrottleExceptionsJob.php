<?php

namespace App\Jobs;

use App\Exceptions\ThrottleableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Run dispatch:throttle-exceptions
 *
 * This is an example of using ThrottlesExceptions middleware together with recoverable and unrecoverable
 * exceptions. Examples for recoverable and unrecoverable exceptions would be:
 * - 429 Too many requests -> recoverable (retry after some duration)
 * - All other unexpected exceptions -> unrecoverable (fail the job and don't retry)
 */
class ThrottleExceptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RECOVERABLE_EXCEPTION = 1;
    public const UNRECOVERABLE_EXCEPTION = 2;

    // This won't have effect as ThrottlesExceptionsWithRedis increases the attempts
    // and not the maxExceptions.
    // public $maxExceptions = 2;

    public function __construct(public ?int $throwException = null)
    {
    }

    public function retryUntil()
    {
        // If this is too short, maxAttempts and maxExceptions will be pointless.
        return now()->addMinutes(2);
    }

    public function middleware()
    {
        return [
            // Wait for {backoff} minutes if any exception is thrown.
            // Allow {maxAttempts} exceptions in {decayMinutes}.
            // Make sure that {maxAttempts} x {backoff} < {decayMinutes}
            (new ThrottlesExceptionsWithRedis(maxAttempts: 3, decayMinutes: 1))
                ->when(fn ($e) => $e instanceof ThrottleableException && $e->recoverable),
//                ->backoff(1), // a.k.a retryAfterMinutes. If you want to give some space for the retries.
        ];
    }

    public function failed(Throwable $throwable)
    {
        // This always reports "has been attempted too many times", even for unrecoverable one."
        /** @var MaxAttemptsExceededException|ThrottleableException $throwable */
        Log::info('failed method', [
            'className' => get_class($throwable),
            'job' => $throwable instanceof MaxAttemptsExceededException ? $this->jobStats($throwable->job) : null,
            'message' => $throwable->getMessage(),
        ]);
    }

    public function handle(): void
    {
        Log::withContext([
            'job' => $this->jobStats($this->job),
        ]);

        if ($this->throwException == self::RECOVERABLE_EXCEPTION) {
            Log::info('Recoverable exception thrown. Will retry in 1 (backoff) minute...');
            throw new ThrottleableException(recoverable: true);
        }

        if ($this->throwException == self::UNRECOVERABLE_EXCEPTION) {
            try {
                Log::info('Unrecoverable exception thrown. Will fail immediately and won\'t be retried...');
                throw new ThrottleableException(recoverable: false);
            } catch (ThrottleableException $e) {
                $this->fail($e); // `fail` call also throws an exception so also throttled. Use together with ThrottlesExceptions::when();
                return;
            }
        }

        Log::info('No exception thrown. Will not fail');
    }

    private function jobStats(?Job $job): ?array
    {
        if (! $job) {
            return null;
        }

        return [
            'job_id' => $job->getJobId(),
            'job_uuid' => $job->uuid(), // To observe redis key for max exceptions.
            'max_tries' => $job->maxTries(),
            'attempts' => $job->attempts(),
            'retry_until' => $job->retryUntil(),
            'max_exceptions' => $job->maxExceptions()
        ];
    }
}
