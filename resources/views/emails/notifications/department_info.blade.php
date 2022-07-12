@include('emails.header')

<p style="font-size: 13px;line-height: 1.5em;text-align: left;">Hi All,</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    以下为度量平台中当前各部门名称，如有变更请邮件至{{config('api.dev_email')}}！
</p>

@include('common.table', ['data' => $data])

@include('emails.footer')