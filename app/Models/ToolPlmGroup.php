<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToolPlmGroup extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'relative_id', 'user_id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = ['department_name'];

    public $timestamps = false;

    public function department()
    {
        return $this->belongsTo('App\Models\Department', 'relative_id', 'id')->select('id', 'name')->withDefault([
            'name' => '',
        ]);
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id')->select('id', 'name', 'email')->withDefault([
            'name' => '',
        ]);
    }

    public function getDepartmentNameAttribute()
    {
        return $this->department()->value('name');
    }

    static public function departmentGroupData()
    {
        $res = self::query()->select(['id as key', 'name as title', 'relative_id'])->get()->toArray();
        $res = collect($res);
        return $res->groupBy('department_name')->map(function ($item, $key) {
            $first_child = $item->first();
            $department_id = $first_child['relative_id'] ?: 0;
            return [
                'key' => 'department_id_'.$department_id,
                'title' => $key ?: '未关联部门',
                'value' => 'department_id_'.$department_id,
                'children' => $item->map(function ($item){
                    return [
                        'key' => $item['key'],
                        'title' => $item['title'],
                        'value' => $item['key'],
                    ];
                }),
            ];
        })->values();
    }

}