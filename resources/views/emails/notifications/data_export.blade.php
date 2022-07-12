@include('emails.header')

<p style="font-size: 13px;line-height: 1.5em;text-align: left;">您好</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    以下为已选项目所关联的静态检查数据(截止 {{$data["deadline"]}})
</p>

@include('common.table', ['data' => $data])

@include('emails.footer')