<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // leave requests waiting too long: one email a day to each unit checker (needs the
        // server's cron to run `php artisan schedule:run` every minute)
        $schedule->command('pcg:leave-reminders')->dailyAt('08:00')->timezone('Asia/Manila')->withoutOverlapping();
        // clearances ending in 30 / 7 / 0 days: one email to the member (pcg.clearance.reminder_days)
        $schedule->command('pcg:clearance-reminders')->dailyAt('07:30')->timezone('Asia/Manila')->withoutOverlapping();
        // monitoring: health every hour (email when something fails), newest backup checked every Monday
        $schedule->command('pcg:monitor --alert')->hourly()->withoutOverlapping();
        $schedule->command('pcg:backup-verify --alert')->weeklyOn(1, '06:30')->timezone('Asia/Manila')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
