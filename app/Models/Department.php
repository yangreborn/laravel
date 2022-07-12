<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Authenticatable
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
    protected $fillable = [
        'name', 'introduction', 'parent_id', 'supervisor_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    public static function getInfo($department_id){
        $parent = null;
        $department = self::where('id', $department_id)->first(['id', 'parent_id', 'name']);
        if ($department->parent_id){
            $parent = self::where('id', $department->parent_id)->first(['id', 'parent_id', 'name']);
        }
        if ($parent){
            return [
                ['id' => $parent->id, 'name' => $parent->name],
                ['id' => $department->id, 'name' => $department->name],
            ];
        }else{
            return [
                ['id' => $department->id, 'name' => $department->name],
            ];
        }
    }

    public static function getProjects(){
        $data = [];
        $result = [];
        $parents = self::select(['id', 'name'])->where('parent_id', 0)->get()->toArray();
        foreach($parents as &$parent){
            $department = self::select(['id', 'name'])->where('parent_id', $parent['id'])->get()->toArray();
            foreach($department as &$item){
                $project = Project::select(['id', 'name'])->where('department_id', $item['id'])->where('stage', '<>', 5)->get()->each(function($product){
                    $product->makeHidden(['members', 'expect_index', 'tools']);
                })->toArray();
                $item["grandson"] = $project;
            }
            $parent["children"] = $department;
            $data[] = $parent;
        }
        $result = self::processing_data($data);
        return $result;
    }

    private static function processing_data($data){
        $result = [];
        foreach($data as $parent){
            $result[] = [
                'id' => $parent["id"],
                'pId' => 0,
                'value' => $parent["id"],
                'title' => $parent["name"],
            ];
            foreach($parent["children"] as $child){
                if(!empty($child["grandson"])){
                    $result[] = [
                        'id' => $parent["id"] . "-" . $child["id"],
                        'pId' => $parent["id"],
                        'value' => $parent["id"] . "-" . $child["id"],
                        'title' => $child["name"],
                    ];
                    foreach($child["grandson"] as $grand){
                        $result[] = [
                            'id' => $parent["id"] . "-" . $child["id"] . "-" . $grand["id"],
                            'pId' => $parent["id"] . "-" . $child["id"],
                            'value' => $parent["id"] . "-" . $child["id"] . "-" . $grand["id"],
                            'title' => $grand["name"],
                            'isLeaf' => true,
                        ];
                    }
                }
            }
        }
        return $result;
    }
}
