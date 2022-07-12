<?php

namespace App\Models\GlobalReportData;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class GetDepartInfo extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'projects';

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
    protected $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];
	protected static $departInfo = array();

    // protected $appends = ['project_checkdata_summary'];
	#代码评审&编译获取部门数据
    public static function getDepartInfo(){
		if(!empty(self::$departInfo)){
			return self::$departInfo;
		}
		//获取一级部门，二级部门，项目列表
		#$projects = DB::table('projects')->where('weekly_assessment',1)->get();
		$sql_res = DB::table('departments')->orderBy('parent_id', 'ASC')->get()->toArray();
		foreach($sql_res as $item){
			if(!$item->parent_id){
				$departs[$item->id]['name'] = $item->name;
			}
			else{
				$departs[$item->parent_id][$item->id]['name'] = $item->name;
			}
		}
		foreach($departs as $key=>$value){
			$key_arrs[$key] = array_keys($value);
		}
		$sql_res = DB::table('projects')/*->where('weekly_assessment',1)*/->get()->toArray();
		foreach($sql_res as $item){
			foreach($key_arrs as $key=>$value){
				if(in_array($item->department_id,$value)){
					$res[$item->id]['name'] = $item->name;
					$res[$item->id]['depart1'] = $departs[$key]['name'];
					$res[$item->id]['depart2'] = $departs[$key][$item->department_id]['name'];
				}
			}
		}
		//获取二级部门列表
		$department_list = DB::table('departments')->where('parent_id','!=',0)->get()->toArray();
		$arrayDeparts =array();
		foreach($department_list as $item){
			$arrayDeparts[$item->name] = array();
			$arrayDeparts[$item->name]['deal_num'] = 0;
			$arrayDeparts[$item->name]['review_num'] = 0;
			$arrayDeparts[$item->name]['failed_count'] = 0;
		}
		self::$departInfo = array($res,$arrayDeparts);
		return self::$departInfo;
	}
}