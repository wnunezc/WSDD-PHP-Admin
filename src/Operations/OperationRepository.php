<?php

namespace Wsdd\PhpAdmin\Operations;

use Wsdd\PhpAdmin\Support\AppConfig;
use Wsdd\PhpAdmin\Support\JsonFile;
final class OperationRepository
{
    /**
     * @var \Wsdd\PhpAdmin\Support\AppConfig
     */
    private $config;
    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }

    public function consumerLockPath()
    {
        return $this->config->persistentRoot() . '/runtime/agent.lock';
    }

    public function publishAgentStatus(array $status)
    {
        JsonFile::writeAtomic($this->config->persistentRoot() . '/runtime/agent-status.json', $status, 0644);
    }
    /**
     * @return void
     */
    public function create(Operation $operation)
    {
        $this->ensureDirectory();
        JsonFile::writeAtomic($this->operationPath($operation->id), $operation->toArray(), 0644);
    }
    /**
     * @return list<Operation>
     */
    public function pending()
    {
        $operations = [];
        foreach ($this->operationFiles() as $path) {
            $operation = $this->loadOperationFile($path);
            if ($operation !== null && !$this->hasTerminalResult($operation->id)) {
                $operations[] = $operation;
            }
        }
        usort($operations, static function (Operation $left, Operation $right) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(strcmp($left->requestedAt, $right->requestedAt), 'int');
        });
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($operations, 'array');
    }
    /**
     * @return array<string, mixed>
     */
    public function listing()
    {
        $items = [];
        foreach ($this->operationFiles() as $path) {
            $operation = $this->loadOperationFile($path);
            if ($operation === null) {
                continue;
            }
            $resultPath = $this->resultPath($operation->id);
            $result = null;
            if (is_file($resultPath) && is_readable($resultPath)) {
                $result = json_decode((string) file_get_contents($resultPath), true);
            }
            $items[] = ['operation' => $operation->toArray(), 'result' => is_array($result) ? $this->normalizeResult($result) : null];
        }
        usort($items, static function (array $left, array $right) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(strcmp((string) (isset($right['operation']['requested_at']) ? $right['operation']['requested_at'] : ''), (string) (isset($left['operation']['requested_at']) ? $left['operation']['requested_at'] : '')), 'int');
        });
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['operations_dir' => $this->config->operationsDir(), 'items' => $items], 'array');
    }
    /**
     * @return void
     */
    public function writeResult(OperationResult $result)
    {
        $this->ensureDirectory();
        JsonFile::writeAtomic($this->resultPath($result->id), $result->toArray(), 0644);
    }
    /**
     * @param string $id
     * @return string
     */
    public function operationPath($id)
    {
        $id = \Wsdd\PhpAdmin\Support\TypeGuard::check($id, 'string');
        $this->assertSafeId($id);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->joinPath($this->config->operationsDir(), $id . '.json'), 'string');
    }
    /**
     * @param string $id
     * @return string
     */
    public function resultPath($id)
    {
        $id = \Wsdd\PhpAdmin\Support\TypeGuard::check($id, 'string');
        $this->assertSafeId($id);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->joinPath($this->config->operationsDir(), $id . '.result.json'), 'string');
    }
    /**
     * @return void
     */
    private function ensureDirectory()
    {
        $directory = $this->config->operationsDir();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create operations directory "%s".', $directory));
        }
    }
    /**
     * @return list<string>
     */
    private function operationFiles()
    {
        $directory = $this->config->operationsDir();
        if (!is_dir($directory)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check([], 'array');
        }
        $files = glob($this->joinPath($directory, '*.json'));
        if ($files === false) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check([], 'array');
        }
        $filtered = array_values(array_filter($files, static function ($path) {
            $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(substr_compare($path, '.result.json', -strlen('.result.json')) !== 0 && is_file($path), 'bool');
        }));
        sort($filtered, SORT_NATURAL);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($filtered, 'array');
    }
    /**
     * @param string $path
     * @return Wsdd\PhpAdmin\Operations\Operation|null
     */
    private function loadOperationFile($path)
    {
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        $name = basename($path);
        if (substr_compare($name, '.json', -strlen('.json')) !== 0) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?Wsdd\PhpAdmin\Operations\Operation');
        }
        $id = (string) substr($name, 0, -5);
        if (!OperationId::isValid($id)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?Wsdd\PhpAdmin\Operations\Operation');
        }
        $expected = $this->operationPath($id);
        if ($this->normalize($expected) !== $this->normalize($path) || !is_readable($path)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?Wsdd\PhpAdmin\Operations\Operation');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?Wsdd\PhpAdmin\Operations\Operation');
        }
        try {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(Operation::fromArray($data), '?Wsdd\PhpAdmin\Operations\Operation');
        } catch (\InvalidArgumentException $exception) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?Wsdd\PhpAdmin\Operations\Operation');
        }
    }
    /**
     * @param string $id
     * @return void
     */
    private function assertSafeId($id)
    {
        $id = \Wsdd\PhpAdmin\Support\TypeGuard::check($id, 'string');
        if (!OperationId::isValid($id)) {
            throw new \InvalidArgumentException('Operation id must be a safe UUID.');
        }
    }
    /**
     * @param string $id
     * @return bool
     */
    private function hasTerminalResult($id)
    {
        $id = \Wsdd\PhpAdmin\Support\TypeGuard::check($id, 'string');
        $path = $this->resultPath($id);
        if (!is_file($path) || !is_readable($path)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $result = json_decode((string) file_get_contents($path), true);
        if (!is_array($result)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(in_array(isset($result['status']) ? $result['status'] : null, ['success', 'failed', 'skipped'], true), 'bool');
    }
    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeResult(array $result)
    {
        if ((isset($result['details']) ? $result['details'] : null) === []) {
            $result['details'] = new \stdClass();
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($result, 'array');
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
    /**
     * @param string $path
     * @return string
     */
    private function normalize($path)
    {
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(str_replace('\\', '/', $path), 'string');
    }
}
