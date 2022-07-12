<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
        $this->exceptionNotify($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if (preg_match('/\/api\/'.env('API_VERSION').'\//', $request->getPathInfo())){
            $exception_name = get_class($exception);
            $msg = $exception->getMessage();
            $code = strpos($exception_name, 'AuthenticationException') > 0 ? 401 : ($exception->getCode()?:404);
            $result = array(
                'code'      => $code,
                'status'    => 'error',
                'msg'       => $msg ?:'请求出错，请稍后再试！',
            );
            return response()->json($result, $code);
        }

        return parent::render($request, $exception);
    }

    private function exceptionNotify(Exception $exception) {
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $user = Auth::user();
        $name_email = $user ? $user->name_email : '--';


        $today = Carbon::now()->toDateTimeString();
        $content = <<<markdown
### 度量平台异常信息 @ $today\n
> 信息： <font color="comment">$message</font>
> 文件： <font color="comment">$file</font>
> 行号： <font color="comment">$line</font>
> 操作用户： <font color="comment">$name_email</font>\n
markdown;

        wechat_bot(['content' => $content], config('wechat.wechat_robot_key.mpl_log'), 'markdown');
    }
}
