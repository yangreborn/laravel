@include('emails.header')

<p style="font-size: 13px;line-height: 1.5em;text-align: left;">您好 {{$email_user ?? ''}},</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    Plm系统中以下Bug（共{{$size}}个）已经超过一周未进行处理，但相关责任人信息缺失或不能准确获取，故无法自动通知，请SQA自行跟踪！
</p>

@include('common.table', ['data' => $data])

@include('emails.footer')