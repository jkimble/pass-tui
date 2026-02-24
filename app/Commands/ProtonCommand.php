<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Termwind\render;

abstract class ProtonCommand extends Command
{
    protected function isLoggedIn(): bool
    {
        $result = Process::timeout(20)->run(['pass-cli', 'info']);

        if ($result->failed()) {
            return false;
        }

        return trim($result->output()) !== '';
    }

    protected function ensureLoggedIn(string $title): bool
    {
        if ($this->isLoggedIn()) {
            return true;
        }

        $this->clearScreen();
        $this->renderHeader($title);
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
            return $this->isLoggedIn();
        }

        return false;
    }

    protected function runLogin(): void
    {
        $this->clearScreen();
        $this->renderHeader('Pass TUI: Login');

        render('<div class="p-2 text-gray-300">Launching Proton Pass CLI login flow...</div>');

        $result = Process::timeout(0)->run(['pass-cli', 'login']);

        if ($result->failed()) {
            $this->error("Login failed: " . $result->errorOutput());
            select('Action', ['back' => '⬅️  Go Back']);
            return;
        }

        $this->info('Login complete. Returning...');
        sleep(1);
    }

    protected function showUserInfo(): void
    {
        $this->clearScreen();
        $this->renderHeader('Proton TUI: User Info');

        $data = spin(
            fn() => $this->fetchUserInfo(),
            'Fetching user info...'
        );

        if (!$data) {
            select('Action', ['back' => '⬅️  Go Back']);
            return;
        }

        $rows = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $rows[] = [$key, (string) $value];
        }

        table(['Field', 'Value'], $rows);
        select('Action', ['back' => '⬅️  Go Back']);
    }

    protected function logout(): void
    {
        $this->clearScreen();
        $this->renderHeader('Proton TUI: Logout');

        if (!confirm('Log out of Proton Pass CLI?', default: false)) {
            return;
        }

        $result = Process::timeout(20)->run(['pass-cli', 'logout']);

        if ($result->failed()) {
            $this->error("Logout failed: " . $result->errorOutput());
            select('Action', ['back' => '⬅️  Go Back']);
            return;
        }

        $this->info('Logged out successfully.');
        sleep(1);
    }

    protected function generatePassword(): void
    {
        $this->clearScreen();
        $this->renderHeader('Proton TUI: Generate Password');

        $type = select(
            label: 'Type',
            options: [
                'random' => 'Random Password',
                'passphrase' => 'Passphrase',
                'back' => 'Back',
            ]
        );

        if ($type === 'back') {
            return;
        }

        if ($type === 'random') {
            $length = text('Length', default: '24');
            $numbers = confirm('Include numbers?', default: true);
            $symbols = confirm('Include symbols?', default: true);
            $uppercase = confirm('Include uppercase?', default: true);

            $args = [
                'pass-cli',
                'password',
                'generate',
                'random',
                '--length=' . (int) $length,
                '--numbers=' . ($numbers ? 'true' : 'false'),
                '--symbols=' . ($symbols ? 'true' : 'false'),
                '--uppercase=' . ($uppercase ? 'true' : 'false'),
            ];

            $result = Process::timeout(20)->run($args);
        } else {
            $count = text('Words', default: '5');
            $separator = text('Separator', default: '-');
            $capitalize = confirm('Capitalize words?', default: false);
            $includeNumbers = confirm('Include numbers?', default: false);

            $args = [
                'pass-cli',
                'password',
                'generate',
                'passphrase',
                '--count=' . (int) $count,
                '--separator=' . $separator,
                '--capitalize=' . ($capitalize ? 'true' : 'false'),
                '--numbers=' . ($includeNumbers ? 'true' : 'false'),
            ];

            $result = Process::timeout(20)->run($args);
        }

        if ($result->failed()) {
            $this->error("Password generation failed: " . $result->errorOutput());
            select('Action', ['back' => '⬅️  Go Back']);
            return;
        }

        $password = trim($result->output());
        render("<div class='p-2 text-green-400'>Generated: <span class='font-bold'>{$password}</span></div>");
        select('Action', ['back' => '⬅️  Go Back']);
    }

    protected function searchItems(): void
    {
        $this->clearScreen();
        $this->renderHeader('Proton TUI: Search');

        $query = text('Search term', required: true);

        $vaults = spin(
            fn() => $this->fetchVaults(),
            'Loading vaults...'
        );

        if (!$vaults) {
            select('Action', ['back' => '⬅️  Go Back']);
            return;
        }

        if (isset($vaults['vaults']) && is_array($vaults['vaults'])) {
            $vaults = $vaults['vaults'];
        }

        $matches = [];
        foreach ($vaults as $vault) {
            $vaultName = $vault['name'];
            $items = $this->fetchItems($vault['vault_id'], $vaultName);

            if (isset($items['items']) && is_array($items['items'])) {
                $items = $items['items'];
            }

            foreach ($items as $item) {
                if ($this->itemMatchesQuery($item, $query)) {
                    $matches[] = $this->formatSearchRow($vaultName, $item);
                }
            }
        }

        if (empty($matches)) {
            render('<div class="p-2 text-yellow-400">No matches found.</div>');
            select('Action', ['back' => '⬅️  Go Back']);
            return;
        }

        table(['Vault', 'Title', 'Username/Email', 'Type'], $matches);
        select('Action', ['back' => '⬅️  Go Back']);
    }

    protected function fetchVaults(): ?array
    {
        $result = Process::timeout(60)->run(['pass-cli', 'vault', 'list', '--output', 'json']);

        if ($result->failed()) {
            $this->error("Error: " . $result->errorOutput());
            return null;
        }

        $data = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Proton CLI Error: " . trim($result->output()));
            return null;
        }

        return $data;
    }

    protected function fetchUserInfo(): ?array
    {
        $result = Process::timeout(20)->run(['pass-cli', 'user', 'info', '--output', 'json']);

        if ($result->failed()) {
            $this->error("Error: " . $result->errorOutput());
            return null;
        }

        $data = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Proton CLI Error: " . trim($result->output()));
            return null;
        }

        return $data;
    }

    protected function fetchItems($vaultId, $vaultName): array
    {
        $result = Process::run([
            'pass-cli',
            'item',
            'list',
            $vaultName,
            '--filter-state=active',
            '--output',
            'json'
        ]);

        if ($result->failed()) {
            return [];
        }

        $data = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $data;
    }

    protected function itemMatchesQuery(array $item, string $query): bool
    {
        $query = strtolower($query);

        $title = strtolower($item['content']['title'] ?? '');
        $username = strtolower($item['content']['content']['Login']['username'] ?? '');
        $email = strtolower($item['content']['content']['Login']['email'] ?? '');
        $url = strtolower($item['content']['content']['Login']['url'] ?? '');
        $note = strtolower($item['content']['note'] ?? '');

        return str_contains($title, $query)
            || str_contains($username, $query)
            || str_contains($email, $query)
            || str_contains($url, $query)
            || str_contains($note, $query);
    }

    protected function formatSearchRow(string $vaultName, array $item): array
    {
        $title = $item['content']['title'] ?? 'Untitled';
        $login = $item['content']['content']['Login'] ?? [];

        $username = $login['email'] ?? $login['username'] ?? 'N/A';
        $type = $item['type'] ?? 'N/A';

        return [$vaultName, $title, $username, $type];
    }

    protected function renderHeader($title): void
    {
        render(<<<HTML
            <div class="w-full bg-indigo-900 text-white px-2 py-1">
                <div class="font-bold">$title</div>
            </div>
        HTML);
    }

    protected function clearScreen(): void
    {
        $this->output->write("\033\143\033[3J");
    }
}
