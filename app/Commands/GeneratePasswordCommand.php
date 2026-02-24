<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class GeneratePasswordCommand extends ProtonCommand
{
    protected $signature = 'generate';
    protected $description = 'Generate a password using Proton Pass CLI';

    public function handle()
    {
        if (!$this->ensureLoggedIn('Pass TUI: Generate Password')) {
            return Command::FAILURE;
        }

        $this->generatePassword();

        return Command::SUCCESS;
    }
}
