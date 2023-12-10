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

    /*
     * $maxExceptions won't have effect for the recoverable errors as ThrottlesExceptions increases the attempts
     * and not the maxExceptions. You can use this for the exceptions that doesn't match
     * the `ThrottlesExceptions::when()` condition below.
     * (i.e. Retry unrecoverable errors `$maxExceptions` times, retry recoverable ones `$tries` times.)
     */
    // public $maxExceptions = 2;


    /*
     * If both $backoff and ThrottlesExceptions::backoff() is set, ThrottlesExceptions' backoff takes precedence
     * and $backoff is ignored.
     *
     * I ThrottlesExceptions::backoff() is ignored and $backoff is set, the job is retried without any delay,
     * and retried maxAttempts x 2 times in the first second, then in the consequent attempts throttles
     * as expected. $backoff is ignored!
     */
    // public $backoff = 20; // Backoff in seconds. Which one will be used ThrottlesExceptions also has backoff?

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
                // Throttle when the condition matches. This won't stop retrying if the condition is met,
                // so the exception should be handled in the handle() method and `$this->fail()` should
                // be run to mark the job as a failed.
                ->when(fn ($e) => $e instanceof ThrottleableException && $e->recoverable)
                ->backoff(1 / 6), // Backoff in minutes. a.k.a retryAfterMinutes. You can set fractions for seconds.
        ];
    }

    public function failed(Throwable $throwable)
    {
        // You can always get `MaxAttemptsExceededException` along with the expected exceptions.
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
            Log::info('Recoverable exception thrown. Will retry...');
            throw new ThrottleableException(recoverable: true);
        }

        if ($this->throwException == self::UNRECOVERABLE_EXCEPTION) {
            try {
                Log::info('Unrecoverable exception thrown. Will fail immediately and won\'t be retried...');
                throw new ThrottleableException(recoverable: false);
            } catch (ThrottleableException $e) {
                // `fail` call also throws an exception, which is also throttled. Use together
                // with ThrottlesExceptions::when();
                $this->fail($e);
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
