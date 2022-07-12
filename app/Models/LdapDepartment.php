<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class LdapDepartment extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'parent_id', 'name', 'uid', 'dn', 'introduction', 'supervisor_id', 'status', 'extra'
    ];

    protected $casts = [
        'extra' => 'array',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * 根据部门id获取最高两级父部门
     */
    static public function getParents(&$id) {
        $result = [];
        do {
            $current = self::query()->where('status', 1)->find($id);
            if (!empty($current)) {
                $result[] = $current['name'];
                $id = $current['parent_id'];
            }
        } while (!empty($current) && $current['parent_id'] !== 0);

        if (!empty($result)) {
            $result = array_slice(array_reverse($result), 1, 2);
            if (sizeof($result) > 1) {
                $result[1] = str_replace($result[0], '', $result[1]);
            }
        }

        return $result;
    }

    static public function syncToDepartment() {
        $bot = User::query()->where('email', 'bot')->first()->toArray();

        $need_sync_departments = self::query()->whereIn('uid', config('api.sync_ldap_departments'))->get()->toArray();

        foreach($need_sync_departments as $item) {
            $res = Department::firstOrNew([
                'name' => $item['name']
            ], [
                'status' => $item['status'],
                'parent_id' => 0,
            ]);
            $res->supervisor_id = $res->supervisor_id ?: $bot['id'];
            $res->save();
            $department_children = self::query()->where('parent_id', $item['id'])->get()->toArray();
            foreach($department_children  as $child) {
                $name = str_replace($res->name, '', $child['name']);
                $result = Department::firstOrNew([
                    'name' => $name
                ], [
                    'status' => $child['status'],
                    'parent_id' => $res->id,
                ]);
                $result->supervisor_id = $result->supervisor_id ?: $bot['id'];
                $result->save();
            }
        }
    }

    static public function getAllParents($id) {
        $res = self::query()
            ->where('status', 1)
            ->where('id', $id)
            ->value('dn');
        
        $result = [];
        if (!empty($res)) {
            $arr = explode(',', $res);
            $arr = array_map(function ($item) {
                return substr($item, 3);
            }, $arr);
            $result = array_slice($arr, 1, -4);
        }
        return array_reverse($result);
    }

    /**
     * 获取父部门下所有子部门
     */
    static public function getChildren($parent) {
        $result = [];

        $res = self::query()
            ->where('parent_id', $parent)
            ->where('status', 1)
            ->pluck('id')
            ->toArray();
        $result = array_merge($result, $res);
        foreach($res as $id) {
            $r = self::getChildren($id);
            $result = array_merge($result, $r);
        }
        return $result;
    }

    /**
     * 获取sqa分管部门的对应关系（含子部门及组）
     */
    static public function departmentSqa() {
        $all_department_sqa = !Cache::has('all-department-sqa') ? [] : Cache::get('all-department-sqa');
        if (empty($all_department_sqa))  {
            $department_sqa = config('api.department_sqa');
            $sqa = array_combine(
                array_column(sqa(), 'name'),
                array_column(sqa(), 'uid')
            );
    
    
            $ids = static::query()
                ->whereIn('name', array_keys($department_sqa))
                ->where('status', '1')
                ->pluck('id', 'name');
    
            foreach($ids as $k=>$v) {
                $all_department_sqa[$v] = $sqa[$department_sqa[$k]];
                $res = static::getChildren($v);
                foreach($res as $item) {
                    $all_department_sqa[$item] = $sqa[$department_sqa[$k]];
                }
            }
            Cache::put('all-department-sqa', $all_department_sqa, Carbon::now()->endOfMonth());
        }
        return $all_department_sqa;
    }
}
