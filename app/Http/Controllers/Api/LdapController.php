<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\LdapDepartment;
use App\Models\LdapUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LdapController extends ApiController
{
    public function activeUsers()
    {
        return $this->success('获取用户数据成功！', LdapUser::activeUsers());
    }

    public function sqaDeaprtment(Request $request) {
        $all_department_sqa = LdapDepartment::departmentSqa();
        $user_id = Auth::guard('api')->id();
        $user = User::query()->find($user_id)->toArray();
        $uid = !empty($user) ? $user['kd_uid'] : null;
        $res = array_filter($all_department_sqa, function ($item) use($uid) {
            return $item === $uid;
        });

        $page_size = $request->per_page ?? config('api.page_size');
        $sort = ($request->sort ?? []) + ['field' => '', 'order' => ''];
        $field = $sort['field'];
        $order = $sort['order'];
        $search = $request->search;
        $result = LdapDepartment::query()
            ->when(!empty($search)&&key_exists('key', $search)&&!empty($search['key']), function ($q) use($search) {
                $q->where('name', 'like', '%' . $search['key'] . '%');
            })
            ->when(
                !empty($order) && !empty($field),
                function($query) use($field, $order) {
                    $query->orderBy($field, $order === 'ascend' ? 'asc' : 'desc');
                    if($field !== 'created_at') {
                        $query->orderBy('created_at', 'desc');
                    }
                },
                function($query) {
                    $query->orderBy('created_at', 'desc');
                }
            )
            ->whereIn('id', array_keys($res ?? []))
            ->where('status', 1)
            ->orderBy('uid')
            ->paginate($page_size);

        foreach($result as &$item) {
            $item['dn'] = array_reverse(
                array_map(function($cell) {
                    return str_replace('OU=', '', $cell);
                }, array_slice(explode(',', $item['dn']), 1, -4))
            );

            if (!empty($item['supervisor_id'])) {
                $user = LdapUser::query()->find($item['supervisor_id']);
                $item['supervisor'] = [
                    'name' => $user->name,
                    'email' => $user->mail,
                ];
            }
        }
        return $this->success('列表获取成功!', $result);
    }

    public function sqaDepartmentEdit(Request $request) {
        if ($request->id) {
            $fields = [];
            if (intval($request->supervisor_id) !== 0) {
                $user = User::find($request->supervisor_id)->toArray();
                $fields['supervisor_id'] = !empty($user) ? $user['ldap_info']['id'] : null;
            }
            $fields['extra'] = !empty($request->extra) ? json_encode($request->extra, true) : null;
            LdapDepartment::query()
                ->where('id', $request->id)
                ->where('status',1)
                ->update($fields);
        }
        return $this->success('信息修改完成！');
    }
}
