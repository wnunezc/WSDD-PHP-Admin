<?php

namespace Wsdd\PhpAdmin\Agent;

use Wsdd\PhpAdmin\Audit\AuditLogger;
use Wsdd\PhpAdmin\Ini\ManagedIniRepository;
use Wsdd\PhpAdmin\Extensions\ExtensionInstaller;
use Wsdd\PhpAdmin\Operations\Operation;
use Wsdd\PhpAdmin\Operations\OperationResult;
use Wsdd\PhpAdmin\Operations\OperationType;
use Wsdd\PhpAdmin\Profile\ProfileRepository;
use Wsdd\PhpAdmin\Profile\ProfileStateRecorder;
use Wsdd\PhpAdmin\Recovery\IniBackupRepository;
use Wsdd\PhpAdmin\Reconciliation\ManagedReconciler;
use Wsdd\PhpAdmin\Service\ServiceReloader;
use Wsdd\PhpAdmin\Support\ProcessIdentity;
use Wsdd\PhpAdmin\Runtime\RuntimeVerifier;
final class OperationProcessor
{
    /**
     * @var \Wsdd\PhpAdmin\Ini\ManagedIniRepository
     */
    private $managedIni;
    /**
     * @var \Wsdd\PhpAdmin\Profile\ProfileRepository
     */
    private $profiles;
    /**
     * @var \Wsdd\PhpAdmin\Service\ServiceReloader
     */
    private $reloader;
    /**
     * @var \Wsdd\PhpAdmin\Profile\ProfileStateRecorder
     */
    private $profileRecorder;
    /**
     * @var \Wsdd\PhpAdmin\Reconciliation\ManagedReconciler
     */
    private $managedReconciler;
    /**
     * @var \Wsdd\PhpAdmin\Extensions\ExtensionInstaller
     */
    private $extensionInstaller;
    /**
     * @var \Wsdd\PhpAdmin\Recovery\IniBackupRepository
     */
    private $backups;
    /**
     * @var \Wsdd\PhpAdmin\Audit\AuditLogger
     */
    private $audit;
    /**
     * @var \Wsdd\PhpAdmin\Runtime\RuntimeVerifier|null
     */
    private $runtimeVerifier;
    /**
     * @param Wsdd\PhpAdmin\Runtime\RuntimeVerifier|null $runtimeVerifier
     */
    public function __construct(ManagedIniRepository $managedIni, ProfileRepository $profiles, ServiceReloader $reloader, ProfileStateRecorder $profileRecorder, ManagedReconciler $managedReconciler, ExtensionInstaller $extensionInstaller, IniBackupRepository $backups, AuditLogger $audit, $runtimeVerifier = null)
    {
        $runtimeVerifier = \Wsdd\PhpAdmin\Support\TypeGuard::check($runtimeVerifier, '?Wsdd\PhpAdmin\Runtime\RuntimeVerifier');
        $this->managedIni = $managedIni;
        $this->profiles = $profiles;
        $this->reloader = $reloader;
        $this->profileRecorder = $profileRecorder;
        $this->managedReconciler = $managedReconciler;
        $this->extensionInstaller = $extensionInstaller;
        $this->backups = $backups;
        $this->audit = $audit;
        $this->runtimeVerifier = $runtimeVerifier;
    }
    /**
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    public function process(Operation $operation)
    {
        $startedAt = gmdate('c');
        try {
            switch ($operation->type) {
                case OperationType::ENABLE_EXTENSION:
                    $result = $this->enableExtension($operation, $startedAt);
                    break;
                case OperationType::DISABLE_EXTENSION:
                    $result = $this->disableExtension($operation, $startedAt);
                    break;
                case OperationType::INSTALL_EXTENSION:
                    $result = $this->installExtension($operation, $startedAt);
                    break;
                case OperationType::REMOVE_EXTENSION:
                    $result = $this->removeExtension($operation, $startedAt);
                    break;
                case OperationType::ROLLBACK_EXTENSION:
                    $result = $this->rollbackExtension($operation, $startedAt);
                    break;
                case OperationType::RECONCILE:
                    $result = $this->reconcile($operation, $startedAt);
                    break;
                default:
                    $result = OperationResult::finish($operation->id, 'failed', $startedAt, 'Unsupported operation type.');
            }
            if ($result->status === 'success' && $this->runtimeVerifier !== null) {
                $profile = $this->profiles->load();
                if (!$profile->isLoaded() || $profile->profile === null) {
                    throw new \RuntimeException('Unable to verify runtime without a valid persistent profile.');
                }
                $expected = [];
                foreach ($profile->profile->extensions() as $name => $metadata) {
                    $expected[$name] = (isset($metadata['desired_state']) ? $metadata['desired_state'] : null) === 'enabled';
                }
                $verification = $this->runtimeVerifier->verify($expected);
                $reloadFailed = (isset($result->details['reload']['status']) ? $result->details['reload']['status'] : null) === 'failed';
                $result = OperationResult::finish($operation->id, ($verification['status'] === 'success' && !$reloadFailed) ? 'success' : 'failed', $startedAt, ($verification['status'] === 'success' && !$reloadFailed) ? $result->message : 'Changes were applied, but runtime verification failed; inspect verification and reload details.', array_replace($result->details, ['verification' => $verification]));
            }
            if ($this->profiles->load()->isLoaded() && ($operation->extension !== null || $operation->type === OperationType::RECONCILE)) {
                $installations = [];
                if (isset($result->details['install']['method'])) $installations[$operation->extension] = $result->details['install'];
                $report = array_replace($result->details, ['status' => $result->status, 'message' => $result->message]);
                (new \Wsdd\PhpAdmin\Profile\ProfileMetadataRecorder($this->profiles, new \Wsdd\PhpAdmin\Extensions\ExtensionCatalog()))->record($report, $installations, $operation->type === OperationType::RECONCILE, $operation->extension);
            }
            $this->auditOperation($operation, $result);
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($result, 'Wsdd\PhpAdmin\Operations\OperationResult');
        } catch (\Exception $exception) {
            $result = OperationResult::finish($operation->id, 'failed', $startedAt, $exception->getMessage(), ['exception' => get_class($exception)]);
            $this->auditOperation($operation, $result);
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($result, 'Wsdd\PhpAdmin\Operations\OperationResult');
        } catch (\Throwable $exception) {
            $result = OperationResult::finish($operation->id, 'failed', $startedAt, $exception->getMessage(), ['exception' => get_class($exception)]);
            $this->auditOperation($operation, $result);
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($result, 'Wsdd\PhpAdmin\Operations\OperationResult');
        }
    }
    /**
     * @param string $startedAt
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    private function enableExtension(Operation $operation, $startedAt)
    {
        $startedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($startedAt, 'string');
        $this->assertCanApplyManagedIni();
        $extension = $this->requiredExtension($operation);
        if ($this->managedIni->isExtensionLoadedByCurrentConfiguration($extension) && !$this->managedIni->hasManagedIni($extension)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, 'skipped', $startedAt, sprintf('Extension "%s" is already loaded by existing PHP configuration; no managed INI was created to avoid duplicate loading.', $extension)), 'Wsdd\PhpAdmin\Operations\OperationResult');
        }
        $backupPath = $this->backups->capture($extension, 'before_enable_extension');
        $path = $this->managedIni->enableExtension($extension);
        $this->profileRecorder->recordExtensionState($extension, 'enabled', 'enable_extension');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, 'success', $startedAt, sprintf('Managed INI created for extension "%s".', $extension), ['backup_path' => $backupPath, 'ini_path' => $path, 'reload' => $this->reloader->gracefulReload()]), 'Wsdd\PhpAdmin\Operations\OperationResult');
    }
    /**
     * @param string $startedAt
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    private function disableExtension(Operation $operation, $startedAt)
    {
        $startedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($startedAt, 'string');
        $this->assertCanApplyManagedIni();
        $extension = $this->requiredExtension($operation);
        $backupPath = $this->backups->capture($extension, 'before_disable_extension');
        $path = $this->managedIni->disableExtension($extension);
        if ($path === null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, 'skipped', $startedAt, sprintf('No managed INI file existed for extension "%s".', $extension), ['ini_path' => null, 'backup_path' => $backupPath]), 'Wsdd\PhpAdmin\Operations\OperationResult');
        }
        $this->profileRecorder->recordExtensionState($extension, 'disabled', 'disable_extension');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, 'success', $startedAt, sprintf('Managed INI removed for extension "%s".', $extension), ['backup_path' => $backupPath, 'ini_path' => $path, 'reload' => $this->reloader->gracefulReload()]), 'Wsdd\PhpAdmin\Operations\OperationResult');
    }
    /**
     * @param string $startedAt
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    private function installExtension(Operation $operation, $startedAt)
    {
        $startedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($startedAt, 'string');
        $extension = $this->requiredExtension($operation);
        $install = $this->extensionInstaller->install($extension);
        if ($install['status'] !== 'success') {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, (string) $install['status'], $startedAt, (string) $install['message'], is_array(isset($install['details']) ? $install['details'] : null) ? $install['details'] : []), 'Wsdd\PhpAdmin\Operations\OperationResult');
        }
        $this->assertCanApplyManagedIni();
        $backupPath = $this->backups->capture($extension, 'before_install_extension');
        $iniPath = $this->managedIni->adoptDockerGeneratedIni($extension);
        if ($iniPath === null && !$this->managedIni->hasAnyIniDirectiveForExtension($extension)) {
            $iniPath = $this->managedIni->enableExtension($extension);
        }
        $this->profileRecorder->recordExtensionState($extension, 'enabled', 'install_extension');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, 'success', $startedAt, sprintf('Extension "%s" installed and managed INI enabled.', $extension), ['backup_path' => $backupPath, 'install' => isset($install['details']) ? $install['details'] : [], 'ini_path' => $iniPath, 'ini_created' => $iniPath !== null, 'reload' => $this->reloader->gracefulReload()]), 'Wsdd\PhpAdmin\Operations\OperationResult');
    }
    /**
     * @param string $startedAt
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    private function removeExtension(Operation $operation, $startedAt)
    {
        $startedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($startedAt, 'string');
        $this->assertCanApplyManagedIni();
        $extension = $this->requiredExtension($operation);
        $backupPath = $this->backups->capture($extension, 'before_remove_extension');
        $iniPath = $this->managedIni->disableExtension($extension);
        $remove = $this->extensionInstaller->remove($extension);
        if ($iniPath !== null || (isset($remove['status']) ? $remove['status'] : null) === 'success') {
            $this->profileRecorder->recordExtensionState($extension, 'removed', 'remove_extension');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, ($iniPath === null && (isset($remove['status']) ? $remove['status'] : null) === 'skipped') ? 'skipped' : 'success', $startedAt, ($iniPath === null) ? (string) (isset($remove['message']) ? $remove['message'] : 'No managed INI existed and binary removal is unsupported.') : sprintf('Managed INI removed for extension "%s"; binary removal was not attempted.', $extension), ['backup_path' => $backupPath, 'ini_path' => $iniPath, 'remove' => $remove, 'reload' => ($iniPath === null) ? ['attempted' => false, 'command' => null, 'status' => 'skipped', 'exit_code' => null, 'message' => 'No managed INI changed.'] : $this->reloader->gracefulReload()]), 'Wsdd\PhpAdmin\Operations\OperationResult');
    }
    /**
     * @param string $startedAt
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    private function rollbackExtension(Operation $operation, $startedAt)
    {
        $startedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($startedAt, 'string');
        $this->assertCanApplyManagedIni();
        $extension = $this->requiredExtension($operation);
        $restore = $this->backups->restoreLatest($extension);
        $status = (string) (isset($restore['status']) ? $restore['status'] : 'failed');
        if ($status === 'success') {
            $this->profileRecorder->recordExtensionState($extension, ((isset($restore['restored_state']) ? $restore['restored_state'] : null) === 'absent') ? 'disabled' : 'enabled', 'rollback_extension');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, in_array($status, ['success', 'skipped'], true) ? $status : 'failed', $startedAt, (string) (isset($restore['message']) ? $restore['message'] : 'Rollback finished.'), ['restore' => $restore, 'reload' => ($status === 'success') ? $this->reloader->gracefulReload() : ['attempted' => false, 'command' => null, 'status' => 'skipped', 'exit_code' => null, 'message' => 'No rollback was applied.']]), 'Wsdd\PhpAdmin\Operations\OperationResult');
    }
    /**
     * @param string $startedAt
     * @return Wsdd\PhpAdmin\Operations\OperationResult
     */
    private function reconcile(Operation $operation, $startedAt)
    {
        $startedAt = \Wsdd\PhpAdmin\Support\TypeGuard::check($startedAt, 'string');
        $report = $this->managedReconciler->apply();
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(OperationResult::finish($operation->id, (string) $report['status'], $startedAt, (string) $report['message'], ['applied' => isset($report['applied']) ? $report['applied'] : [], 'skipped' => isset($report['skipped']) ? $report['skipped'] : [], 'reload' => isset($report['reload']) ? $report['reload'] : [], 'errors' => isset($report['errors']) ? $report['errors'] : [], 'verification' => isset($report['verification']) ? $report['verification'] : []]), 'Wsdd\PhpAdmin\Operations\OperationResult');
    }
    /**
     * @return void
     */
    private function assertCanApplyManagedIni()
    {
        if (ProcessIdentity::isRoot()) {
            return;
        }
        if ($this->managedIni->canWriteDirectory()) {
            return;
        }
        throw new \RuntimeException('Agent requires root privileges or write access to the managed INI directory.');
    }
    /**
     * @return string
     */
    private function requiredExtension(Operation $operation)
    {
        if ($operation->extension === null) {
            throw new \RuntimeException('Operation requires an extension name.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($operation->extension, 'string');
    }
    /**
     * @return void
     */
    private function auditOperation(Operation $operation, OperationResult $result)
    {
        $this->audit->record($operation->requestedBy, $operation->type, isset($operation->extension) ? $operation->extension : 'runtime', $result->status, ['operation_id' => $operation->id, 'message' => $result->message, 'details' => $result->details]);
    }
}
