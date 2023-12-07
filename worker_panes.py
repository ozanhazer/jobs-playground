#!/usr/bin/env python3
# Python script to control iTerm2. Installation:
# 1. Install iterm2 `pip install iterm2`
# 2. Enable python API in iTerm2 preferences.

import iterm2
import os
import sys

async def main(connection):
    app = await iterm2.async_get_app(connection)
    window = app.current_terminal_window
    domain = iterm2.broadcast.BroadcastDomain()

    # Define base path and project name variables
    base_path = os.path.dirname(os.path.realpath(sys.argv[0]))

    if window is not None:
        tab = await window.async_create_tab()

        left_pane1 = tab.current_session
        log_pane = await left_pane1.async_split_pane(vertical=True)
        left_pane2 = await left_pane1.async_split_pane(vertical=False)
        left_pane3 = await left_pane1.async_split_pane(vertical=False)
        left_pane4 = await left_pane1.async_split_pane(vertical=False)

        # Run workers
        worker_panes = [left_pane1, left_pane2, left_pane3, left_pane4]
        for worker_pane in worker_panes:
            domain.add_session(worker_pane)
            await worker_pane.async_send_text(f'cd {base_path}; php artisan queue:work\n')

        await log_pane.async_send_text(f'cd {base_path}; tail -f storage/logs/laravel.log\n')

        # Enable broadcast to all worker panes
        await iterm2.async_set_broadcast_domains(connection, broadcast_domains=[domain])
    else:
        print("No current window")

iterm2.run_until_complete(main)
