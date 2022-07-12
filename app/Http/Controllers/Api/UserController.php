<?php

namespace App\Http\Controllers\Api;

use App\Events\UserLogin;
use App\Events\UserNotify;
use App\Mail\CaptchaGenerated;
use App\Mail\PasswordGenerated;
use App\Models\Project;
use App\Models\UserCaptcha;
use App\Models\UserContact;
use App\Models\UserProject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Http\Controllers\Traits\ProxyHelpers;
use App\Models\User;
use App\Http\Controllers\ApiController;
use App\Models\DiffcountSearchCondition;
use App\Models\PlmSearchCondition;
use App\Models\TapdSearchCondition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Passport\Events\AccessTokenCreated;

class UserController extends ApiController
{
    use ProxyHelpers;

    /**
     * 登陆接口
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'bail|required|exists:users',
            'password' => 'bail|required|between:5,32',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $tokens = $this->authenticate();

        $info = User::where('email', $request->email)->take(1)->get()->first();

        broadcast(new UserLogin(['user_id' => $info['id'], 'token' => $tokens]));
        event(new UserNotify([
            'user' => $info,
            'server' => $_SERVER,
            'message' => config('useraction.login'),
        ]));

        return $this->success('登陆成功！', ['token' => $tokens, 'info' => $info]);
    }

    /**
     * 获取验证码
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function captcha(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'bail|required|exists:users',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $user = User::where('email', $request->email)->first();
        if ($user->is_admin !== 'guest') {
            $captcha = strtolower(\Illuminate\Support\Str::random(4));
            $result = UserCaptcha::updateOrCreate(['user_id' => $user->id], ['code' => $captcha]);
            $result && Mail::to($user->email)
                ->send(new CaptchaGenerated(['captcha' => $captcha]));

            return $result ? $this->success('获取验证码成功，请查收邮件！') : $this->failed('获取验证码失败，请稍后再试！');
        } else {
            return $this->failed('抱歉，本平台还未对此账号开放，请耐心等待！');
        }
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function passwordForget(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'bail|required|exists:users',
            'captcha' => 'bail|required',
        ]);
        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $user_id = User::where('email', $request->email)->value('id');

        $res = UserCaptcha::orderBy('updated_at', 'desc')
            ->where([
                ['user_id', $user_id],
                ['code', $request->captcha],
                ['updated_at', '>', Carbon::now()->subMinutes(15)],
            ])
            ->first();
        if (!empty($res)){
            UserCaptcha::destroy($user_id);
            $request->user_id = $user_id;
            return $this->passwordReset($request);
        }else{
            return $this->failed('验证码错误或过期，请核查！');
        }
    }

    public function info()
    {
        return $this->success('请求成功！', Auth::guard('api')->user());
    }

    public function userList(Request $request)
    {
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field'])&&!empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';
        $search = $request->search;
        $user_model = User::active()->except()->orderBy($field, $order);
        if (!empty($search)){
            if(!empty($search['key'])){
                $search_text = $search['key'];
                $user_model = $user_model
                    ->where(function ($query) use ($search_text){
                        $query
                            ->where('name', 'like', "%$search_text%")
                            ->orWhere('email', 'like', "%$search_text%");
                    });
            }
            if (!empty($search['category'])){
                $search_category = $search['category'];
                if (!empty($search_category[1])){
                    $user_model = $user_model->whereHas('departments', function ($query) use ($search_category){
                        $query->where('department_id', $search_category[1]);
                    })->distinct();
                }
            }
        }
        $users = $user_model->paginate($page_size);
        return $this->success('用户列表获取成功!', [
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'data' => $users->items(),
        ]);
    }

    public function search(Request $request)
    {
        $key = $request->key;
        $except = $request->except ?? [];
        $users = User::active()->except()
            ->where(function($query) use ($key){
                $query->where('name', 'like', "%$key%")
                    ->orWhere('email', 'like', "%$key%");
            })
            ->whereNotIn('id', $except)
            ->limit(7)
            ->get();
        return $this->success('用户列表获取成功!', $users);
    }

    public function allUsers()
    {
        return $this->success('获取所有用户成员成功!', User::activeUsers());
    }

    /**
     * 人员添加
     * @param Request $request
     * @throws ApiException
     * @return array
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'bail|required|email|unique:users',
            'introduction' => 'max:255',
            'telephone' => 'max:45',
            'mobile' => 'max:45',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $extras = [
            'password' => bcrypt( \Illuminate\Support\Str::random(6) ),
            'password_expired' => Carbon::now()->addDays(config('api.password_expired'))->toDateTimeString(),
            'remember_token' => \Illuminate\Support\Str::random(10),
        ];
        $input = \Illuminate\Support\Facades\Request::all();
        $result = User::create(array_merge($input, $extras));

        // 添加部门信息
        if (isset($input['department_id']) && !empty($input['department_id'])) {
            User::where('id', $result['id'])->update(['is_department_conformed' => 1]);
            foreach ($input['department_id'] as &$item){
                $item = [
                    'user_id' => $result['id'],
                    'department_id' => $item
                ];
            }
            DB::table('user_departments')->insert($input['department_id']);
        }

        // 添加svn信息
        if (!empty($request->svn_id)) {
            DB::table('svn_users')
                ->where('id', $request->svn_id)
                ->update(['author_id' => $result['id']]);
        }
        return $this->success('添加用户成功!');
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function edit(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'introduction' => 'max:255',
            'telephone' => 'max:45',
            'mobile' => 'max:45',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $user = User::find($request->id);
        $user->introduction = $request->introduction;
        $user->telephone = $request->telephone;
        $user->mobile = $request->mobile;
        $user->is_admin = $request->is_admin;
        $user->save();

        // 修改svn信息
        if (!empty($request->svn_id)) {
            DB::table('svn_users')
                ->where('author_id', $user->id)
                ->update(['author_id' => null]);

            DB::table('svn_users')
                ->where('id', $request->svn_id)
                ->update(['author_id' => $user->id]);
        }


        return $this->success('修改用户成功!');
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function passwordReset(Request $request){
        $user = User::find($request->user_id);

        // 生成随机密码
        $password = \Illuminate\Support\Str::random(6);

        $user->password = bcrypt($password);
        $user->password_expired = Carbon::now()->addDays(config('api.password_expired'))->toDateTimeString();
        $result = $user->save();
        if ($result) {
            broadcast(new UserLogin(['user_id' => $user->id, 'token' => []]));
            Mail::to($user->email)
                ->send(new PasswordGenerated(['password' => $password]));
            return $this->success('重置用户密码成功!');
        }

        return $this->failed('重置用户密码失败，请稍后重试!');
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function userDelete(Request $request){
        $user = User::find($request->user_id);
        $user->delete();
        return $this->success('删除用户成功!');
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function profile(Request $request){
        $validator = Validator::make($request->all(), [
            'introduction' => 'max:255',
            'telephone' => 'max:45',
            'mobile' => 'max:45',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $user = User::find($request->id);

        $user->introduction = $request->introduction;
        $user->telephone = $request->telephone;
        $user->mobile = $request->mobile;

        $user->save();
        return $this->success('修改用户成功!');
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function passwordModify(Request $request){
        $validator = Validator::make($request->all(), [
            'old_password' => 'bail|required|between:5,32',
            'password' => 'bail|required|between:5,32|confirmed|different:old_password',
            'password_confirmation' => 'bail|required|between:5,32',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $user = User::find($request->id);
        if (Hash::check($request->get('old_password'), $user->password)){
            $user->password = bcrypt($request->password);
            $user->password_expired = Carbon::now()->addDays(config('api.password_expired'))->toDateTimeString();
            $user->save();
            return $this->success('修改密码成功!');
        }else{
            return $this->failed('原密码错误!');
        }
    }

    public function contactList(Request $request){
        $id = Auth::guard('api')->id();
        $model = UserContact::where('user_id', $id);
        if (!empty($request->search)){
            $search = $request->search;
            if (!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('name', 'like', "%$search_text%");
            }
        }

        return $this->success('获取列表成功！', $model->get());
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function contactAdd(Request $request){
        $id = Auth::guard('api')->id();

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'max:45',
                Rule::unique('user_contacts')
                    ->where(function ($query) use ($id) {
                        $query->where('user_id', $id);
                    })
            ],
            'introduction' => 'max:255',
            'to' => 'required|max:255',
            'cc' => 'required|max:255',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $data = new UserContact;
        $data->user_id = $id;
        $data->name = $request->name;
        $data->introduction = $request->introduction ?? '';
        $data->to = array_column($request->to, 'key');
        $data->cc = array_column($request->cc, 'key');

        $data->save();

        return $this->success('添加数据成功！');
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function contactEdit(Request $request){
        $id = Auth::guard('api')->id();

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
            'name' => [
                'required',
                'max:45',
                Rule::unique('user_contacts')
                    ->ignore($request->id)
                    ->where(function ($query) use ($id) {
                        $query->where('user_id', $id);
                    })
            ],
            'introduction' => 'max:255',
            'to' => 'required|max:255',
            'cc' => 'required|max:255',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $data = UserContact::where('user_id', $id)->find($request->id);
        if (!empty($data)){
            $data->name = $request->name;
            $data->introduction = $request->introduction ?? '';
            $data->to = array_column($request->to, 'key');
            $data->cc = array_column($request->cc, 'key');

            $data->save();

            return $this->success('修改数据成功！');
        }else{
            return $this->failed('修改数据失败！');
        }
    }

    public function contactDelete(Request $request){
        $item = UserContact::find($request->item_id);
        $item->delete();
        return $this->success('删除条目成功!');
    }

    public function projectList(Request $request){
        $id = Auth::guard('api')->id();
        $model = UserProject::where('user_id', $id);
        if (!empty($request->search)){
            $search = $request->search;
            if (!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('name', 'like', "%$search_text%");
            }
        }

        return $this->success('获取列表成功！', $model->get());
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function projectAdd(Request $request){
        $id = Auth::guard('api')->id();
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'max:45',
                Rule::unique('user_projects')
                    ->where(function ($query) use ($id) {
                        $query->where('user_id', $id);
                    })
            ],
            'introduction' => 'max:255',
            'department_id' => 'required',
            'review_tool_type' => 'required',
            'projects' => 'required',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $data = new UserProject;
        $data->user_id = $id;
        $data->department_id = $request->department_id[1];
        $data->name = $request->name;
        $data->introduction = $request->introduction ?? '';
        $data->projects = array_column($request->projects, 'key');
        $data->tool_type = $request->review_tool_type ?? 1;
        $data->members = $request->members;

        $data->save();

        return $this->success('添加数据成功！');
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function projectEdit(Request $request){
        $id = Auth::guard('api')->id();
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
            'name' => [
                'required',
                'max:45',
                Rule::unique('user_projects')
                    ->ignore($request->id)
                    ->where(function ($query) use ($id) {
                        $query->where('user_id', $id);
                    })
            ],
            'introduction' => 'max:255',
            'projects' => 'required',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $data = UserProject::where('user_id', $id)->find($request->id);
        if (!empty($data)){
            $data->department_id = $request->department_id[1];
            $data->name = $request->name;
            $data->introduction = $request->introduction ?? '';
            $data->projects = array_column($request->projects, 'key');
            $data->tool_type = $request->tool_type ?? 1;
            $data->members = $request->members;

            $data->save();

            return $this->success('修改数据成功！');
        }else{
            return $this->failed('修改数据失败！');
        }
    }

    public function projectDelete(Request $request){
        $item = UserProject::find($request->id);
        $item->delete();
        return $this->success('删除条目成功!');
    }

    public function projectsMembers(Request $request){
        $project_ids = array_column($request->ids, 'key') ?? [];
        $data = Project::query()->whereIn('id', $project_ids)->get();
        $result = [];
        foreach($data as $item) {
            $result[] = [
                'key' => (string)$item->id,
                'value' => (string)$item->id,
                'title' => $item->name,
                'children' => array_map(function($member) use($item){
                    return [
                        'key' => $item->id . '-' . $member['id'],
                        'value' => $item->id . '-' . $member['id'],
                        'title' => $member['name'],
                    ];
                }, $item->members['data']),
            ];
        }
        // 去除成员为空数据
        $result = array_filter($result, function ($item){
            return sizeof($item['children']) === 0 ? false : true;
        });

        return $this->success('获取项目成员信息成功！', $result);
    }

    public function unlinkSvnUsers(Request $request){
        return $this->success('未关联用户svn账号列表', DB::table('svn_users')
            ->whereNull('author_id')
            ->orWhere('id', $request->svn_id)
            ->select(['id', 'svn_name'])
            ->get());
    }

    public function logout()
    {
        event(new AccessTokenCreated('', Auth::guard('api')->id(), env('OAUTH_CLIENT_ID')));
        return $this->success('成功登出！');
    }

    public function templateList(){
        $id = Auth::guard('api')->id();

        // plm search conditions
        $plm_search_conditions = PlmSearchCondition::where('user_id', $id)
            ->select('id', 'title', DB::Raw("'plm' AS tool"), 'created_at', 'updated_at')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->toArray();

        $diffcount_search_conditions = DiffcountSearchCondition::where('user_id', $id)
            ->select('id', 'title', DB::Raw("'diffcount' AS tool"), 'created_at', 'updated_at')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->toArray();

        $tapd_search_conditions = TapdSearchCondition::where('user_id', $id)
            ->select('id', 'title', DB::Raw("'tapd' AS tool"), 'created_at', 'updated_at')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->toArray();
        
        $res = array_merge(
            $plm_search_conditions,
            $diffcount_search_conditions,
            $tapd_search_conditions
        );

        return $this->success('获取列表成功！', $res);
    }

    public function templateEdit(Request $request){
        $user_id = Auth::guard('api')->id();
        $tool = $request->tool ?? '';
        $id = $request->id ?? '';
        $title = $request->title ?? '';

        if (!empty($tool) && !empty($id) && !empty($title)) {
            switch($tool) {
                case 'plm':
                    PlmSearchCondition::where('user_id', $user_id)
                        ->where('id', $id)
                        ->update([
                            'title' => $title
                        ]);
                    break;
                case 'diffcount':
                    DiffcountSearchCondition::where('user_id', $user_id)
                        ->where('id', $id)
                        ->update([
                            'title' => $title
                        ]);
                    break;
                case 'tapd':
                    TapdSearchCondition::where('user_id', $user_id)
                        ->where('id', $id)
                        ->update([
                            'title' => $title
                        ]);
                    break;
            }
        }

        return $this->success('数据修改成功！');
    }

    public function templateDelete(Request $request){
        $user_id = Auth::guard('api')->id();
        $tool = $request->tool ?? '';
        $id = $request->id ?? '';

        if (!empty($tool) && !empty($id)) {
            switch($tool) {
                case 'plm':
                    PlmSearchCondition::where('user_id', $user_id)
                        ->where('id', $id)
                        ->delete();
                    break;
                case 'diffcount':
                    DiffcountSearchCondition::where('user_id', $user_id)
                        ->where('id', $id)
                        ->delete();
                    break;
                case 'tapd':
                    TapdSearchCondition::where('user_id', $user_id)
                        ->where('id', $id)
                        ->delete();
                    break;
            }
        }

        return $this->success('数据删除成功！');
    }
}
