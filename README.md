# Laravel Jobs Playground

A repo to experiment with Laravel queue throttling and rate limit control.

## Dispatching Jobs from Artisan Tinker
You can dispatch jobs from `php artisan tinker` by: 

`Queue::push(new TestJob())`

`dispatch(new TestJob())` or `TestJob::dispatch()` won't work because of how it was designed.

## Real time log inspection

If you are using iTerm2, `./worker_panes.py` would create a new tabs with 4 panes, each running one worker
script (`php artisan queue:work`). You can visualize how workers work.
