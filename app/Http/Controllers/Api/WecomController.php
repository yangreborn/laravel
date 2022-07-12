<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\WecomCallbackService;

class WecomController {

    // 设置回调接口时验证
    public function callback(Request $request) {
        $wxcpt = new WecomCallbackService(config('wechat.callback_token'), config('wechat.encoding_aes_key'), config('wechat.company_pid'));

        $msg_signature = $request->msg_signature;
        $timestamp = $request->timestamp;
        $nonce = $request->nonce;
        $echostr = $request->echostr;

        $sEchoStr = "";

        // call verify function
        $errCode = $wxcpt->VerifyURL($msg_signature, $timestamp, $nonce, $echostr, $sEchoStr);
        if ($errCode == 0) {
            echo $sEchoStr . "\n";
        } else {
            wlog('WECHAT_CALLBACK_ERR:', $errCode . "\n\n");
        }
    }

    // 处理回调返回的消息
    public function message(Request $request) {
        $wxcpt = new WecomCallbackService(config('wechat.callback_token'), config('wechat.encoding_aes_key'), config('wechat.company_pid'));

        $msg_xml = '';
        $decrypt_err_code = $wxcpt->DecryptMsg($request->msg_signature, $request->timestamp, $request->nonce, file_get_contents('php://input'), $msg_xml);

        if ($decrypt_err_code == 0) {
            $wxcpt->resolve($msg_xml);

            return $wxcpt->response($request->nonce);
            
        }

        wlog('WECHAT_CALLBACK_DECRYPT_ERR:', $decrypt_err_code . "\n\n");
        return '';
    }
}