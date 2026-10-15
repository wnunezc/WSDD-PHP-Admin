<?php

namespace Wsdd\PhpAdmin\Reconciliation;

use Wsdd\PhpAdmin\Extensions\ExtensionName;
use Wsdd\PhpAdmin\Extensions\ExtensionInstaller;
use Wsdd\PhpAdmin\Ini\ManagedIniRepository;
use Wsdd\PhpAdmin\Profile\ProfileRepository;
use Wsdd\PhpAdmin\Service\ServiceReloader;
use Wsdd\PhpAdmin\Support\ProcessIdentity;
use Wsdd\PhpAdmin\Runtime\RuntimeVerifier;
final class ManagedReconciler
{
    /**
     * @var \Wsdd\PhpAdmin\Profile\ProfileRepository
     */
    private $profiles;
    /**
     * @var \Wsdd\PhpAdmin\Ini\ManagedIniRepository
     */
    private $managedIni;
    /**
     * @var \Wsdd\PhpAdmin\Service\ServiceReloader
     */
    private $reloader;
    /**
     * @var \Wsdd\PhpAdmin\Extensions\ExtensionInstaller
     */
    private $extensionInstaller;
    /**
     * @var \Wsdd\PhpAdmin\Runtime\RuntimeVerifier|null
     */
    private $runtimeVerifier;
    private $verificationTimeoutSeconds;
    /**
     * @param Wsdd\PhpAdmin\Runtime\RuntimeVerifier|null $runtimeVerifier
     */
    public function __construct(ProfileRepository $profiles, ManagedIniRepository $managedIni, ServiceReloader $reloader, ExtensionInstaller $extensionInstaller, $runtimeVerifier = null, $verificationTimeoutSeconds = 30)
    {
        $runtimeVerifier = \Wsdd\PhpAdmin\Support\TypeGuard::check($runtimeVerifier, '?Wsdd\PhpAdmin\Runtime\RuntimeVerifier');
        $this->profiles = $profiles;
        $this->managedIni = $managedIni;
        $this->reloader = $reloader;
        $this->extensionInstaller = $extensionInstaller;
        $this->runtimeVerifier = $runtimeVerifier;
        if (!is_numeric($verificationTimeoutSeconds) || $verificationTimeoutSeconds < 0 || $verificationTimeoutSeconds > 120) throw new \InvalidArgumentException('Invalid runtime verification timeout.');
        $this->verificationTimeoutSeconds = (float) $verificationTimeoutSeconds;
    }
    /**
     * @return array<string, mixed>
     */
    public function apply()
    {
        $profileResult = $this->profiles->load();
        if (!$profileResult->isLoaded() || $profileResult->profile === null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => 'failed', 'message' => isset($profileResult->error) ? $profileResult->error : 'Persistent profile does not exist.', 'applied' => [], 'skipped' => [], 'reload' => $this->noReload('Profile could not be loaded.')], 'array');
        }
        $applied = [];
        $errors = [];
        $skipped = [];
        $changed = false;
        foreach ($profileResult->profile->extensions() as $extension => $metadata) {
            $desiredState = isset($metadata['desired_state']) ? $metadata['desired_state'] : null;
            if (!ExtensionName::isValid($extension)) {
                $errors[] = 'Profile contains an invalid extension name.';
                $skipped[] = ['extension' => $extension, 'desired_state' => $desiredState, 'reason' => 'Profile contains an invalid extension name.'];
                continue;
            }
            if ($desiredState === 'enabled') {
                $result = $this->enable($extension);
                $changed = $changed || $result['changed'];
                $this->appendAction($result, $applied, $skipped);
                continue;
            }
            if ($desiredState === 'disabled') {
                $result = $this->disable($extension);
                $changed = $changed || $result['changed'];
                $this->appendAction($result, $applied, $skipped);
                continue;
            }
            if ($desiredState === 'removed') {
                $result = $this->remove($extension);
                $changed = $changed || $result['changed'];
                $this->appendAction($result, $applied, $skipped);
                continue;
            }
            $skipped[] = ['extension' => $extension, 'desired_state' => $desiredState, 'reason' => 'No safe managed action is supported for this state yet.'];
            $errors[] = 'Invalid desired state for extension ' . $extension . '.';
        }
        $reload = $changed ? $this->reloader->gracefulReload() : $this->noReload('No managed INI changed.');
        $verification = ['status' => 'skipped'];
        $expected = [];
        foreach ($profileResult->profile->extensions() as $name => $metadata) {
            if (ExtensionName::isValid($name) && in_array(isset($metadata['desired_state']) ? $metadata['desired_state'] : null, ['enabled', 'disabled', 'removed'], true)) {
                $expected[$name] = $metadata['desired_state'] === 'enabled';
            }
        }
        foreach ($skipped as $action) {
            if (isset($action['install_status']) && $action['install_status'] === 'failed') {
                $errors[] = $action['extension'] . ': ' . $action['reason'];
            }
        }
        if ($this->runtimeVerifier !== null) {
            $verification = $this->runtimeVerifier->verify($expected, $this->verificationTimeoutSeconds);
        } else {
            // Isolated callers without an Apache verifier must still verify fresh CLI state.
            $loaded = array_fill_keys(array_map([ExtensionName::class, 'canonical'], \Wsdd\PhpAdmin\Runtime\RuntimeSnapshot::freshCli()['extensions']), true);
            $mismatches = [];
            foreach ($expected as $name => $enabled) {
                if (isset($loaded[$name]) !== $enabled) $mismatches[] = 'cli: extension ' . $name . ' does not match expected state.';
            }
            $verification = ['status' => $mismatches === [] ? 'success' : 'failed', 'errors' => $mismatches];
        }
        $failed = $errors !== [] || (isset($reload['status']) ? $reload['status'] : null) === 'failed' || $verification['status'] === 'failed';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => $failed ? 'failed' : 'success', 'message' => $failed ? 'Desired runtime state was not satisfied; inspect installation, verification and reload details.' : 'Reconciliation verified the desired runtime state.', 'errors' => $errors, 'verification' => $verification, 'applied' => $applied, 'skipped' => $skipped, 'reload' => $reload], 'array');
    }
    /**
     * @return array{changed:bool, applied:array<string, mixed>|null, skipped:array<string, mixed>|null}
     * @param string $extension
     */
    private function enable($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertCanApplyManagedIni();
        if ($this->managedIni->hasManagedIni($extension)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['changed' => false, 'applied' => null, 'skipped' => ['extension' => $extension, 'desired_state' => 'enabled', 'reason' => 'Managed INI already exists.']], 'array');
        }
        if ($this->managedIni->isExtensionLoadedByCurrentConfiguration($extension)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['changed' => false, 'applied' => null, 'skipped' => ['extension' => $extension, 'desired_state' => 'enabled', 'reason' => 'Extension is already loaded by existing PHP configuration.']], 'array');
        }
        $installDetails = null;
        if (!$this->managedIni->isExtensionAvailable($extension)) {
            $install = $this->extensionInstaller->install($extension);
            if ((isset($install['status']) ? $install['status'] : null) !== 'success') {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(['changed' => false, 'applied' => null, 'skipped' => ['extension' => $extension, 'desired_state' => 'enabled', 'reason' => isset($install['message']) ? $install['message'] : 'Extension is not available and could not be installed safely.', 'install_status' => isset($install['status']) ? $install['status'] : 'skipped', 'install_details' => isset($install['details']) ? $install['details'] : []]], 'array');
            }
            $installDetails = isset($install['details']) ? $install['details'] : [];
        }
        $path = null;
        $path = $this->managedIni->adoptDockerGeneratedIni($extension);
        if ($path === null && !$this->managedIni->hasAnyIniDirectiveForExtension($extension)) {
            $path = $this->managedIni->enableExtension($extension);
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['changed' => true, 'applied' => ['extension' => $extension, 'action' => 'enable_extension', 'ini_path' => $path, 'ini_created' => $path !== null, 'install' => $installDetails], 'skipped' => null], 'array');
    }
    /**
     * @return array{changed:bool, applied:array<string, mixed>|null, skipped:array<string, mixed>|null}
     * @param string $extension
     */
    private function disable($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertCanApplyManagedIni();
        $path = $this->managedIni->disableExtension($extension);
        if ($path === null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['changed' => false, 'applied' => null, 'skipped' => ['extension' => $extension, 'desired_state' => 'disabled', 'reason' => 'No managed INI file existed.']], 'array');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['changed' => true, 'applied' => ['extension' => $extension, 'action' => 'disable_extension', 'ini_path' => $path], 'skipped' => null], 'array');
    }
    /**
     * @return array{changed:bool, applied:array<string, mixed>|null, skipped:array<string, mixed>|null}
     * @param string $extension
     */
    private function remove($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertCanApplyManagedIni();
        $path = $this->managedIni->disableExtension($extension);
        $remove = $this->extensionInstaller->remove($extension);
        if ($path === null && (isset($remove['status']) ? $remove['status'] : null) !== 'success') {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['changed' => false, 'applied' => null, 'skipped' => ['extension' => $extension, 'desired_state' => 'removed', 'reason' => isset($remove['message']) ? $remove['message'] : 'Safe binary removal is not supported.', 'remove_status' => isset($remove['status']) ? $remove['status'] : 'skipped']], 'array');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['changed' => $path !== null || (isset($remove['status']) ? $remove['status'] : null) === 'success', 'applied' => ['extension' => $extension, 'action' => 'remove_extension', 'ini_path' => $path, 'remove' => $remove], 'skipped' => null], 'array');
    }
    /**
     * @param array{applied:array<string, mixed>|null, skipped:array<string, mixed>|null} $result
     * @param list<array<string, mixed>> $applied
     * @param list<array<string, mixed>> $skipped
     * @return void
     */
    private function appendAction(array $result, array &$applied, array &$skipped)
    {
        if (is_array($result['applied'])) {
            $applied[] = $result['applied'];
        }
        if (is_array($result['skipped'])) {
            $skipped[] = $result['skipped'];
        }
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
        throw new \RuntimeException('Managed reconciliation requires root privileges or write access to the managed INI directory.');
    }
    /**
     * @return array<string, mixed>
     * @param string $message
     */
    private function noReload($message)
    {
        $message = \Wsdd\PhpAdmin\Support\TypeGuard::check($message, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['attempted' => false, 'command' => null, 'status' => 'skipped', 'exit_code' => null, 'message' => $message], 'array');
    }
}
