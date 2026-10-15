<?php

namespace Wsdd\PhpAdmin\Profile;

use Wsdd\PhpAdmin\Runtime\RuntimeDetector;
final class ProfileStateRecorder
{
    /**
     * @var \Wsdd\PhpAdmin\Profile\ProfileRepository
     */
    private $profiles;
    /**
     * @var \Wsdd\PhpAdmin\Runtime\RuntimeDetector
     */
    private $runtimeDetector;
    public function __construct(ProfileRepository $profiles, RuntimeDetector $runtimeDetector)
    {
        $this->profiles = $profiles;
        $this->runtimeDetector = $runtimeDetector;
    }
    /**
     * @param string $extension
     * @param string $desiredState
     * @param string $source
     * @return Wsdd\PhpAdmin\Profile\ProfileDocument
     */
    public function recordExtensionState($extension, $desiredState, $source)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $desiredState = \Wsdd\PhpAdmin\Support\TypeGuard::check($desiredState, 'string');
        $source = \Wsdd\PhpAdmin\Support\TypeGuard::check($source, 'string');
        $profile = $this->profiles->loadOrInitialize($this->runtimeDetector->detect());
        $updated = $profile->withExtensionState($extension, $desiredState, $source);
        $this->profiles->save($updated);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($updated, 'Wsdd\PhpAdmin\Profile\ProfileDocument');
    }
}
