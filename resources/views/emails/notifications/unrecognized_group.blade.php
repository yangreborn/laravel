@include('emails.header')
<p>Hi All,</p>
@if(!empty($unrecognized))
<p>&nbsp;&nbsp;&nbsp;&nbsp;由于缺少用户或重名等原因，以下小组负责人未能自动与度量平台中用户关联，请各位认领小组并手动关联！</p>
@include('common.table', ['data' => $unrecognized])
@endif

<br>

@if(!empty($matched))
    <p>&nbsp;&nbsp;&nbsp;&nbsp;由于自动匹配为模糊匹配，成功匹配的小组负责人仍有可能存在错误，请各位抽空核对数据！</p>
    @include('common.table', ['data' => $matched])
@endif

@include('emails.footer')