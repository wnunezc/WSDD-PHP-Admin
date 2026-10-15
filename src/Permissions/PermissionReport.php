<?php

namespace Wsdd\PhpAdmin\Permissions;

final class PermissionReport
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var mixed[]
     */
    private $paths;
    /**
     * @var bool
     */
    private $currentProcessIsRoot;
    /**
     * @var bool
     */
    private $agentAvailable;
    /**
     * @param list<PathPermission> $paths
     * @param bool $currentProcessIsRoot
     * @param bool $agentAvailable
     */
    public function __construct(array $paths, $currentProcessIsRoot, $agentAvailable)
    {
        $currentProcessIsRoot = \Wsdd\PhpAdmin\Support\TypeGuard::check($currentProcessIsRoot, 'bool');
        $agentAvailable = \Wsdd\PhpAdmin\Support\TypeGuard::check($agentAvailable, 'bool');
        $this->paths = $paths;
        $this->currentProcessIsRoot = $currentProcessIsRoot;
        $this->agentAvailable = $agentAvailable;
    }
    /**
     * @return bool
     */
    public function canUsePersistentProfile()
    {
        foreach ($this->paths as $path) {
            if ($path->label === 'Persistent profile root') {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($path->canWriteNowOrCreate(), 'bool');
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
    }
    /**
     * @return bool
     */
    public function canWriteManagedIni()
    {
        foreach ($this->paths as $path) {
            if ($path->label === 'Managed INI directory') {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($path->canWriteNowOrCreate(), 'bool');
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
    }
    /**
     * @return bool
     */
    public function canUseOperationsQueue()
    {
        foreach ($this->paths as $path) {
            if ($path->label === 'Operations directory') {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($path->canWriteNowOrCreate(), 'bool');
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['can_use_persistent_profile' => $this->canUsePersistentProfile(), 'can_use_operations_queue' => $this->canUseOperationsQueue(), 'can_write_managed_ini' => $this->canWriteManagedIni(), 'current_process_is_root' => $this->currentProcessIsRoot, 'agent_available' => $this->agentAvailable, 'paths' => array_map(static function (PathPermission $path) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($path->toArray(), 'array');
        }, $this->paths)], 'array');
    }
}
