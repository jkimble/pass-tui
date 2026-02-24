<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class SearchCommand extends ProtonCommand
{
    protected $signature = 'search';
    protected $description = 'Search items across Proton Pass vaults';

    public function handle()
    {
        if (!$this->ensureLoggedIn('Pass TUI: Search')) {
            return Command::FAILURE;
        }

        $this->searchItems();

        return Command::SUCCESS;
    }
}
