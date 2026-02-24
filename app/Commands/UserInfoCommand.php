<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class UserInfoCommand extends ProtonCommand
{
    protected $signature = 'user';
    protected $description = 'Show Proton Pass user info';

    public function handle()
    {
        if (!$this->ensureLoggedIn('Pass TUI: User Info')) {
            return Command::FAILURE;
        }

        $this->showUserInfo();

        return Command::SUCCESS;
    }
}
