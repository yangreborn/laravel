<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class AnalysisDiffcount extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'analysis_diffcount';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tool_diffcount_id',
        'period',
        'deadline',
        'add_line',
        'mod_line',
        'del_line',
        'awm_line',
        'blk_line',
        'cmt_line',
        'nbnc_line',
        'extra',
    ];

     /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    public static function getDiffcountData($period,$deadline = False,$tool_ids){
        if(!empty($tool_ids)){
            $res = self::query()
            ->whereIn('tool_diffcount_id', $tool_ids)
            ->where('period', $period)
            ->when(!empty($deadline),function($query) use($deadline){
                return $query->where('deadline',$deadline);
            })
            ->first(
                array(
                    DB::raw('SUM(add_line) as add_line'),
                    DB::raw('SUM(mod_line) as mod_line'),
                    DB::raw('SUM(del_line) as del_line'),
                    DB::raw('SUM(awm_line) as awm_line'),
                    DB::raw('SUM(blk_line) as blk_line'),
                    DB::raw('SUM(cmt_line) as cmt_line'),
                    DB::raw('SUM(nbnc_line) as nbnc_line'),
                )
            );
            $result['add_line'] = isset($res->add_line)?(int)$res->add_line:0;
            $result['mod_line'] = isset($res->mod_line)?(int)$res->mod_line:0;
            $result['del_line'] = isset($res->del_line)?(int)$res->del_line:0;
            $result['awm_line'] = isset($res->awm_line)?(int)$res->awm_line:0;
            $result['blk_line'] = isset($res->blk_line)?(int)$res->blk_line:0;
            $result['cmt_line'] = isset($res->cmt_line)?(int)$res->cmt_line:0;
            $result['nbnc_line'] = isset($res->nbnc_line)?(int)$res->nbnc_line:0;
        }
        return $result??[];
    }

}