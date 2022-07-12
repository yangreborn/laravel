<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Models\Role;
use App\Http\Controllers\ApiController;

use Illuminate\Support\Facades\Validator;

class RoleController extends ApiController
{
    /**
     * 角色列表
     * @return mixed
     */
    public function roleList()
    {
        $page_size = config('api.page_size');
        $roles = Role::paginate($page_size);
        return $this->success('角色列表获取成功!', $roles);
    }

    /**
     * 角色添加
     * @param Request $request
     * @throws ApiException
     * @return array
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:45',
            'introduction' => 'max:255',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $input = \Illuminate\Support\Facades\Request::all();
        Role::create($input);

        return $this->success('添加角色成功!');
    }

    /**
     * 角色修改
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function edit(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:45',
            'introduction' => 'max:255',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $role = Role::find($request->id);

        $role->name = $request->name;
        $role->introduction = $request->introduction;

        $role->save();
        return $this->success('修改角色成功!');
    }

    /**
     * 角色删除
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function delete(Request $request){
        $role = Role::find($request->role_id);
        $role->delete();
        return $this->success('删除角色成功!');
    }
}
