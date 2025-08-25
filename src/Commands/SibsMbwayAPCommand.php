<?php

namespace Rodrigolopespt\SibsMbwayAP\Commands;

use Illuminate\Console\Command;

class SibsMbwayAPCommand extends Command
{
    public $signature = 'laravel-sibs-mbway-authorized-payments';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
