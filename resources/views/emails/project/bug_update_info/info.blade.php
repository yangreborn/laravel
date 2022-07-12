<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="zh">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>项目缺陷更新情况</title>
</head>
<body style="margin: 24px;">
<table style="width: 100%;font-family: 'Microsoft YaHei',Arial,Helvetica,sans-serif,'宋体'; font-size: 13px; line-height: 1.5em;">
    @if(!empty($not_updated_projects))
    <tr>
        <td><h3>项目缺陷更新情况</h3></td>
    </tr>
    <tr>
        <td><span style="color: red;">*注：以下已进入测试阶段项目在过去一周内未做Bug更新，请跟进！</span></td>
    </tr>
    <tr>
        <td>
            <ul>
                @foreach($not_updated_projects as $item)
                    <li>{{$item->name}}</li>
                @endforeach
            </ul>
        </td>
    </tr>
    @endif
</table>
</body>
</html>