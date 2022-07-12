<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use tests\Mockery\Adapter\Phpunit\EmptyTestCase;

class TapdWorkflow extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tapd_workflow';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['project_id', 'tapd_status', 'custom_status', 'sort'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * @param $workflow array 项目的工作流
     * @param $status string 映射的状态
     * @return array
     */
    static public function getStatus($workflow, $status){
        $init_value = ['tapd_status' => '', 'custom_status' => '', 'sort' => 0, 'mapping_status' => '', 'executor' => ''];
        $filtered_workflow = array_filter($workflow, function ($item) use ($status){
            return $item['mapping_status'] === $status;
        });
        return !empty($filtered_workflow) ? \Illuminate\Support\Arr::first($filtered_workflow) : $init_value;
    }

}
