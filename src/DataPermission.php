<?php


namespace Dreamyi12\Casbin;


use App\Common\Assist\Login;
use App\Enterprise\Common\Scopes\PermissionScope;
use Dreamyi12\Casbin\Exceptions\UnauthorizedException;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Deleting;
use Hyperf\Database\Model\Events\Event;
use Hyperf\Database\Model\Events\Saving;
use Hyperf\Database\Model\Events\Updating;
use Hyperf\Database\Schema\Schema;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Arr;

trait DataPermission
{

    /**
     * Define primary permission configuration
     */
    protected function boot(): void
    {
        static::addGlobalScope(new PermissionScope());
    }

    /**
     * @param Updating $updating
     */
    public function updating(Updating $updating)
    {
        $user = $this->login->getUser();
        if(!empty($user)){
            if (Schema::hasColumn($this->getTable(), 'update_user_id')) {
                $this->setAttribute('update_user_id', $user['id']);
            }
            if (Schema::hasColumn($this->getTable(), 'update_user_name')) {
                $this->setAttribute('update_user_name', $user['realname']);
            }
        }
        $this->authentication($this->savePermission, $updating);
    }

    /**
     * @param Creating $creating
     */
    public function creating(Creating $creating)
    {
        $user = $this->login->getUser();
        if(!empty($user)) {
            if (Schema::hasColumn($this->getTable(), 'org_id')) {
                $this->setAttribute('org_id', $user['org_id']);
            }
            if (Schema::hasColumn($this->getTable(), 'org_name')) {
                $this->setAttribute('org_name', $user['org_name']);
            }
            if (Schema::hasColumn($this->getTable(), 'create_user_id')) {
                $this->setAttribute('create_user_id', $user['id']);
            }
            if (Schema::hasColumn($this->getTable(), 'create_user_name')) {
                $this->setAttribute('create_user_name', $user['realname']);
            }
            if (Schema::hasColumn($this->getTable(), 'update_user_id')) {
                $this->setAttribute('update_user_id', $user['id']);
            }
            if (Schema::hasColumn($this->getTable(), 'update_user_name')) {
                $this->setAttribute('update_user_name', $user['realname']);
            }
        }
    }

    /**
     * @param Saving $saving
     */
    public function saving(Saving $saving)
    {
        $this->authentication($this->savePermission, $saving);
    }

    /**
     * @param Deleting $deleting
     */
    public function deleting(Deleting $deleting)
    {
        $this->authentication($this->deletePermission, $deleting);
    }

    /**
     * Data authentication
     * @param string $type
     * @param Event $event
     */
    protected function authentication(array $dataPermission, Event $event)
    {
        $model = $event->getModel();
        if (!empty($dataPermission)) {
            $user = $this->login->getUser();
            if(!empty($user)){
                //如果依据本身模型表的字段
                if ($dataPermission['type'] == 'field') {
                    if ($user['id'] != $model->getAttributeValue($dataPermission['field'])) {
                        $event->setPropagation(true);
                        throw new UnauthorizedException();
                    }
                } elseif ($dataPermission['type'] == "relation") {
                    if (!$model->{$dataPermission['relation']}->where($dataPermission['filed'], $user['id'])->exists()) {
                        $event->setPropagation(true);
                        throw new UnauthorizedException();
                    }
                }
            }
        }
        $function = Arr::last(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2));
        if (method_exists(parent::class, $function['function'])) {
            parent::$function['function']($event);
        }
    }
}