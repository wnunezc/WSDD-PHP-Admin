<?php

namespace Wsdd\PhpAdmin\Profile;

use Wsdd\PhpAdmin\Runtime\RuntimeDetection;
use Wsdd\PhpAdmin\Support\AppConfig;
use Wsdd\PhpAdmin\Support\JsonFile;
final class ProfileRepository
{
    /**
     * @var \Wsdd\PhpAdmin\Support\AppConfig
     */
    private $config;
    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }
    /**
     * @return Wsdd\PhpAdmin\Profile\ProfileLoadResult
     */
    public function load()
    {
        $path = $this->config->profilePath();
        if (!is_file($path)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ProfileLoadResult($path, false, null, null), 'Wsdd\PhpAdmin\Profile\ProfileLoadResult');
        }
        if (!is_readable($path)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ProfileLoadResult($path, true, null, 'Profile file exists but is not readable.'), 'Wsdd\PhpAdmin\Profile\ProfileLoadResult');
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ProfileLoadResult($path, true, null, 'Profile file could not be read.'), 'Wsdd\PhpAdmin\Profile\ProfileLoadResult');
        }
        try {
            $data = \Wsdd\PhpAdmin\Support\JsonCodec::decode($json);
        } catch (\JsonException $exception) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ProfileLoadResult($path, true, null, 'Profile file contains invalid JSON: ' . $exception->getMessage()), 'Wsdd\PhpAdmin\Profile\ProfileLoadResult');
        }
        if (!is_array($data)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ProfileLoadResult($path, true, null, 'Profile JSON root must be an object.'), 'Wsdd\PhpAdmin\Profile\ProfileLoadResult');
        }
        $profile = ProfileDocument::fromArray($data);
        if ($profile->schemaVersion() !== ProfileDocument::SCHEMA_VERSION) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ProfileLoadResult($path, true, null, sprintf('Unsupported profile schema version "%s".', (string) $profile->schemaVersion())), 'Wsdd\PhpAdmin\Profile\ProfileLoadResult');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ProfileLoadResult($path, true, $profile, null), 'Wsdd\PhpAdmin\Profile\ProfileLoadResult');
    }
    /**
     * @return Wsdd\PhpAdmin\Profile\ProfileDocument
     */
    public function initialize(RuntimeDetection $runtime)
    {
        $profile = ProfileDocument::createEmpty($runtime);
        $root = $this->config->persistentRoot();
        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException(sprintf('Unable to create persistent profile root "%s".', $root));
        }
        $path = $this->config->profilePath();
        if (is_file($path)) {
            throw new \RuntimeException(sprintf('Profile already exists at "%s".', $path));
        }
        JsonFile::writeAtomic($path, $profile->toArray(), 0644);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($profile, 'Wsdd\PhpAdmin\Profile\ProfileDocument');
    }
    /**
     * @return Wsdd\PhpAdmin\Profile\ProfileDocument
     */
    public function loadOrInitialize(RuntimeDetection $runtime)
    {
        $result = $this->load();
        if ($result->isLoaded() && $result->profile !== null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($result->profile, 'Wsdd\PhpAdmin\Profile\ProfileDocument');
        }
        if ($result->exists && !$result->isLoaded()) {
            throw new \RuntimeException(isset($result->error) ? $result->error : 'Persistent profile exists but could not be loaded.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->initialize($runtime), 'Wsdd\PhpAdmin\Profile\ProfileDocument');
    }
    /**
     * @return void
     */
    public function save(ProfileDocument $profile)
    {
        $root = $this->config->persistentRoot();
        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException(sprintf('Unable to create persistent profile root "%s".', $root));
        }
        JsonFile::writeAtomic($this->config->profilePath(), $profile->toArray(), 0644);
    }
}
