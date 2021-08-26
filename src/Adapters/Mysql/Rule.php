<?php

namespace Dreamyi12\Casbin\Adapters\Mysql;

use Hyperf\DbConnection\Model\Model;

/**
 * Rule Model.
 */
class Rule extends Model
{

    /**
     * Fillable.
     *
     * @var array
     */
    protected $fillable = ['type', 'role_id', 'href', 'method', 'example', 'domain', 'reserve'];

    /**
     * timestamps
     * 
     * @var bool
     */
    public $timestamps = false;

    /**
     * Create a new Eloquent model instance.
     *
     * @param array  $attributes
     * @param string $guard
     */
    public function __construct(array $attributes = [], string $table = 'rule')
    {
        $this->setTable($table);
        parent::__construct($attributes);
    }

}
