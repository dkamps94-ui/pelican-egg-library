<?php

namespace Jerox\EggLibrary\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Jerox\EggLibrary\Console\Commands\FindImagesCommand;

class EggLibraryPluginProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // A few eggs per hour until every game has artwork, afterwards only new catalog eggs
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(FindImagesCommand::class)->hourlyAt(17)->withoutOverlapping(60)
                ->when(fn () => (bool) config('egg-library.image_search', true));
        });
    }
}
