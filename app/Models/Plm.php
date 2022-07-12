<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Plm extends Authenticatable
{
    use HasApiTokens, Notifiable , SoftDeletes;

    protected $table = 'plm_data';

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
        'product_family',
        'group',
        'subject',
        'creator',
        'creator_mail',
        'reviewer',
        'reReviewer',
        'bug_explain',
        'fre_occurrence',
        'inside_version',
        'version',
        'performance',
        'product_name',
        'reject',
        'seriousness',
        'solution',
        'solve_status',
        'description',
        'status',
        'user_emails',
        'create_time',
        'audit_time',
        'distribution_time',
        'close_date',
        'solve_time',
        'pro_solve_date',
        'project_id',
        'product_id',
        'group_id',
        'product_family_id',
        'psr_number',
        'deleted_at',
        'delay_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    public $timestamps = false;

    public function getGroupAttribute($value){
        return !empty($value) ? $value : '<未知>';
    }

    public function getSubjectAttribute($value){
        return !empty($value) ? $value : '<未知>';
    }

    public function getProductNameAttribute($value){
        return !empty($value) ? $value : '<未知>';
    }

    public function getReviewerAttribute($value){
        return !empty($value) ? $value : '<未知>';
    }

    public function getCreatorAttribute($value){
        return !empty($value) ? $value : '<未知>';
    }

}