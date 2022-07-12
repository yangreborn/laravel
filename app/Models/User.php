<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
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
        'kd_uid',
        'name',
        'email',
        'password',
        'introduction',
        'telephone',
        'mobile',
        'password_expired',
        'remember_token',
        'is_admin',
        'is_department_conformed',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $appends = ['departments', 'is_password_expired', 'name_email', 'svn_info'];

    public function getNameEmailAttribute(){
        return "{$this->name}/{$this->email}";
    }

    /**
     * get the is_admin's text.
     *
     * @param  string  $value
     * @return mixed
     */
    public function getIsAdminAttribute($value)
    {
        $user_type = config('api.user_type');
        return $user_type[$value];
    }

    /**
     * set the is_admin's value.
     *
     * @param  string  $value
     * @return void
     */
    public function setIsAdminAttribute($value)
    {
        $this->attributes['is_admin'] = array_search($value, config('api.user_type'));
    }

    public function getDepartmentsAttribute()
    {
        $data = $this->departments()->select('department_id')->get()->toArray();
        return [
            'ids' => array_column($data, 'department_id'),
            'names' => array_column($data, 'department'),
            'data' => array_map(function($item){
                return [
                    'id' => $item['department_id'],
                    'name' => $item['department'],
                ];
            }, $data),
        ];
    }

    public function departments()
    {
        return $this->hasMany('App\Models\UserDepartment', 'user_id', 'id')
            ->select('department_id');
    }

    public function getIsPasswordExpiredAttribute(){
        // 已过期：1；3天内过期：2；未过期：0.
        $password_expired = strtotime($this->password_expired);
        $seconds = $password_expired - time();
        return $seconds>0 ? ($seconds>3*24*60*60 ? 0 : 2) : 1;
    }

    public static function getOrCreateUser($email) {
        $extras = [
            'name' => substr($email, 0, strpos($email, '@')),
            'password' => bcrypt( \Illuminate\Support\Str::random(6) ),
            'password_expired' => Carbon::now()->addDays(config('api.password_expired'))->toDateTimeString(),
            'remember_token' => \Illuminate\Support\Str::random(10),
        ];
        return self::firstOrCreate(['email' => $email], $extras);
    }

    /**
     * 获取用户部门信息是否确认
     * @param $user_id
     * @return bool
     */
    public static function isDepartmentConformed($user_id){
        $status = self::where('id', $user_id)->value('is_department_conformed');
        return $status == 1 ? true : false;
    }

    public function svnInfo()
    {
        return $this->hasOne('App\Models\SvnUser', 'author_id', 'id');
    }

    public function getSvnInfoAttribute() {
        $res = $this->svnInfo()->value('id');
        return !empty($res) ? $res : '';
    }

    public function ldapInfo()
    {
        return $this->hasOne('App\Models\LdapUser', 'uid', 'kd_uid');
    }

    public function getNameAttribute($value)
    {
        $ldap_info = $this->ldapInfo;
        return $ldap_info ? $ldap_info->name : $value;
    }

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function scopeExcept($query) {
        return $query->whereNotIn('email', config('api.special_user_email'));
    }

    /**
     * 参照Ldap中数据更新users表中数据--将users表中存在而ldap中不存在人员状态禁用
     */
    static public function syncUserWithLdap() {
        $users = self::all();
        $special_user_email = config('api.special_user_email');
        $special_user_email[] = config('api.test_email');
        foreach ($users as $user) {
            // echo $user->email . "\n";
            $need_update = false;
            if(LdapUser::query()->where('mail', $user->email)->count() === 0) {
                $need_update = $user->status !== 0;
                $user->status = 0;
            }

            // 特殊邮箱处理
            if(in_array($user->email, $special_user_email)) {
                $need_update = $user->status !== 1;
                $user->status = 1;
            }

            if ($need_update) {
                $user->save();
            }
        }
    }

    static public function activeUsers() {
        $users = self::active()
            ->except()
            ->select(['id', 'name', 'email', 'is_admin', 'kd_uid'])
            ->get()
            ->makeHidden(['departments', 'is_password_expired', 'name_email', 'svn_info', 'ldapInfo']);
        return $users;
    }
}
