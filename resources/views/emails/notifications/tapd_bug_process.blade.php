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

@if(!empty($data['tbodys']))
<p style="font-size: 14px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    TAPD中以下缺陷已经超过一周未进行处理，请及时跟进！（*若提及缺陷与本人无关，请忽略！）
</p>
@include('common.table_unsafe', ['data' => $data])
@else
<p style="font-size: 14px;line-height: 1.5em;text-align: left;text-indent: 2em;">
    本次统计周期内，相关TAPD项目中无延期未处理缺陷，请知晓！
</p>
@endif

@include('emails.footer')
<br />
