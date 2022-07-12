@include('emails.header')
<p style="font-size: 13px;line-height: 1.5em;text-align: left;">您好,</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    以下为TAPD中外部需求与缺陷数据统计，请关注并及时跟进（<b>其中若有标色条目表示已经逾期，请重点关注</b>）！
</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    统计中需求涵盖的状态有'新增','规划中','重新打开'和'实现中'；缺陷涵盖的状态有'新','新增','重新打开','接收/处理','处理中','转交'。
</p>

@if(!empty($story_reponse_solve))
<div style="font-size: 13px;line-height: 1.5em;text-align: left;">
<br />
<b>需求延期响应、实现条目详情，</b>
@include('common.table_unsafe', ['data' => $story_reponse_solve])
</div>
@endif

@if(!empty($bug_reponse_solve))
<div style="font-size: 13px;line-height: 1.5em;text-align: left;">
<br />
<b>缺陷延期响应、解决条目详情，</b>
@include('common.table_unsafe', ['data' => $bug_reponse_solve])
</div>
@endif

@if(!empty($story_due_blank))
<div style="font-size: 13px;line-height: 1.5em;text-align: left;">
<br />
<b>需求解决期限为空条目详情，</b>
@include('common.table_unsafe', ['data' => $story_due_blank])
</div>
@endif

@if(!empty($bug_due_blank))
<div style="font-size: 13px;line-height: 1.5em;text-align: left;">
<br />
<b>缺陷解决期限为空条目详情，</b>
@include('common.table_unsafe', ['data' => $bug_due_blank])
</div>
@endif
    
@if(!empty($story_validate))
<div style="font-size: 13px;line-height: 1.5em;text-align: left;">
<br />
<b>需求延期验证、关闭条目详情，</b>
@include('common.table_unsafe', ['data' => $story_validate])
</div>
@endif

@if(!empty($bug_validate))
<div style="font-size: 13px;line-height: 1.5em;text-align: left;">
<br />
<b>缺陷延期验证、关闭条目详情，</b>
@include('common.table_unsafe', ['data' => $bug_validate])
</div>
@endif

@include('emails.footer')