<?php

namespace PlinCode\IstatGeographical\Commands;

use Illuminate\Console\Command;

class IstatGeographicalCommand extends Command
{
    public $signature = 'laravel-istat-geographical-dataset';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
