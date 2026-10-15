<?php

namespace Wsdd\PhpAdmin\Runtime;

final class RuntimeDetection
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var string
     */
    private $phpBinary;
    /**
     * @var string
     */
    private $phpVersion;
    /**
     * @var string
     */
    private $phpSapi;
    /**
     * @var string
     */
    private $runtime;
    /**
     * @var string|null
     */
    private $phpIni;
    /**
     * @var string|null
     */
    private $iniScanDir;
    /**
     * @var mixed[]
     */
    private $loadedExtensions;
    /**
     * @var string
     */
    private $osFamily;
    /**
     * @var string|null
     */
    private $effectiveUser;
    /**
     * @var mixed[]
     */
    private $tools;
    /**
     * @var mixed[]
     */
    private $packageManagers;
    /**
     * @var mixed[]
     */
    private $observations;
    /**
     * @param list<string> $loadedExtensions
     * @param array<string, bool> $tools
     * @param array<string, bool> $packageManagers
     * @param string $phpBinary
     * @param string $phpVersion
     * @param string $phpSapi
     * @param string $runtime
     * @param string|null $phpIni
     * @param string|null $iniScanDir
     * @param string $osFamily
     * @param string|null $effectiveUser
     */
    public function __construct($phpBinary, $phpVersion, $phpSapi, $runtime, $phpIni, $iniScanDir, array $loadedExtensions, $osFamily, $effectiveUser, array $tools, array $packageManagers, array $observations = [])
    {
        $phpBinary = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpBinary, 'string');
        $phpVersion = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpVersion, 'string');
        $phpSapi = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpSapi, 'string');
        $runtime = \Wsdd\PhpAdmin\Support\TypeGuard::check($runtime, 'string');
        $phpIni = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpIni, '?string');
        $iniScanDir = \Wsdd\PhpAdmin\Support\TypeGuard::check($iniScanDir, '?string');
        $osFamily = \Wsdd\PhpAdmin\Support\TypeGuard::check($osFamily, 'string');
        $effectiveUser = \Wsdd\PhpAdmin\Support\TypeGuard::check($effectiveUser, '?string');
        $this->phpBinary = $phpBinary;
        $this->phpVersion = $phpVersion;
        $this->phpSapi = $phpSapi;
        $this->runtime = $runtime;
        $this->phpIni = $phpIni;
        $this->iniScanDir = $iniScanDir;
        $this->loadedExtensions = $loadedExtensions;
        $this->osFamily = $osFamily;
        $this->effectiveUser = $effectiveUser;
        $this->tools = $tools;
        $this->packageManagers = $packageManagers;
        $this->observations = $observations;
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['observations' => $this->observations, 'php' => ['binary' => $this->phpBinary, 'version' => $this->phpVersion, 'sapi' => $this->phpSapi, 'runtime' => $this->runtime, 'ini_file' => $this->phpIni, 'ini_scan_dir' => $this->iniScanDir], 'extensions' => ['loaded' => $this->loadedExtensions, 'loaded_count' => count($this->loadedExtensions)], 'system' => ['os_family' => $this->osFamily, 'effective_user' => $this->effectiveUser, 'tools' => $this->tools, 'package_managers' => $this->packageManagers]], 'array');
    }
}
