<?php

namespace Dreamyi12\Casbin\Model;

use Dreamyi12\Casbin\Model\Interfaces\DataPermissionInterface;

abstract class DataPermissionBasis implements DataPermissionInterface
{
    /**
     *
     * @var
     */
    protected $type;

    /**
     * @var
     */
    protected $relation;


}