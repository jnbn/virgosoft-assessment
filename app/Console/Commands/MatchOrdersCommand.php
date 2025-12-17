<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MatchOrders;
use Illuminate\Console\Command;

class MatchOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:match';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Match pending orders in the orderbook';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting order matching...');

        // Run synchronously instead of dispatching to queue
        MatchOrders::dispatchSync();

        $this->info('Order matching completed.');

        return Command::SUCCESS;
    }
}
