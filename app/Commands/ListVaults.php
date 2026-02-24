<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Termwind\render;

class ListVaults extends ProtonCommand
{
    protected $signature = 'launch';
    protected $description = 'Interactive Terminal UI for Proton Pass';

    public function handle()
    {
        while (true) {
            $this->clearScreen();
            $this->renderHeader('Pass TUI: Home');

            if (!$this->isLoggedIn()) {
                render('<div class="p-2 text-yellow-400">⚠️ You are not logged in to Proton Pass CLI.</div>');
                $action = select(
                    label: 'Action',
                    options: [
                        'login' => 'Log In',
                        'exit' => 'Exit',
                    ]
                );

                if ($action === 'login') {
                    $this->runLogin();
                    continue;
                }

                render('<div class="px-1 text-gray-400">Goodbye!</div>');
                break;
            }

            $action = select(
                label: 'What would you like to do?',
                options: [
                    'vaults' => 'List Vaults',
                    'user' => 'Show User Info',
                    'password' => 'Generate Password',
                    'search' => 'Search',
                    'logout' => 'Log Out',
                    'exit' => 'Exit Pass TUI',
                ]
            );

            if ($action === 'exit') {
                render('<div class="px-1 text-gray-400">Goodbye!</div>');
                break;
            }

            if ($action === 'vaults') {
                $this->listVaultsFlow();
                continue;
            }

            if ($action === 'user') {
                $this->showUserInfo();
                continue;
            }

            if ($action === 'logout') {
                $this->logout();
                continue;
            }

            if ($action === 'password') {
                $this->generatePassword();
                continue;
            }

            if ($action === 'search') {
                $this->searchItems();
                continue;
            }
        }

        return Command::SUCCESS;
    }

    protected function listVaultsFlow()
    {
        while (true) {
            $this->clearScreen();
            $this->renderHeader('Proton TUI: Vaults');

            $vaults = spin(
                fn() => $this->fetchVaults(),
                'Connecting to Proton...'
            );

            if (!$vaults) {
                return;
            }

            if (isset($vaults) && is_array($vaults)) {
                $vaults = $vaults['vaults'];
            }

            $options = [];
            foreach ($vaults as $vault) {
                $label = $vault['name'];
                $options[$vault['vault_id']] = $label;
            }

            $options['back'] = 'Back to Home';

            render('<div class="mt-1 text-indigo-400 font-bold">Select a vault to open:</div>');

            $selectedId = select(
                label: '',
                options: $options,
                scroll: 10,
                validate: fn ($value) => match ($value) {
                    'back' => null,
                    default => null,
                }
            );

            if ($selectedId === 'back') {
                return;
            }

            $vaultName = $options[$selectedId] ?? 'Unknown';
            $this->viewVaultItems($selectedId, $vaultName);
        }
    }

    /**
     * Display items within a specific vault
     */
    protected function viewVaultItems($vaultId, $vaultName)
    {
        $showPasswords = false;

        while (true) {
            $this->clearScreen();
            $this->renderHeader("Proton TUI: Vaults > $vaultName");

            $items = spin(
                fn() => $this->fetchItems($vaultId, $vaultName),
                $showPasswords ? 'Revealing passwords...' : 'Fetching items...'
            );

            if (isset($items['items']) && is_array($items['items'])) {
                $items = $items['items'];
            }

            if (empty($items)) {
                render('<div class="p-2 text-yellow-400">⚠️ This vault is empty.</div>');
                select('Action', ['back' => '⬅️  Go Back']);
                return;
            }

            $rows = array_map(function ($item) use ($showPasswords) {
                return [
                    $item['content']['title'],
                    $item['content']['content']['Login']['email'] ?? $item['content']['content']['Login']['username'] ?? 'N/A',
                    // $item['content']['note'] ?? 'N/A',
                    $showPasswords ? ($item['content']['content']['Login']['password'] ?? 'N/A') : '********',
                    !empty($item['content']['content']['Login']['totp_uri']) ? 'Yes' : 'No',
                ];
            }, $items);

            table(['Title', 'Username', 'Password', '2FA'], $rows);

            $action = select(
                label: 'Actions',
                options: [
                    'show' => $showPasswords ? 'Hide Passwords' : 'Reveal Passwords',
                    'create' => 'Create New Login',
                    'back' => 'Back to Vaults',
                ]
            );

            if ($action === 'back') return;

            if ($action === 'create') {
                $this->createLogin($vaultName);
            }

            if ($action === 'show') {
                $showPasswords = !$showPasswords;
            }
        }
    }

    protected function createLogin($vaultName = null)
    {
        while (true) {
            $this->clearScreen();

            if (!$vaultName) {
                render('<div class="p-2 text-yellow-400">⚠️ There has been an error.</div>');
                select('Action', ['back' => '⬅️  Go Back']);
                return;
            }

            $this->renderHeader("Proton TUI: Vaults > $vaultName > New Login");

            $this->info("Create a new login in this vault ($vaultName).");

            $title = text('Title', required: true);
            $username = text('Username (optional)');
            $email = text('Email (optional)');
            $password = password('Password');
            $url = text('URL (optional)');
            $confirm = confirm('Generate login?', default: false);

            if (!$confirm) return;

            $data = compact('title', 'username', 'email', 'password', 'url', 'vaultName');
            spin(
                $result = function () use ($data) {
                    $this->createItem($data);
                },
                'Generating login...'
            );

            if (!empty($result)) {
                $this->info("Login created successfully! Redirecting back to {$vaultName}.");
            } else {
                $this->error("Error creating login. Redirecting back to {$vaultName}.");
            }

            sleep(3);
            return;
        }
    }

    protected function createItem(Array $data)
    {
        if (!$data) return;

        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $url = $data['url'] ?? '';

        if (!$data['title']) return;

        $result = Process::timeout(60)->run([
            'pass-cli',
            'item',
            'create',
            'login',
            '--vault-name=' . $data['vaultName'],
            '--title=' . $data['title'],
            '--username=' . $username,
            '--email=' . $email,
            '--password=' . $password,
            '--url=' . $url,
        ])->throw();

        return $result->output();
    }

    /**
     * Simple date formatting with styling
     */
    protected function formatDate($timestamp)
    {
        $date = \Carbon\Carbon::parse($timestamp);
        if ($date->diffInDays() < 7) {
            return "<span class='text-green-400'>" . $date->diffForHumans() . "</span>";
        }
        return "<span class='text-gray-500'>" . $date->format('M d, Y') . "</span>";
    }
}
