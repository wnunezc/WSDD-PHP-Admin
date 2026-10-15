<?php

namespace Wsdd\PhpAdmin\Recovery;

use Wsdd\PhpAdmin\Extensions\ExtensionName;
use Wsdd\PhpAdmin\Ini\ManagedIniRepository;
use Wsdd\PhpAdmin\Support\AppConfig;
use Wsdd\PhpAdmin\Support\JsonFile;
final class IniBackupRepository
{
    /**
     * @var \Wsdd\PhpAdmin\Support\AppConfig
     */
    private $config;
    /**
     * @var \Wsdd\PhpAdmin\Ini\ManagedIniRepository
     */
    private $managedIni;
    public function __construct(AppConfig $config, ManagedIniRepository $managedIni)
    {
        $this->config = $config;
        $this->managedIni = $managedIni;
    }
    /**
     * @param string $extension
     * @param string $reason
     * @return string
     */
    public function capture($extension, $reason)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $reason = \Wsdd\PhpAdmin\Support\TypeGuard::check($reason, 'string');
        $this->assertSafeExtension($extension);
        $directory = $this->backupDirectory();
        $path = $this->managedIni->managedIniPath($extension);
        $snapshot = ['extension' => strtolower($extension), 'reason' => $reason, 'captured_at' => gmdate('c'), 'managed_ini_path' => $path, 'existed' => is_file($path), 'content' => (is_file($path) && is_readable($path)) ? (string) file_get_contents($path) : null];
        $backupPath = $this->joinPath($directory, gmdate('YmdHis') . '-' . strtolower($extension) . '-' . bin2hex(random_bytes(4)) . '.json');
        JsonFile::writeAtomic($backupPath, $snapshot, 0640);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($backupPath, 'string');
    }
    /**
     * @return array<string, mixed>
     * @param string $extension
     */
    public function restoreLatest($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        $backup = $this->latestBackup($extension);
        if ($backup === null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => 'skipped', 'message' => sprintf('No managed INI backup exists for extension "%s".', $extension)], 'array');
        }
        $data = json_decode((string) file_get_contents($backup), true);
        if (!is_array($data) || (isset($data['extension']) ? $data['extension'] : null) !== strtolower($extension)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => 'failed', 'message' => sprintf('Latest backup for extension "%s" is invalid.', $extension), 'backup_path' => $backup], 'array');
        }
        if ((isset($data['existed']) ? $data['existed'] : false) === true) {
            $content = isset($data['content']) ? $data['content'] : null;
            if (!is_string($content)) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => 'failed', 'message' => sprintf('Latest backup for extension "%s" has no restorable content.', $extension), 'backup_path' => $backup], 'array');
            }
            $this->managedIni->replaceManagedIni($extension, $content);
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => 'success', 'message' => sprintf('Managed INI for extension "%s" was restored from backup.', $extension), 'backup_path' => $backup, 'restored_state' => 'present'], 'array');
        }
        $removed = $this->managedIni->removeManagedIni($extension);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => 'success', 'message' => sprintf('Managed INI for extension "%s" was restored to absent state.', $extension), 'backup_path' => $backup, 'restored_state' => 'absent', 'removed_path' => $removed], 'array');
    }
    /**
     * @param string $extension
     * @return string|null
     */
    private function latestBackup($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $directory = $this->backupDirectory();
        $files = glob($this->joinPath($directory, '*-' . strtolower($extension) . '-*.json'));
        if ($files === false || $files === []) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
        }
        rsort($files, SORT_NATURAL);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(is_readable($files[0]) ? $files[0] : null, '?string');
    }
    /**
     * @return string
     */
    private function backupDirectory()
    {
        $directory = $this->joinPath($this->config->backupDir(), 'ini');
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create backup directory "%s".', $directory));
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($directory, 'string');
    }
    /**
     * @param string $extension
     * @return void
     */
    private function assertSafeExtension($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        if (!ExtensionName::isValid($extension)) {
            throw new \InvalidArgumentException('Extension name is not safe.');
        }
    }
    /**
     * @param string $root
     * @param string $leaf
     * @return string
     */
    private function joinPath($root, $leaf)
    {
        $root = \Wsdd\PhpAdmin\Support\TypeGuard::check($root, 'string');
        $leaf = \Wsdd\PhpAdmin\Support\TypeGuard::check($leaf, 'string');
        $separator = (strpos($root, '/') !== false && strpos($root, '\\') === false) ? '/' : DIRECTORY_SEPARATOR;
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(rtrim($root, '/\\') . $separator . ltrim($leaf, '/\\'), 'string');
    }
}
