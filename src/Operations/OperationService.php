<?php

namespace Wsdd\PhpAdmin\Operations;

final class OperationService
{
    /**
     * @var \Wsdd\PhpAdmin\Operations\OperationRepository
     */
    private $repository;
    public function __construct(OperationRepository $repository)
    {
        $this->repository = $repository;
    }
    /**
     * @param string $type
     * @param string|null $extension
     * @param string $requestedBy
     * @return Wsdd\PhpAdmin\Operations\Operation
     */
    public function create($type, $extension, $requestedBy = 'web')
    {
        $type = \Wsdd\PhpAdmin\Support\TypeGuard::check($type, 'string');
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, '?string');
        $requestedBy = \Wsdd\PhpAdmin\Support\TypeGuard::check($requestedBy, 'string');
        $operation = Operation::create($type, $extension, $requestedBy);
        $this->repository->create($operation);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($operation, 'Wsdd\PhpAdmin\Operations\Operation');
    }
}
