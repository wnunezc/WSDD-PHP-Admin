<?php

namespace Wsdd\PhpAdmin\Profile;

final class ProfileLoadResult
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var string
     */
    private $path;
    /**
     * @var bool
     */
    private $exists;
    /**
     * @var \Wsdd\PhpAdmin\Profile\ProfileDocument|null
     */
    private $profile;
    /**
     * @var string|null
     */
    private $error;
    /**
     * @param string $path
     * @param bool $exists
     * @param Wsdd\PhpAdmin\Profile\ProfileDocument|null $profile
     * @param string|null $error
     */
    public function __construct($path, $exists, $profile, $error)
    {
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        $exists = \Wsdd\PhpAdmin\Support\TypeGuard::check($exists, 'bool');
        $profile = \Wsdd\PhpAdmin\Support\TypeGuard::check($profile, '?Wsdd\PhpAdmin\Profile\ProfileDocument');
        $error = \Wsdd\PhpAdmin\Support\TypeGuard::check($error, '?string');
        $this->path = $path;
        $this->exists = $exists;
        $this->profile = $profile;
        $this->error = $error;
    }
    /**
     * @return bool
     */
    public function isLoaded()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->profile !== null && $this->error === null, 'bool');
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['path' => $this->path, 'exists' => $this->exists, 'loaded' => $this->isLoaded(), 'error' => $this->error, 'profile' => ($this->profile !== null) ? $this->profile->toArray() : null], 'array');
    }
}
