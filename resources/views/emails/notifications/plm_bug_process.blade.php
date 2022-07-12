@include('emails.header')

<p style="font-size: 13px;line-height: 1.5em;text-align: left;">您好,</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    Plm中以下Bug已经超过一周未进行处理，请及时跟进！（*若提及Bug与本人无关，请忽略！）
</p>

<div id="bug_process_detail">
    @include('common.table', ['data' => $data])
</div>

@include('emails.footer')