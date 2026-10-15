<?php

namespace Wsdd\PhpAdmin\Profile;

use Wsdd\PhpAdmin\Runtime\RuntimeDetection;
final class ProfileDocument
{
    const SCHEMA_VERSION = 1;
    /**
     * @var mixed[]
     */
    private $data;
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    /**
     * @return Wsdd\PhpAdmin\Profile\ProfileDocument
     */
    public static function createEmpty(RuntimeDetection $runtime)
    {
        $now = gmdate('c');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self(['schema_version' => self::SCHEMA_VERSION, 'created_at' => $now, 'updated_at' => $now, 'php' => ['version' => $runtime->phpVersion, 'runtime' => $runtime->runtime, 'sapi' => $runtime->phpSapi, 'ini_file' => $runtime->phpIni, 'ini_scan_dir' => $runtime->iniScanDir], 'extensions' => new \stdClass(), 'last_reconciliation' => null]), 'Wsdd\PhpAdmin\Profile\ProfileDocument');
    }
    /**
     * @param array<string, mixed> $data
     * @return Wsdd\PhpAdmin\Profile\ProfileDocument
     */
    public static function fromArray(array $data)
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self($data), 'Wsdd\PhpAdmin\Profile\ProfileDocument');
    }
    /**
     * @return int
     */
    public function schemaVersion()
    {
        $value = isset($this->data['schema_version']) ? $this->data['schema_version'] : null;
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(is_int($value) ? $value : 0, 'int');
    }
    /**
     * @return array<string, mixed>
     */
    public function php()
    {
        $php = isset($this->data['php']) ? $this->data['php'] : [];
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(is_array($php) ? $php : [], 'array');
    }
    /**
     * @return array<string, array<string, mixed>>
     */
    public function extensions()
    {
        $extensions = isset($this->data['extensions']) ? $this->data['extensions'] : [];
        if ($extensions instanceof \stdClass) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check([], 'array');
        }
        if (!is_array($extensions)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check([], 'array');
        }
        $normalized = [];
        foreach ($extensions as $name => $metadata) {
            if (!is_string($name) || !is_array($metadata)) {
                continue;
            }
            $normalized[$name] = $metadata;
        }
        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($normalized, 'array');
    }
    /**
     * @param string $extension
     * @param string $desiredState
     * @param string $source
     * @return Wsdd\PhpAdmin\Profile\ProfileDocument
     */
    public function withExtensionState($extension, $desiredState, $source)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $desiredState = \Wsdd\PhpAdmin\Support\TypeGuard::check($desiredState, 'string');
        $source = \Wsdd\PhpAdmin\Support\TypeGuard::check($source, 'string');
        if (!in_array($desiredState, ['enabled', 'disabled', 'removed'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid desired extension state "%s".', $desiredState));
        }
        $data = $this->data;
        $extensions = $this->extensions();
        $now = gmdate('c');
        $extensions[$extension] = array_merge(isset($extensions[$extension]) ? $extensions[$extension] : [], ['desired_state' => $desiredState, 'managed_by' => 'wsdd-php-admin', 'source' => $source, 'updated_at' => $now]);
        ksort($extensions, SORT_NATURAL | SORT_FLAG_CASE);
        $data['extensions'] = $extensions;
        $data['updated_at'] = $now;
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self($data), 'Wsdd\PhpAdmin\Profile\ProfileDocument');
    }
    /**
     * @return array<string, mixed>
     */
    public function withMetadata(array $extensions, $reconciliation = null)
    {
        $data = $this->data;
        $current = $this->extensions();
        foreach ($extensions as $name => $metadata) {
            if (isset($current[$name])) {
                // Observations must never introduce or replace desired state.
                unset($metadata['desired_state'], $metadata['source'], $metadata['managed_by']);
                $current[$name] = array_replace($current[$name], $metadata);
            }
        }
        $data['extensions'] = $current;
        if ($reconciliation !== null) $data['last_reconciliation'] = $reconciliation;
        $data['updated_at'] = gmdate('c');
        return new self($data);
    }
    /** @return array<string, mixed> */
    public function toArray()
    {
        $data = $this->data;
        $extensions = isset($data['extensions']) ? $data['extensions'] : null;
        if (is_array($extensions) && $extensions === []) {
            $data['extensions'] = new \stdClass();
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($data, 'array');
    }
}
