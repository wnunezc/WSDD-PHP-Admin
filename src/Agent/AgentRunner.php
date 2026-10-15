<?php

namespace Wsdd\PhpAdmin\Agent;

use Wsdd\PhpAdmin\Operations\OperationRepository;
use Wsdd\PhpAdmin\Operations\OperationResult;
use Wsdd\PhpAdmin\Reconciliation\ManagedReconciler;
use Wsdd\PhpAdmin\Support\ProcessIdentity;
final class AgentRunner
{
    private $lease;
    private $startupReady = false;
    /**
     * @var \Wsdd\PhpAdmin\Operations\OperationRepository
     */
    private $operations;
    /**
     * @var \Wsdd\PhpAdmin\Agent\OperationProcessor
     */
    private $processor;
    /**
     * @var \Wsdd\PhpAdmin\Agent\AgentLogger
     */
    private $logger;
    /**
     * @var \Wsdd\PhpAdmin\Reconciliation\ManagedReconciler|null
     */
    private $startupReconciler;
    /**
     * @param Wsdd\PhpAdmin\Reconciliation\ManagedReconciler|null $startupReconciler
     */
    public function __construct(OperationRepository $operations, OperationProcessor $processor, AgentLogger $logger, $startupReconciler = null)
    {
        $startupReconciler = \Wsdd\PhpAdmin\Support\TypeGuard::check($startupReconciler, '?Wsdd\PhpAdmin\Reconciliation\ManagedReconciler');
        $this->operations = $operations;
        $this->processor = $processor;
        $this->logger = $logger;
        $this->startupReconciler = $startupReconciler;
    }
    /**
     * @return int
     */
    public function runOnce()
    {
        $owned = $this->lease === null;
        if ($owned) $this->lease = new \Wsdd\PhpAdmin\Support\InstanceLock($this->operations->consumerLockPath());
        try {
            $processed = 0;
            $this->logger->info(sprintf('agent:once started as user=%s root=%s', (($__wsddPortValue1 = ProcessIdentity::effectiveUser()) !== null) ? $__wsddPortValue1 : 'unknown', ProcessIdentity::isRoot() ? 'yes' : 'no'));
            foreach ($this->operations->pending() as $operation) {
                $this->logger->info(sprintf('processing operation id=%s type=%s', $operation->id, $operation->type));
                $this->operations->writeResult(OperationResult::processing($operation->id));
                $result = $this->processor->process($operation);
                $this->operations->writeResult($result);
                $this->logger->info(sprintf('operation id=%s finished status=%s message=%s', $result->id, $result->status, $result->message));
                $processed++;
            }
            $this->logger->info(sprintf('agent:once finished processed=%d', $processed));
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($processed, 'int');
        } finally {
            if ($owned) {
                $this->lease->release();
                $this->lease = null;
            }
        }
    }
    /**
     * @param int $sleepSeconds
     * @return void
     */
    public function runLoop($sleepSeconds = 2)
    {
        $sleepSeconds = \Wsdd\PhpAdmin\Support\TypeGuard::check($sleepSeconds, 'int');
        $this->lease = new \Wsdd\PhpAdmin\Support\InstanceLock($this->operations->consumerLockPath());
        try {
            $this->logger->info('agent loop started');
            $this->startupReady = $this->runStartupReconciliation();
            while (true) {
                try {
                    $this->runOnce();
                    $this->publishStatus($this->startupReady ? 'ready' : 'degraded');
                } catch (\Exception $exception) {
                    $this->logger->error($exception->getMessage());
                    $this->publishStatus('failed');
                } catch (\Throwable $exception) {
                    $this->logger->error($exception->getMessage());
                    $this->publishStatus('failed');
                }
                sleep($sleepSeconds);
            }
        } finally {
            $this->lease->release();
            $this->lease = null;
        }
    }

    private function publishStatus($state)
    {
        $this->operations->publishAgentStatus(['pid' => getmypid(), 'root' => realpath(dirname(dirname(__DIR__))), 'start_id' => getenv('WSDD_PHP_ADMIN_START_ID'), 'state' => $state, 'updated_at' => microtime(true)]);
    }
    /**
     * @return void
     */
    public function runStartupReconciliation()
    {
        if ($this->startupReconciler === null) {
            return true;
        }
        try {
            $this->logger->info('startup reconciliation started');
            $report = $this->startupReconciler->apply();
            $this->logger->info(sprintf('startup reconciliation finished status=%s applied=%d skipped=%d message=%s', (string) (isset($report['status']) ? $report['status'] : 'unknown'), is_array(isset($report['applied']) ? $report['applied'] : null) ? count($report['applied']) : 0, is_array(isset($report['skipped']) ? $report['skipped'] : null) ? count($report['skipped']) : 0, (string) (isset($report['message']) ? $report['message'] : '')));
            return isset($report['status']) && $report['status'] === 'success';
        } catch (\Exception $exception) {
            $this->logger->error('startup reconciliation failed: ' . $exception->getMessage());
            return false;
        } catch (\Throwable $exception) {
            $this->logger->error('startup reconciliation failed: ' . $exception->getMessage());
            return false;
        }
    }
}
