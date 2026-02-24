<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\select;
use function Termwind\render;

class LogoutCommand extends ProtonCommand
{
    protected $signature = 'logout';
    protected $description = 'Log out of Proton Pass CLI';

    public function handle()
    {
        if (!$this->isLoggedIn()) {
            $this->clearScreen();
            $this->renderHeader('Pass TUI: Logout');
            render('<div class="p-2 text-yellow-400">⚠️ You are not logged in.</div>');
            select('Action', ['back' => '⬅️  Go Back']);
            return Command::FAILURE;
        }

        $this->logout();

        return Command::SUCCESS;
    }
}
