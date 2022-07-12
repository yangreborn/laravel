<?php

namespace App\Http\Controllers\Api;

use App\Models\Department;
use App\Services\CreateJenkinsJob;
use App\Exceptions\ApiException;
use App\Http\Controllers\ApiController;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Facades\Validator;

class JenkinsController extends ApiController
{
    /**
     * 获取二级部门及项目
     */
    public function projectLinkedList(Request $request)
    {
        return $this->success('获取已关联项目列表成功！', Department::getProjects());
    }

    /**
     * 部署
     * @param Request $request
     * @return success
     */
    public function createJenkinsJob(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tool' => 'required',
            'flow' => 'required',
            'subject' => 'max:255',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $input = \Illuminate\Support\Facades\Request::all();
        CreateJenkinsJob::handleData($input);
        return $this->success('创建Jenkins Job成功!');
    }
}