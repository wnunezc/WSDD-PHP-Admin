<?php

namespace Wsdd\PhpAdmin\Permissions;

final class PathPermission
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var string
     */
    private $label;
    /**
     * @var string
     */
    private $path;
    /**
     * @var bool
     */
    private $exists;
    /**
     * @var bool
     */
    private $isDirectory;
    /**
     * @var bool
     */
    private $isFile;
    /**
     * @var bool
     */
    private $readable;
    /**
     * @var bool
     */
    private $writable;
    /**
     * @var bool
     */
    private $parentWritable;
    /**
     * @param string $label
     * @param string $path
     * @param bool $exists
     * @param bool $isDirectory
     * @param bool $isFile
     * @param bool $readable
     * @param bool $writable
     * @param bool $parentWritable
     */
    public function __construct($label, $path, $exists, $isDirectory, $isFile, $readable, $writable, $parentWritable)
    {
        $label = \Wsdd\PhpAdmin\Support\TypeGuard::check($label, 'string');
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        $exists = \Wsdd\PhpAdmin\Support\TypeGuard::check($exists, 'bool');
        $isDirectory = \Wsdd\PhpAdmin\Support\TypeGuard::check($isDirectory, 'bool');
        $isFile = \Wsdd\PhpAdmin\Support\TypeGuard::check($isFile, 'bool');
        $readable = \Wsdd\PhpAdmin\Support\TypeGuard::check($readable, 'bool');
        $writable = \Wsdd\PhpAdmin\Support\TypeGuard::check($writable, 'bool');
        $parentWritable = \Wsdd\PhpAdmin\Support\TypeGuard::check($parentWritable, 'bool');
        $this->label = $label;
        $this->path = $path;
        $this->exists = $exists;
        $this->isDirectory = $isDirectory;
        $this->isFile = $isFile;
        $this->readable = $readable;
        $this->writable = $writable;
        $this->parentWritable = $parentWritable;
    }
    /**
     * @return bool
     */
    public function canWriteNowOrCreate()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->exists && $this->writable || !$this->exists && $this->parentWritable, 'bool');
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['label' => $this->label, 'path' => $this->path, 'exists' => $this->exists, 'is_directory' => $this->isDirectory, 'is_file' => $this->isFile, 'readable' => $this->readable, 'writable' => $this->writable, 'parent_writable' => $this->parentWritable, 'can_write_now_or_create' => $this->canWriteNowOrCreate()], 'array');
    }
}
