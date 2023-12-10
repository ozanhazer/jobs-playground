<?php

use App\Jobs\ThrottleExceptionsJob;
use Illuminate\Support\Facades\Artisan;

Artisan::command('dispatch:throttle-exceptions', function () {
    ThrottleExceptionsJob::dispatch(ThrottleExceptionsJob::RECOVERABLE_EXCEPTION);
    ThrottleExceptionsJob::dispatch(ThrottleExceptionsJob::UNRECOVERABLE_EXCEPTION);
    ThrottleExceptionsJob::dispatch();
})->purpose('Dispatch test jobs for throttle-exceptions');
