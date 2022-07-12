<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Plm报告</title>
    <style>
        body{
            font-size: 13px;
        }
        body, h1, h2, table{
            font-family: 'Microsoft YaHei',Arial,sans-serif;
        }
        h1 {
            font-family: 'Microsoft YaHei',Arial,sans-serif;
            text-align: center;
        }
        h2 {
            font-family: 'Microsoft YaHei',Arial,sans-serif;
            text-align: left;
        }
        table, th, tr, td {
            border: 1px solid #000000;
        }
        table {
            text-align: center;
            border-collapse: collapse;
            font-size: 13px;
            line-height: 1.5em;
        }
        tr {
            page-break-before: always;
            page-break-after: always;
            page-break-inside: avoid;
        }
        p {
            margin: 5px 0;
        }
    </style>
</head>
<body style="max-width: 100%!important; margin: 0 auto;">
<div id="email-content">
<!--[if mso]>
<center>
    <table style="border: none;"><tr style="border: none"><td style="border: none; width: 100%">
<![endif]-->


<p style="font-size: 13px;line-height: 1.5em;text-align: left;">您好,</p>

@if(!empty($data['tbodys']))
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    以下 Tapd任务即将于2个工作日内到期，请待办人及时处理。如因开发延期的测试任务，请测试及时顺延预计结束时间。（*若任务与提及本人无关，请忽略!）
</p>

@include('common.table_unsafe', ['data' => $data])

    @if(!empty($no_data_tapd))
    <p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
        截至目前，TAPD项目“ {{$no_data_tapd}} ”暂无2天内到期任务，请知晓！
    </p>
    @endif

@else
<p style="font-size: 13px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    截至目前，TAPD项目“ {{$no_data_tapd}} ”暂无2天内到期任务，请知晓！
</p>
@endif

@include('emails.footer')
