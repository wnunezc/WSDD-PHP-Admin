<?php

namespace Wsdd\PhpAdmin\Extensions;

use Wsdd\PhpAdmin\Support\ProcessIdentity;
use Wsdd\PhpAdmin\Support\CommandRunner;
use Wsdd\PhpAdmin\Runtime\RuntimeSnapshot;
final class ExtensionInstaller
{
    /**
     * @var \Wsdd\PhpAdmin\Extensions\ExtensionCatalog
     */
    private $catalog;
    public function __construct(ExtensionCatalog $catalog)
    {
        $this->catalog = $catalog;
    }
    /**
     * @return array<string, mixed>
     * @param string $extension
     */
    public function install($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $extension = strtolower($extension);
        if (!ExtensionName::isValid($extension)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('failed', 'Extension name is not safe.'), 'array');
        }
        $resolution = $this->catalog->resolve($extension);
        $entry = $resolution['entry'];
        if ($entry === null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('skipped', (string) $resolution['reason'], ['php_version' => $resolution['php_version'], 'distribution' => $resolution['distribution']]), 'array');
        }
        if (!ProcessIdentity::isRoot()) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('failed', 'Extension installation requires the root agent.'), 'array');
        }
        $loaded = array_map([ExtensionName::class, 'canonical'], RuntimeSnapshot::freshCli()['extensions']);
        if (in_array($extension, $loaded, true) || is_file(rtrim((string) ini_get('extension_dir'), '/') . '/' . $extension . '.so')) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('skipped', 'Extension is already installed; use an activation operation instead of reinstalling or taking ownership of base configuration.'), 'array');
        }
        if (!$this->commandExists('docker-php-ext-install')) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('skipped', 'docker-php-ext-install is not available in this image.'), 'array');
        }
        $commands = [];
        if ($entry['packages'] !== []) {
            if (!$this->commandExists('apt-get')) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('skipped', 'Required system packages exist in catalog, but apt-get is not available.'), 'array');
            }
            $apt = ['apt-get', '-o', 'APT::Get::AllowUnauthenticated=false', '-o', 'Acquire::AllowInsecureRepositories=false', '-o', 'Acquire::AllowDowngradeToInsecureRepositories=false'];
            $commands[] = array_merge($apt, ['update']);
            $commands[] = array_merge($apt, ['install', '-y', '--no-install-recommends'], $entry['packages']);
        }
        if ($entry['configure'] !== []) {
            if (!$this->commandExists('docker-php-ext-configure')) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('skipped', 'docker-php-ext-configure is required but is not available in this image.'), 'array');
            }
            $commands[] = array_merge(['docker-php-ext-configure', $extension], $entry['configure']);
        }
        $commands[] = ['docker-php-ext-install', $extension];
        $steps = [];
        foreach ($commands as $command) {
            $step = $this->run($command);
            $steps[] = $step;
            if ($step['exit_code'] !== 0) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('failed', sprintf('Safe installation failed while running "%s".', implode(' ', $command)), ['steps' => $steps]), 'array');
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('success', sprintf('Extension "%s" was installed with catalog method "%s".', $extension, $entry['install_method']), ['steps' => $steps, 'method' => $entry['install_method'], 'packages' => $entry['packages'], 'php_version' => $resolution['php_version'], 'distribution' => $resolution['distribution']]), 'array');
    }
    /**
     * @param string $extension
     * @return bool
     */
    public function canRemoveBinary($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
    }
    /**
     * @return array<string, mixed>
     * @param string $extension
     */
    public function remove($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        if (!ExtensionName::isValid($extension)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('failed', 'Extension name is not safe.'), 'array');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->result('skipped', sprintf('Safe binary removal for extension "%s" is not supported yet; managed INI removal is handled separately.', strtolower($extension))), 'array');
    }
    /**
     * @param list<string> $command
     * @return array<string, mixed>
     */
    private function run(array $command)
    {
        foreach ($command as $part) {
            if (!is_string($part) || $part === '' || strpos($part, "\x00") !== false) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(['command' => '[invalid]', 'exit_code' => 127, 'output' => 'Command failed safety validation.'], 'array');
            }
        }
        $env = ['DEBIAN_FRONTEND' => 'noninteractive'];
        $result = (new CommandRunner())->run($command, 1800, $env);
        $output = trim($result['stdout'] . "\n" . $result['stderr']);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['command' => implode(' ', $command), 'exit_code' => $result['exit_code'], 'output' => $this->truncate($output)], 'array');
    }
    /**
     * @param string $command
     * @return bool
     */
    private function commandExists($command)
    {
        $command = \Wsdd\PhpAdmin\Support\TypeGuard::check($command, 'string');
        if (!in_array($command, ['apt-get', 'docker-php-ext-configure', 'docker-php-ext-install', 'docker-php-ext-enable'], true)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $output = [];
        $exitCode = 1;
        exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null', $output, $exitCode);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($exitCode === 0 && isset($output[0]) && trim($output[0]) !== '', 'bool');
    }
    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     * @param string $status
     * @param string $message
     */
    private function result($status, $message, array $details = [])
    {
        $status = \Wsdd\PhpAdmin\Support\TypeGuard::check($status, 'string');
        $message = \Wsdd\PhpAdmin\Support\TypeGuard::check($message, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => $status, 'message' => $message, 'details' => $details], 'array');
    }
    /**
     * @param string $output
     * @return string
     */
    private function truncate($output)
    {
        $output = \Wsdd\PhpAdmin\Support\TypeGuard::check($output, 'string');
        if (strlen($output) <= 4000) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($output, 'string');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(substr($output, -4000), 'string');
    }
}
