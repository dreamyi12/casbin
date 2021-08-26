<?php

declare(strict_types=1);

namespace Dreamyi12\Casbin\Adapters\Mysql;

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Dreamyi12\Casbin\Adapters\Mysql\Rule;
use Hyperf\DbConnection\Db;
use Casbin\Persist\Adapter;
use Casbin\Persist\BatchAdapter;
use Casbin\Persist\UpdatableAdapter;
use Casbin\Persist\FilteredAdapter;
use Casbin\Model\Model;
use Casbin\Persist\AdapterHelper;
use Casbin\Exceptions\InvalidFilterTypeException;

/**
 * DatabaseAdapter.
 */
class DatabaseAdapter implements Adapter, BatchAdapter, UpdatableAdapter, FilteredAdapter
{

    use AdapterHelper;

    /**
     * @var bool
     */
    private $filtered = false;

    /**
     * Rules eloquent model.
     *
     * @var Rule
     */
    protected $eloquent;

    /**
     * Db
     * @var Db
     */
    protected $db;

    /**
     * @var
     */
    protected $table;

    /**
     * @var
     */
    protected $example;

    /**
     * the DatabaseAdapter constructor.
     *
     * @param Rule $eloquent
     */
    public function __construct(Db $db, array $table, string $example)
    {
        $this->table = $table;
        $this->example = $example;
        $this->eloquent = make(Rule::class, ['attributes' => [], 'table' => $this->table['table_name']]);
        $this->db = $db;
        $this->initTable();
    }

    public function initTable()
    {
        if (!Schema::hasTable($this->table['table_name'])) {
            Schema::create($this->table['table_name'], function (Blueprint $table) {
                $table->increments('id');
                foreach ($this->table['columns'] as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
    }

    /**
     * savePolicyLine function.
     * @param string $type
     * @param array $rules
     */
    public function savePolicyLine(string $type, array $rules)
    {
        $col['type'] = $type;
        foreach ($rules as $key => $rule) {
            $col[$this->table['columns'][$key + 1]] = $rule;
        }
        return $col;
    }

    /**
     * loads all policy rules from the storage.
     * @param Model $model
     */
    public function loadPolicy(Model $model): void
    {
        $rows = $this->eloquent->select($this->table['columns'])->get()->toArray();
        foreach ($rows as $row) {
            $line = implode(', ', array_filter($row, function ($val) {
                return '' != $val && !is_null($val);
            }));
            $this->loadPolicyLine(trim($line), $model);
        }
    }

    /**
     * saves all policy rules to the storage.
     *
     * @param Model $model
     */
    public function savePolicy(Model $model): void
    {
        foreach ($model['p'] as $type => $ast) {
            foreach ($ast->policy as $rule) {
                $row = $this->savePolicyLine($type, $rule);
                $this->eloquent->create($row);
            }
        }

        foreach ($model['g'] as $type => $ast) {
            foreach ($ast->policy as $rule) {
                $row = $this->savePolicyLine($type, $rule);
                $this->eloquent->create($row);
            }
        }
    }

    /**
     * adds a policy rule to the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $type
     * @param array $rule
     */
    public function addPolicy(string $sec, string $type, array $rule): void
    {
        $row = $this->savePolicyLine($type, $rule);
        $this->eloquent->create($row);
    }

    /**
     * Adds a policy rules to the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $type
     * @param string[][] $rules
     */
    public function addPolicies(string $sec, string $type, array $rules): void
    {
        $rows = [];
        foreach ($rules as $rule) {
            $rows[] = $this->savePolicyLine($type, $rule);
        }
        $this->eloquent->insert($rows);
    }

    /**
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $type
     * @param array $rule
     */
    public function removePolicy(string $sec, string $type, array $rule): void
    {
        $query = $this->eloquent->where('type', $type);
        foreach ($rule as $key => $value) {
            $query->where($this->table['columns'][$key + 1], $value);
        }
        $query->delete();
    }

    /**
     * Removes policy rules from the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $type
     * @param string[][] $rules
     */
    public function removePolicies(string $sec, string $type, array $rules): void
    {
        $this->db->beginTransaction();
        try {
            foreach ($rules as $rule) {
                $this->removePolicy($sec, $type, $rule);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * RemoveFilteredPolicy removes policy rules that match the filter from the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $type
     * @param int $fieldIndex
     * @param string ...$fieldValues
     */
    public function removeFilteredPolicy(string $sec, string $type, int $fieldIndex, string ...$fieldValues): void
    {
        $query = $this->eloquent->where('type', $type);
        foreach (range(0, 5) as $value) {
            if ($fieldIndex <= $value && $value < $fieldIndex + count($fieldValues)) {
                if ('' != $fieldValues[$value - $fieldIndex]) {
                    $query->where($this->table['columns'][$value + 1], $fieldValues[$value - $fieldIndex]);
                }
            }
        }
        $query->delete();
    }

    /**
     * Updates a policy rule from storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param string[] $oldRule
     * @param string[] $newPolicy
     */
    public function updatePolicy(string $sec, string $ptype, array $oldRule, array $newPolicy): void
    {
        $query = $this->eloquent->where('type', $ptype);
        foreach ($oldRule as $k => $v) {
            $query->where($this->table['columns'][$k + 1], $v);
        }
        $query->first();
        $update = [];
        foreach ($newPolicy as $k => $v) {
            $update[$this->table['columns'][$k + 1]] = $v;
        }
        $query->update($update);
    }

    /**
     * UpdatePolicies updates some policy rules to storage, like db, redis.
     *
     * @param string $sec
     * @param string $ptype
     * @param string[][] $oldRules
     * @param string[][] $newRules
     * @return void
     */
    public function updatePolicies(string $sec, string $ptype, array $oldRules, array $newRules): void
    {
        $this->db->beginTransaction();
        try {
            foreach ($oldRules as $i => $oldRule) {
                $this->updatePolicy($sec, $ptype, $oldRule, $newRules[$i]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Loads only policy rules that match the filter.
     *
     * @param Model $model
     * @param mixed $filter
     */
    public function loadFilteredPolicy(Model $model, $filter): void
    {
        $query = $this->eloquent->newQuery();

        if (is_string($filter)) {
            $query->whereRaw($filter);
        } else if ($filter instanceof Filter) {
            foreach ($filter->p as $k => $v) {
                $query->where($v, $filter->g[$k]);
            }
        } else if ($filter instanceof \Closure) {
            $query->where($filter);
        } else {
            throw new InvalidFilterTypeException('invalid filter type');
        }
        $rows = $query->get()->makeHidden(['id'])->toArray();
        foreach ($rows as $row) {
            $row = array_filter($row, function ($value) {
                return !is_null($value) && $value !== '';
            });
            $line = implode(', ', array_filter($row, function ($val) {
                return '' != $val && !is_null($val);
            }));
            $this->loadPolicyLine(trim($line), $model);
        }
        $this->setFiltered(true);
    }

    /**
     * Returns true if the loaded policy has been filtered.
     *
     * @return bool
     */
    public function isFiltered(): bool
    {
        return $this->filtered;
    }

    /**
     * Sets filtered parameter.
     *
     * @param bool $filtered
     */
    public function setFiltered(bool $filtered): void
    {
        $this->filtered = $filtered;
    }

}
