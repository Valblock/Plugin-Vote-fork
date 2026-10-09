<?php

namespace Azuriom\Plugin\Vote\Commands;

use Azuriom\Plugin\Vote\Models\Guest;
use Azuriom\Plugin\Vote\Support\GuestAccounts;
use Illuminate\Console\Command;

class PruneGuestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vote:prune-guests {--hours=24 : Minimum age of the guest accounts to delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete guest accounts created by the vote page that never received a vote.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = 0;

        Guest::with('user')
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->lazyById()
            ->each(function (Guest $guest) use (&$deleted) {
                $user = $guest->user;

                if ($user === null) {
                    $guest->delete();

                    return;
                }

                if ($user->money > 0 || GuestAccounts::hasReferences($user)) {
                    return;
                }

                GuestAccounts::forceDelete($user);
                $deleted++;
            });

        $this->info("{$deleted} guest account(s) deleted.");

        return self::SUCCESS;
    }
}
