@include('emails.header')

<p style="font-size: 13px;line-height: 1.5em;text-align: left;">Hi All,</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    以下工具无法自动关联至相应流,原因大概率为相关Job长时间未构建,系统中没有相关流信息所致,如有数据跟踪需求,可以请相关工具负责人员手动触发构建,仍有疑问请反馈！
</p>

@include('common.table', ['data' => $data])

@include('emails.footer')