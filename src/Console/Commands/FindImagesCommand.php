<?php

namespace Jerox\EggLibrary\Console\Commands;

use Illuminate\Console\Command;
use Jerox\EggLibrary\AutoImages;

class FindImagesCommand extends Command
{
    protected $signature = 'egg-library:find-images
                            {--limit=25 : Look up at most this many eggs per run}
                            {--retry : Search eggs without a match again right away}';

    protected $description = 'Looks up Steam artwork for catalog eggs that have no image yet';

    public function handle(): int
    {
        $stats = AutoImages::refresh((int) $this->option('limit'), (bool) $this->option('retry'), fn (string $line) => $this->line($line));

        $this->info("{$stats['found']} found, {$stats['missed']} without match, {$stats['errors']} errors, {$stats['open']} left");

        return self::SUCCESS;
    }
}
