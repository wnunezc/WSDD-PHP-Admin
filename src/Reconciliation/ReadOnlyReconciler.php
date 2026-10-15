<?php

namespace Wsdd\PhpAdmin\Reconciliation;

use Wsdd\PhpAdmin\Extensions\ExtensionName;
use Wsdd\PhpAdmin\Profile\ProfileLoadResult;
use Wsdd\PhpAdmin\Runtime\RuntimeDetection;
final class ReadOnlyReconciler
{
    /**
     * @return Wsdd\PhpAdmin\Reconciliation\ReconciliationReport
     */
    public function reconcile(ProfileLoadResult $profileResult, RuntimeDetection $runtime)
    {
        $issues = [];
        if (!$profileResult->exists) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ReconciliationReport(false, 'read-only', [new ReconciliationIssue('info', 'profile', 'No persistent profile exists yet. Initialize one before declaring desired extension state.')]), 'Wsdd\PhpAdmin\Reconciliation\ReconciliationReport');
        }
        if (!$profileResult->isLoaded()) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ReconciliationReport(false, 'read-only', [new ReconciliationIssue('error', 'profile', isset($profileResult->error) ? $profileResult->error : 'Persistent profile could not be loaded.')]), 'Wsdd\PhpAdmin\Reconciliation\ReconciliationReport');
        }
        $profile = $profileResult->profile;
        if ($profile === null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ReconciliationReport(false, 'read-only', []), 'Wsdd\PhpAdmin\Reconciliation\ReconciliationReport');
        }
        $profilePhp = $profile->php();
        foreach (isset($runtime->observations['errors']) ? $runtime->observations['errors'] : [] as $error) {
            $issues[] = new ReconciliationIssue('error', 'runtime.observation', $error);
        }
        $this->compareRuntimeMetadata($profilePhp, $runtime, $issues);
        $loaded = array_fill_keys(array_map([ExtensionName::class, 'canonical'], $runtime->loadedExtensions), true);
        foreach ($profile->extensions() as $extension => $metadata) {
            $desiredState = isset($metadata['desired_state']) ? $metadata['desired_state'] : null;
            if (!ExtensionName::isValid($extension)) {
                $issues[] = new ReconciliationIssue('error', $extension, 'Profile contains an invalid extension name.');
                continue;
            }
            if (!in_array($desiredState, ['enabled', 'disabled', 'removed'], true)) {
                $issues[] = new ReconciliationIssue('error', $extension, 'Profile extension entry has an invalid desired_state.');
                continue;
            }
            $normalizedExtension = strtolower($extension);
            $isLoaded = isset($loaded[$normalizedExtension]);
            if ($desiredState === 'enabled' && !$isLoaded) {
                $issues[] = new ReconciliationIssue('action-required', $extension, 'Profile wants this extension enabled, but it is not currently loaded.');
                continue;
            }
            if ($desiredState === 'disabled' && $isLoaded) {
                $issues[] = new ReconciliationIssue('action-required', $extension, 'Profile wants this extension disabled, but it is currently loaded.');
                continue;
            }
            if ($desiredState === 'removed' && $isLoaded) {
                $issues[] = new ReconciliationIssue('action-required', $extension, 'Profile marks this extension removed, but it is currently loaded.');
            }
        }
        if ($issues === []) {
            $issues[] = new ReconciliationIssue('ok', 'profile', 'Persistent profile matches the currently observed runtime for tracked extensions.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new ReconciliationReport(true, 'read-only', $issues), 'Wsdd\PhpAdmin\Reconciliation\ReconciliationReport');
    }
    /**
     * @param array<string, mixed> $profilePhp
     * @param list<ReconciliationIssue> $issues
     * @return void
     */
    private function compareRuntimeMetadata(array $profilePhp, RuntimeDetection $runtime, array &$issues)
    {
        $expectedVersion = isset($profilePhp['version']) ? $profilePhp['version'] : null;
        if (is_string($expectedVersion) && $expectedVersion !== $runtime->phpVersion) {
            $issues[] = new ReconciliationIssue('warning', 'php.version', sprintf('Profile PHP version is "%s", observed runtime is "%s".', $expectedVersion, $runtime->phpVersion));
        }
        $expectedRuntime = isset($profilePhp['runtime']) ? $profilePhp['runtime'] : null;
        if (is_string($expectedRuntime) && $expectedRuntime !== $runtime->runtime) {
            $expectedScanDir = isset($profilePhp['ini_scan_dir']) ? $profilePhp['ini_scan_dir'] : null;
            if (!is_string($expectedScanDir) || $runtime->iniScanDir === null || $expectedScanDir !== $runtime->iniScanDir) {
                $issues[] = new ReconciliationIssue('warning', 'php.runtime', sprintf('Profile runtime is "%s", observed runtime is "%s".', $expectedRuntime, $runtime->runtime));
            }
        }
        $expectedScanDir = isset($profilePhp['ini_scan_dir']) ? $profilePhp['ini_scan_dir'] : null;
        if (is_string($expectedScanDir) && $runtime->iniScanDir !== null && $expectedScanDir !== $runtime->iniScanDir) {
            $issues[] = new ReconciliationIssue('warning', 'php.ini_scan_dir', sprintf('Profile scan dir is "%s", observed runtime uses "%s".', $expectedScanDir, $runtime->iniScanDir));
        }
    }
}
