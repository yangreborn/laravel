<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class AnalysisCodeReview extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'analysis_codereview';

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
        'tool_phabricator_id',
        'period',
        'deadline',
        'commit_count',
        'review_count',
        'deal_count',
        'valid_count',
        'intime_count',
        'extra',
    ];

     /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    public static function getReviewData($period,$deadline = False,$tool_ids){
        $result = [
            'commit_num'=>0,
            'review_num'=>0,
            'true_review_num'=>0,
            'receive_num'=>0,
            'deal_num'=>0,
            'valid_num'=>0,
            'intime_num'=>0,
        ];
        if(!empty($tool_ids)){
            $tools = Phabricator::query()->whereIn('id',$tool_ids)->where('review_type','!=',1)->get();
            foreach($tools as $tool){
                $ids[] = $tool->id;
            }
            if(!empty($ids)){
                $res = self::query()
                ->whereIn('tool_phabricator_id', $ids)
                ->where('period', $period)
                ->when(!empty($deadline),function($query) use($deadline){
                    return $query->where('deadline',$deadline);
                })
                ->first(
                    array(
                        DB::raw('SUM(commit_count) as commit_count'),
                        DB::raw('SUM(review_count) as review_count'),
                    )
                );
                $commit_count = isset($res->commit_count)?(int)$res->commit_count:0;
                $review_count = isset($res->review_count)?(int)$res->review_count:0;
            }
            $res = self::query()
            ->whereIn('tool_phabricator_id', $tool_ids)
            ->where('period', $period)
            ->when(!empty($deadline),function($query) use($deadline){
                return $query->where('deadline',$deadline);
            })
            ->first(
                array(
                    DB::raw('SUM(commit_count) as commit_count'),
                    DB::raw('SUM(review_count) as review_count'),
                    DB::raw('SUM(receive_count) as receive_count'),
                    DB::raw('SUM(deal_count) as deal_count'),
                    DB::raw('SUM(valid_count) as valid_count'),
                    DB::raw('SUM(intime_count) as intime_count'),
                )
            );
            if(empty($commit_count)){
                $commit_count = isset($res->commit_count)?(int)$res->commit_count:0;
                $review_count = $commit_count;
            }
            $true_review_count = isset($res->review_count)?(int)$res->review_count:0;
            $receive_count = isset($res->receive_count)?(int)$res->receive_count:0;
            $deal_count = isset($res->deal_count)?(int)$res->deal_count:0;
            $valid_count = isset($res->valid_count)?(int)$res->valid_count:0;
            $intime_count = isset($res->intime_count)?(int)$res->intime_count:0;
            $review_count = empty($commit_count)?0:($true_review_count/$commit_count>0.9?$commit_count:$true_review_count);

            $result['commit_num'] =  $result['commit_num'] + $commit_count;
            $result['review_num'] =  $result['review_num'] + $review_count;
            $result['true_review_num'] =  $result['true_review_num'] + $true_review_count;
            $result['receive_num'] =  $result['receive_num'] + $receive_count;
            $result['deal_num'] =  $result['deal_num'] + $deal_count;
            $result['valid_num'] =  $result['valid_num'] + $valid_count;
            $result['intime_num'] =  $result['intime_num'] + $intime_count;
        }
        return $result;
    }

}