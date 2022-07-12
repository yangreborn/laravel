@include('emails.header')

<p style="font-size: 13px;line-height: 1.5em;text-align: left;">Hi All,</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    以下为过去一年内有Bug数据更新但没有作关联的Plm项目，请及时添加关联！
</p>

@include('common.table', ['data' => $data])

@include('emails.footer')