<?php

namespace Dreamyi12\Casbin\Scopes;

use App\Enterprise\Common\Model\Info;
use Dreamyi12\Casbin\Exceptions\UnauthorizedException;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Scope;
use Phper666\JWTAuth\JWT;

class PermissionScope implements Scope
{

    /**
     * 处理数据权限
     * @param Builder $builder
     * @param Model $model
     */
    public function apply(Builder $builder, Model $model)
    {
        $dataPermission = $model->getSelectPermission();

        $user = make(JWT::class)->getParserData();
        if (!empty($dataPermission)) {
            //如果依据本身模型表的字段
            if ($dataPermission['type'] == 'field') {
                $builder->where($dataPermission['field'], $user['id']);
            } elseif ($dataPermission['type'] == "relation") {
                $builder->whereHas($dataPermission['relation'], function ($query) use ($dataPermission, $user) {
                    $query->where($dataPermission['field'], $user['id']);
                });
            }
            //如果其身份为组织部门负责人，则能获取该部门下的所有数据

        }
        if($model instanceof Info){
            $builder->where('id', $user['org_id']);
        }else{
            $builder->where('org_id', $user['org_id']);
        }

    }
}