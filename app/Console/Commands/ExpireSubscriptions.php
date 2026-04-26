<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:expire-subscriptions')]
#[Description('Command description')]
class ExpireSubscriptions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expired = \Illuminate\Support\Facades\DB::table('subscriptions')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        $this->info("Expired subscriptions updated: " . $expired);
    }
}
