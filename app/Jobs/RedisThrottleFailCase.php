<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Demonstration for using throttling some heavy task inside the job.
 * Testing procedure:
 * - Run 4 workers
 * - `tail -f` the log file
 * - Fire 10 jobs simultaneously.
 *
 * Outcome:
 * - 2 jobs will complete, 8 will fail.
 * - When the job is released due to not getting a lock, it'll still be marked as "DONE" in the worker output.
 *   Don't let it confuse you.
 * - The first 2 job will block the others for 60sec.s, until the loop is completed.
 * - 10 seconds later the 8 delayed jobs will fail as the number of tries is 1 by default.
 *
 * To make the 8 delayed jobs tried again:
 * 1. You can increase `$tries` (10 won't cut it and some jobs will fail eventually).
 * 2. You can set a time frame in `retryUntil()`
 */
class RedisThrottleFailCase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // public $tries = 10;

    public function retryUntil()
    {
        // return now()->addMinutes(5);
    }

    public function handle(): void
    {
        Log::withContext(['job_id' => $this->job->getJobId()]);
        Log::info("Before processing");

        Redis::throttle('key')
            ->block(0) // Attempt to get the lock for this sec.s
            ->allow(2) // times
            ->every(60) // sec.
            ->then(function () {
                // A typical example would be paginated API call. If you try to fetch all the items in a loop
                // you'll end up with rate limiter errors (429 Too many requests). So this needs to be throtted.
                // If you throttle the whole job, you'll start over unless you save the state somehow.
                foreach (range(1, 5) as $tries) {
                    Log::info("Start processing $tries");
                    sleep(3);
                    Log::info("End processing $tries");
                }
            }, function () {
                Log::warning('Cannot get lock');
                // Each release will increase `attempts` so the job will fail after 60 seconds.
                $this->release(10);
            });

        // This will still show (`release(10)` above, or `fail(...)` call won't stop the execution)
        Log::info("After processing");
    }

    public function failed(\Throwable $throwable)
    {
        // This won't prevent exception from being thrown, so you'll see two log messages, one warning
        // and one error level.
        // $this->job is null here.
        Log::warning('Job failed', ['throwable' => $throwable->getMessage()]);
    }
}
