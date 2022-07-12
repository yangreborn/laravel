<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2017/12/20
 * Time: 15:27
 */

namespace App\Http\Controllers\Traits;

use Symfony\Component\HttpFoundation\Response as FoundationResponse;
use Illuminate\Support\Facades\Response;

trait ApiResponse
{
    protected $http_code = FoundationResponse::HTTP_NOT_FOUND;
    protected $status = 'error';
    protected $msg = '请求出错，请稍后再试！';
    protected $response_data = array();

    protected function getHttpCode()
    {
        return $this->http_code;
    }

    protected function setHttpCode($code)
    {
        $this->http_code = $code;
        return $this;
    }

    protected function getStatus(){
        return $this->status;
    }

    protected function setStatus($status){
        $this->status = $status;
        return $this;
    }

    protected function getMsg(){
        return $this->msg;
    }

    protected function setMsg($msg){
        $this->msg = $msg;
        return $this;
    }

    protected function getResponseData(){
        return $this->response_data;
    }

    protected function setResponseDate($data){
        $this->response_data = $data;
        return $this;
    }

    /**
     * @return mixed
     */
    protected function respond()
    {
        $result = [
            'code' => $this->http_code,
            'status' => $this->status,
            'msg' => $this->msg,
        ];
        !empty($this->response_data)&&($result['data'] = $this->response_data);
        return Response::json($result, $this->http_code);
    }

    /**
     * @param $http_code
     * @param integer $status
     * @return mixed
     */
    protected function getPublicInfo($http_code, $status){
        return $this->setHttpCode($http_code)
            ->setStatus($status);
    }


    /**
     * @param $msg
     * @param $data
     * @return mixed
     */
    public function success($msg, $data = array()){
        return $this->getPublicInfo(FoundationResponse::HTTP_OK, 'success')
            ->setMsg($msg)
            ->setResponseDate($data)
            ->respond();
    }

    /**
     * @param $msg
     * @param  $data
     * @return mixed
     */
    public function failed($msg, $data = array()){
        return $this->setMsg($msg)
            ->setResponseDate($data)
            ->respond();
    }
}