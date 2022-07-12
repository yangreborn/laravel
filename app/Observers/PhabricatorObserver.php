<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/7/31
 * Time: 16:02
 */

namespace App\Observers;

use App\Models\Phabricator;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use App\Models\UserDepartment;

class PhabricatorObserver
{
    public function updated(Phabricator $phabricator) {
        $modified = $phabricator->getDirty();
        // 当project_id被修改时触发事件
        if (array_key_exists('project_id', $modified)) {
            $phabricator_id = $phabricator->getAttributeValue('id');
            $current_project_id = $phabricator->getAttributeValue('project_id');
            $previous_project_id = $phabricator->getOriginal('project_id');

            if (!empty($current_project_id)) { // 关联项目
                $project = Project::find($current_project_id);
                $is_member_conformed = $project->is_member_conformed === 1;
                if (!$is_member_conformed) { // 已确认项目成员的，不能自动修改其成员
                    // 自动设定项目成员以及部门信息
                    $auto_members = Phabricator::getAutoMembers($phabricator_id);
                    foreach ($auto_members as $auto_member) {
                        $is_department_conformed = User::isDepartmentConformed($auto_member);
                        // 已确认用户部门的，不能自动修改其部门信息
                        !$is_department_conformed && UserDepartment::updateOrCreate(['user_id' => $auto_member, 'department_id' => $project->department_id]);
                        ProjectUser::updateOrCreate(['project_id' => $project->id, 'user_id' => $auto_member]);
                    }
                }
            } else { // 取消关联
                $project = Project::find($previous_project_id);
                $is_member_conformed = $project->is_member_conformed === 1;
                !$is_member_conformed && ProjectUser::query()->where('project_id', $project->id)->delete();
            }
        }
    }
}