<?php

namespace Wsdd\PhpAdmin\Support;

/** Keeps result objects externally immutable on runtimes without readonly. */
trait ReadOnlyProperties
{
    /**
     * @param string $name
     */
    public function __get($name)
    {
        $name = \Wsdd\PhpAdmin\Support\TypeGuard::check($name, 'string');
        if (!property_exists($this, $name)) {
            throw new \LogicException('Unknown result property: ' . $name);
        }
        return $this->{$name};
    }
    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        $name = \Wsdd\PhpAdmin\Support\TypeGuard::check($name, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(property_exists($this, $name) && isset($this->{$name}), 'bool');
    }
    /**
     * @param string $name
     * @return void
     */
    public function __set($name, $value)
    {
        $name = \Wsdd\PhpAdmin\Support\TypeGuard::check($name, 'string');
        throw new \LogicException('Result properties are read-only: ' . $name);
    }
    /**
     * @param string $name
     * @return void
     */
    public function __unset($name)
    {
        $name = \Wsdd\PhpAdmin\Support\TypeGuard::check($name, 'string');
        throw new \LogicException('Result properties are read-only: ' . $name);
    }
}
