<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="zh">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>报告</title>
</head>
<body>
<table style="width: 100%;font-family: 'Microsoft YaHei',Arial,sans-serif;padding-left: 4%;padding-right: 4%; font-size: 13px; line-height: 1.5em;text-align: left;">
    <tr>
        <td>
            <h2 style="text-align: center">
                    Gerrit工具使用报告
            </h2>
        </td>
    </tr>
    @if(!empty($report_summary))
    <tr>
        <td>
            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                <tr>
                    <td width="50" align="center" valign="top">
                        <h3>总结</h3>
                    </td>
                    <td>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                <tr>
                                    <td width="20" align="center" valign="top">&bull;</td>
                                    <td>{!! $report_summary !!}</td>
                                </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @endif
    <tr>
        <td>
            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                <tr>
                    <td width="50" align="center" valign="top">
                        <h3>详情</h3>
                    </td>
                    <td>
                        <div style="color: #ea4336;">本次统计时间区间为：{{$period['start_time']}}至{{$period['end_time']}}</div>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">一、</td>
                                <td width="50%">
                                    <div style="font-weight: bold;">项目代码行数据统计</div>
                                    @if(!empty($diffcount_datas))
                                            <table style="width: 90%;align= left; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                <tr style="background-color: #f0f2f5;">
                                                    <th style="border: 1px solid #000">提交人</th>
                                                    <th style="border: 1px solid #000">提交数</th>
                                                    <th  style="border: 1px solid #000">提交行数</th> 
                                                </tr>
                                                @foreach ($diffcount_datas as $diffcount_data)
                                                    <tr>
                                                        <td style="border: 1px solid #000;">{{$diffcount_data['author']}}</td>
                                                        <td style="border: 1px solid #000;">{{$diffcount_data['commits']}}</td>
                                                        <td style="border: 1px solid #000;">{{$diffcount_data['lines']}}</td>
                                                    </tr>
                                                @endforeach
                                                <tr style="background-color: #f0f2f5;">
                                                        <td style="border: 1px solid #000;">总计</td>
                                                        <td style="border: 1px solid #000;">{{$diffcount_sum['commits']}}</td>
                                                        <td style="border: 1px solid #000;">{{$diffcount_sum['lines']}}</td>         
                                                </tr>
                                            </table>
                                    @endif
                                </td>
                                <td width="50%"><img style="display: block;" src="{{$diffcount_chart}}" alt="image"></td>
                            </tr>
                        </table>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">二、</td>
                                <td width="50%">
                                    <div style="font-weight: bold;">项目评审数据统计</div>
                                    @if(!empty($phabricator_datas))
                                            <table style="width: 90%;align= left; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                <tr style="background-color: #f0f2f5;">
                                                    <th width="5%" style="border: 1px solid #000">提交人</th>
                                                    <th width="5%" style="border: 1px solid #000">评审处理数</th>
                                                    <th width="5%" style="border: 1px solid #000">评审意见数</th>
                                                    <th width="5%" style="border: 1px solid #000">及时处理数</th>
                                                </tr>
                                                @foreach ($phabricator_datas as $phabricator_data)
                                                    <tr>
                                                        <td style="border: 1px solid #000;">{{$phabricator_data['author']}}</td>
                                                        <td style="border: 1px solid #000;">{{$phabricator_data['deal']}}</td>
                                                        <td style="border: 1px solid #000;">{{$phabricator_data['comment']}}</td>
                                                        <td style="border: 1px solid #000;">{{$phabricator_data['in_time']}}</td>
                                                    </tr>
                                                @endforeach
                                                <tr style="background-color: #f0f2f5;">
                                                        <td style="border: 1px solid #000;">总计</td>
                                                        <td style="border: 1px solid #000;">{{$phabricator_sum['deal']}}</td>
                                                        <td style="border: 1px solid #000;">{{$phabricator_sum['comment']}}</td>
                                                        <td style="border: 1px solid #000;">{{$phabricator_sum['in_time']}}</td>
                                                </tr>
                                            </table>
                                    @endif
                                </td>
                                <td width="50%"><img style="display: block;" src="{{$phabricator_chart}}" alt="image"></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>