<?php

namespace Wsdd\PhpAdmin\Reconciliation;

final class ReconciliationReport
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var bool
     */
    private $profileLoaded;
    /**
     * @var string
     */
    private $mode;
    /**
     * @var mixed[]
     */
    private $issues;
    /**
     * @param list<ReconciliationIssue> $issues
     * @param bool $profileLoaded
     * @param string $mode
     */
    public function __construct($profileLoaded, $mode, array $issues)
    {
        $profileLoaded = \Wsdd\PhpAdmin\Support\TypeGuard::check($profileLoaded, 'bool');
        $mode = \Wsdd\PhpAdmin\Support\TypeGuard::check($mode, 'string');
        $this->profileLoaded = $profileLoaded;
        $this->mode = $mode;
        $this->issues = $issues;
    }
    /**
     * @return int
     */
    public function issueCount()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(count(array_filter($this->issues, static function (ReconciliationIssue $issue) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($issue->severity !== 'ok', 'bool');
        })), 'int');
    }
    /**
     * @return bool
     */
    public function hasActionableIssues()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->issueCount() > 0, 'bool');
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['profile_loaded' => $this->profileLoaded, 'mode' => $this->mode, 'issue_count' => $this->issueCount(), 'issues' => array_map(static function (ReconciliationIssue $issue) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($issue->toArray(), 'array');
        }, $this->issues)], 'array');
    }
}
