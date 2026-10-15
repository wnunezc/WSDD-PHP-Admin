<?php

namespace Wsdd\PhpAdmin\Reconciliation;

final class ReconciliationIssue
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var string
     */
    private $severity;
    /**
     * @var string
     */
    private $subject;
    /**
     * @var string
     */
    private $message;
    /**
     * @param string $severity
     * @param string $subject
     * @param string $message
     */
    public function __construct($severity, $subject, $message)
    {
        $severity = \Wsdd\PhpAdmin\Support\TypeGuard::check($severity, 'string');
        $subject = \Wsdd\PhpAdmin\Support\TypeGuard::check($subject, 'string');
        $message = \Wsdd\PhpAdmin\Support\TypeGuard::check($message, 'string');
        $this->severity = $severity;
        $this->subject = $subject;
        $this->message = $message;
    }
    /**
     * @return array<string, string>
     */
    public function toArray()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['severity' => $this->severity, 'subject' => $this->subject, 'message' => $this->message], 'array');
    }
}
