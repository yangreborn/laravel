@include('emails.header')

<p style="font-size: 13px;line-height: 1.5em;text-align: left;">您好,</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">现对在研项目（<font color="#FF4435">已进入测试阶段或维护阶段</font>）的Bug未定期（<font color="#FF4435">两周</font>）提交进行告警提醒，以便各位及时了解测试Bug是否存在未提交等异常。
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    TAPD以及PLM以下项目已经超过两周未提交Bug，请查看！（*若提及Bug与本人无关，请忽略！）
</p>

<div><h2 style="text-align: center"><font size="3px" color="#0041D6">两周以上无Bug提交一览表（统计时间点：{{$data['deadline']}} ~ {{$data['now']}}）</font></h2></div>
<table width="100%" border="1" cellpadding="0" cellspacing="0" style="margin-bottom:5px">
    <thead>
    @foreach($data['theads'] as $thead)
        <tr>
            @foreach($thead as $child)
                <th style="@if(!empty($child['bg_color']))background-color: {{$child['bg_color']}};@endif @if($child['width'])width: {{$child['width']}}px;@endif"
                    @if($child['width'])
                    width="{{$child['width']}}"
                    @endif
                    @if($child['rowspan'])
                    rowspan="{{$child['rowspan']}}"
                    @endif
                    @if($child['colspan'])
                    colspan="{{$child['colspan']}}"
                    @endif
                >{{ $child['value']  }}</th>
            @endforeach
        </tr>
    @endforeach
    </thead>
    <tbody>
    @foreach($data['tbodys'] as $child)
        <tr>
            <td width="200px">{{$child['tool_project']}}</td>
            <td width="80px">{{$child['product_line']}}</td>
            <td width="100px">{{$child['department']}}</td>
            <td width="200px">{{$child['parent_project']}}</td>
            <td width="80px">{{$child['relative_type']}}</td>
            <td width="80px">{{$child['supervisor']}}</td>
            <td width="80px">{{$child['sqa']}}</td>
            <td width="80px">{{$child['last_modified']}}</td>
            @if($child['last_modified_status'] === "正常")
                <td width="80px"> {{$child['last_modified_status']}} </td>
            @else
                <td width="80px"><font color="#cf1322"> {{$child['last_modified_status']}} </font></td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>

<p style="font-size: 13px;line-height: 1.5em;text-align: left;"><font color="#FF4435">说明：</font></p>
<ol type="1">
<li>数据主要来源TAPD、PLM系统；</li>
<li>如两周以上未提交Bug，Bug提交存在异常，请项目组重点关注；</li>
<li>如无异常，可忽略本提醒，同时可根据需要，反馈无需邮件提醒；</li>
<li>如列表中信息有误（需新增、修改、删除），请及时与对应的SQA联系，谢谢！</li></ol>

@include('emails.footer')