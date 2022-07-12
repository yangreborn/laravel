<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Plm报告</title><style>
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
            width: 100%;
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
<body style="margin: 24px;">
    <h1>Plm Bug统计</h1>
    @if(!empty($summary))
        <h2>总结</h2>
        <div style="text-align: left; font-size: 14px;">{!! $summary !!}</div>
    @endif
    @foreach($content_to_show as $part)
        @include('emails.plm.'.$part)
    @endforeach
</body>
</html>