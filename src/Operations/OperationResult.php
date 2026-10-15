<?php

namespace Wsdd\PhpAdmin\Operations;

final class OperationResult
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var string
     */
    private $id;
    /**
     * @var string
     */
    private $status;
    /**
     * @var string
     */
    private $startedAt;
    /**
     * @var string
     */
    private $finishedAt;
    /**
     * @var string
     */
    private $message;
    /**
     * @var mixed[]
     */
    private $details;
    /**
     * @param array<string, mixed> $details
     * @param string $id
     * @param string $status
     * @param string $startedAt
     * @param string $finishedAt
     * @param string $message
     */
    public function __construct($id, $status, $startedAt, $finishedAt, $message, array $details = [])
    {
        $id = \Wsdd\PhpAdmin\Support\TypeGuard::check($id, 'string');
        $status = \Wsdd\PhpAdmin\Support\TypeGuard::check($status, 'string');
        $startedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($startedAt, 'string');
        $finishedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($finishedAt, 'string');
        $message = \Wsdd\PhpAdmin\Support\TypeGuard::check($message, 'string');
        $this->id = $id;
        $this->status = $status;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
        $this->message = $message;
        $this->details = $details;
        if (!in_array($status, ['processing', 'success', 'failed', 'skipped'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid operation result status "%s".', $status));
        }
    }
    /**
     * @param string $id
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    public static function processing($id)
    {
        $now = gmdate('c');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self($id, 'processing', $now, $now, 'Agent is processing this operation.'), 'Wsdd\PhpAdmin\Operations\OperationResult');
    }
    /**
     * @param array<string, mixed> $details
     * @param string $id
     * @param string $status
     * @param string $startedAt
     * @param string $message
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    public static function finish($id, $status, $startedAt, $message, $details = [])
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self($id, $status, $startedAt, gmdate('c'), $message, $details), 'Wsdd\PhpAdmin\Operations\OperationResult');
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['id' => $this->id, 'status' => $this->status, 'started_at' => $this->startedAt, 'finished_at' => $this->finishedAt, 'message' => $this->message, 'details' => ($this->details === []) ? new \stdClass() : $this->details], 'array');
    }
}
