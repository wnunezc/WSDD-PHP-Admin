<?php

namespace Wsdd\PhpAdmin\Operations;

use Wsdd\PhpAdmin\Extensions\ExtensionName;
final class Operation
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var string
     */
    private $id;
    /**
     * @var string
     */
    private $type;
    /**
     * @var string|null
     */
    private $extension;
    /**
     * @var string
     */
    private $requestedAt;
    /**
     * @var string
     */
    private $requestedBy;
    /**
     * @var mixed[]
     */
    private $payload;
    /**
     * @param array<string, mixed> $payload
     * @param string $id
     * @param string $type
     * @param string|null $extension
     * @param string $requestedAt
     * @param string $requestedBy
     */
    public function __construct($id, $type, $extension, $requestedAt, $requestedBy, array $payload)
    {
        $id = \Wsdd\PhpAdmin\Support\TypeGuard::check($id, 'string');
        $type = \Wsdd\PhpAdmin\Support\TypeGuard::check($type, 'string');
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, '?string');
        $requestedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($requestedAt, 'string');
        $requestedBy = \Wsdd\PhpAdmin\Support\TypeGuard::check($requestedBy, 'string');
        $this->id = $id;
        $this->type = $type;
        $this->extension = $extension;
        $this->requestedAt = $requestedAt;
        $this->requestedBy = $requestedBy;
        $this->payload = $payload;
        $this->validate();
    }
    /**
     * @param array<string, mixed> $data
     * @return Wsdd\PhpAdmin\Operations\Operation
     */
    public static function fromArray($data)
    {
        $id = isset($data['id']) ? $data['id'] : null;
        $type = isset($data['type']) ? $data['type'] : null;
        $extension = isset($data['extension']) ? $data['extension'] : null;
        $requestedAt = isset($data['requested_at']) ? $data['requested_at'] : null;
        $requestedBy = isset($data['requested_by']) ? $data['requested_by'] : null;
        $payload = isset($data['payload']) ? $data['payload'] : [];
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self(is_string($id) ? $id : '', is_string($type) ? $type : '', (is_string($extension) && $extension !== '') ? $extension : null, is_string($requestedAt) ? $requestedAt : '', is_string($requestedBy) ? $requestedBy : '', is_array($payload) ? $payload : []), 'Wsdd\PhpAdmin\Operations\Operation');
    }
    /**
     * @param string $type
     * @param string|null $extension
     * @param string $requestedBy
     * @return Wsdd\PhpAdmin\Operations\Operation
     */
    public static function create($type, $extension, $requestedBy = 'cli')
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self(OperationId::generate(), $type, $extension, gmdate('c'), $requestedBy, []), 'Wsdd\PhpAdmin\Operations\Operation');
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['id' => $this->id, 'type' => $this->type, 'extension' => $this->extension, 'requested_at' => $this->requestedAt, 'requested_by' => $this->requestedBy, 'payload' => ($this->payload === []) ? new \stdClass() : $this->payload], 'array');
    }
    /**
     * @return void
     */
    private function validate()
    {
        if (!OperationId::isValid($this->id)) {
            throw new \InvalidArgumentException('Operation id must be a UUID.');
        }
        if (!OperationType::isAllowed($this->type)) {
            throw new \InvalidArgumentException(sprintf('Operation type "%s" is not allowed.', $this->type));
        }
        if ($this->requiresExtension() && ($this->extension === null || !ExtensionName::isValid($this->extension))) {
            throw new \InvalidArgumentException('Operation extension name is invalid.');
        }
        if (!$this->requiresExtension() && $this->extension !== null && !ExtensionName::isValid($this->extension)) {
            throw new \InvalidArgumentException('Operation extension name is invalid.');
        }
        if ($this->requestedAt === '' || $this->requestedBy === '') {
            throw new \InvalidArgumentException('Operation requester metadata is incomplete.');
        }
    }
    /**
     * @return bool
     */
    private function requiresExtension()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(in_array($this->type, [OperationType::ENABLE_EXTENSION, OperationType::DISABLE_EXTENSION, OperationType::INSTALL_EXTENSION, OperationType::REMOVE_EXTENSION, OperationType::ROLLBACK_EXTENSION], true), 'bool');
    }
}
