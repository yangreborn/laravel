@include('emails.header')

<p style="font-size: 13px;line-height: 1.5em;text-align: left;">您好 {{$email_user ?? ''}},</p>
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    Plm系统中以下Bug（共{{$size}}个）已经超过一周未进行处理，请及时跟进！（*若提及Bug与本人无关，请忽略！）
</p>

@include('common.table', ['data' => $data])

@include('emails.footer')