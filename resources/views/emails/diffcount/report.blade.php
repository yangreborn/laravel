<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="zh">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>报告</title>
</head>
<body>
<table style="font-family: 'Microsoft YaHei',Arial,sans-serif;margin-left:8%;margin-right:8%;padding-left: 8%;padding-right: 8%; font-size: 13px; line-height: 1.5em;text-align: left;">
    <tr>
        <td>
            <h2 style="text-align: center">
                Diffcount代码行统计报告
            </h2>
        </td>
    </tr>
    @if(!empty($review_summary))
    <tr>
        <td>
            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                <tr>
                    <td width="50" align="left" valign="top">
                        <h3 style="color: #0000FF;">总结</h3>
                        <br/>
                    </td>
                    <td>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                            @if(!empty($review_summary))
                                <tr>
                                    <td width="20" align="left" valign="top">&bull;</td>
                                    <td>{!! $review_summary !!}</td>
                                </tr>
                            @endif
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
                    <td width="50" align="left" valign="top">
                        <h3 style="color: #0000FF;">详情</h3>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                        <div style="color: #ea4336;">&nbsp;&nbsp;&nbsp;&nbsp;本次统计时间区间为：{{$period['start_time']}}至{{$period['end_time']}}</div>
                        <div>
                          <div style="font-size: 14px;text-decoration: underline;">说明：</div>
                          
                            <li>代码行单位:LOC</li>
                            <li>统计的语言文件类型：C/C++、Java、Python、Perl、C#、SQL、XML、TCL/TK、vue、yml、html、css、json、properties、rc、mk、cmake、txt、sh、bat</li>
                            <li>未纳入本次统计提交详情，请查阅附件sheet页 “未统计提交详情”</li>
                            @if(!empty($week_data['groups']))
                                @foreach ($week_data['groups'] as $group => $str)
                                    <li><b>{{$group}}：</b>{{$str}}</li>
                                @endforeach
                            @endif
                          
                        </div>
                        </table>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">一、</td>
                                <td>
                                    <div style="font-weight: bold;font-size: 18px;">项目代码行统计情况</div>
                                    <br/>
                                    
                                    <table style="width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                        <tr style="background-color: #f0f2f5;">
                                            <th width="16%" style="border: 1px solid #000">项目名</th>
                                            <th width="16%" style="border: 1px solid #000">代码流名</th>
                                            <th width="7%" style="border: 1px solid #000">提交次数</th>
                                            <th width="7%" style="border: 1px solid #000">不统计提交次数</th>
                                            <th width="7%" style="border: 1px solid #000">增加行数</th>
                                            <th width="7%" style="border: 1px solid #000">修改行数</th>
                                            <th width="7%" style="border: 1px solid #000">删除行数</th>
                                            <th width="8%" style="border: 1px solid #000">变动注释行数</th>
                                            <th width="7%" style="border: 1px solid #000">变动空行</th>
                                            <th width="8%" style="border: 1px solid #000">变动非空非注释行</th>
                                        </tr>
                                        @foreach ($summary['p_detail'] as $project => $data)
                                        <tr>
                                            <td style="border: 1px solid #000;">{{$data['projectName']}}</td>
                                            <td style="border: 1px solid #000;">{{$project}}</td>
                                            <td style="border: 1px solid #000">{{$data['summary']['commit_num']}}</td>
                                            <td style="border: 1px solid #000">{{$data['summary']['invalid_num']}}</td>
                                            <td style="border: 1px solid #000">{{$data['summary']['add']}}</td>
                                            <td style="border: 1px solid #000">{{$data['summary']['mod']}}</td>
                                            <td style="border: 1px solid #000">{{$data['summary']['del']}}</td>
                                            <td style="border: 1px solid #000">{{$data['summary']['comment_change']}}</td>
                                            <td style="border: 1px solid #000">{{$data['summary']['blk_change']}}</td>
                                            <td style="border: 1px solid #000">{{$data['summary']['nbnc_line']}}</td>
                                        </tr>
                                        @endforeach
                                    </table>
                                    
                                </td>
                            </tr>
                        </table><br/>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">二、</td>
                                <td>
                                    <div style="font-weight: bold;font-size: 18px;">变动非空非注释行数趋势（按自然周统计）</div><br/>
                                    <table>
                                        <tr>
                                            <td>
                                                @if($is_preview)
                                                    <img style="display: block;" src="{{$week_trend_chart}}" alt="image">
                                                @else
                                                    <img style="display: block;" src="{{$message->embedData($week_trend_chart,'变动非空非注释行数周趋势')}}" alt="image">
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table><br/>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">三、</td>
                                <td>
                                    <div style="font-weight: bold;font-size: 18px;">提交人代码行统计情况</div><br/>
                                    <div>
                                      <div style="font-size: 14px;text-decoration: underline;">说明：</div>
                                      <ol>
                                        <li>提交人为 NULL,说明无提交</li>
                                        <li>增删改数据均为0,说明无代码文件提交</li>
                                      </ol>
                                    </div><br/>
                                    <table style="width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                        <tr style="background-color: #f0f2f5;">
                                            <th width="10%" style="border: 1px solid #000">提交人</th>
                                            <th width="18%" style="border: 1px solid #000">工程名</th>
                                            <th width="7%" style="border: 1px solid #000">提交次数</th>
                                            <th width="8%" style="border: 1px solid #000">不统计提交次数</th>
                                            <th width="7%" style="border: 1px solid #000">增加行数</th>
                                            <th width="7%" style="border: 1px solid #000">修改行数</th>
                                            <th width="7%" style="border: 1px solid #000">删除行数</th>
                                            <th width="8%" style="border: 1px solid #000">变动注释行数</th>
                                            <th width="8%" style="border: 1px solid #000">变动非空非注释行数</th>
                                        </tr>
                                        @foreach ($summary['p_detail'] as $project => $data)
                                            @foreach ($data['details'] as $person => $person_data)
                                            <tr>
                                                <td style="border: 1px solid #000;">{{$person}}</td>
                                                <td style="border: 1px solid #000;">{{$project}}</td>
                                                <td style="border: 1px solid #000">{{$person_data['commits']}}</td>
                                                <td style="border: 1px solid #000">{{$person_data['invalid']}}</td>
                                                <td style="border: 1px solid #000">{{$person_data['add']}}</td>
                                                <td style="border: 1px solid #000">{{$person_data['mod']}}</td>
                                                <td style="border: 1px solid #000">{{$person_data['del']}}</td>
                                                <td style="border: 1px solid #000">{{$person_data['comment_change']}}</td>
                                                <td style="border: 1px solid #000">{{$person_data['nbnc_line']}}</td>
                                            </tr>
                                            @endforeach
                                        @endforeach
                                    </table>
                                    <table>
                                        <tr>
                                            <td>
                                                @if($totalBarChart)
                                                    @if($is_preview)
                                                        <img style="display: block;" src="{{$totalBarChart}}" alt="image">
                                                    @else
                                                        <img style="display: block;" src="{{$message->embedData($totalBarChart,'变动非空非注释总行数')}}" alt="image">
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                    <table style="width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                        <tr style="background-color: #f0f2f5;">
                                            <th width="10%" style="border: 1px solid #000">提交人</th>
                                            <th width="7%" style="border: 1px solid #000">提交次数</th>
                                            <th width="8%" style="border: 1px solid #000">不统计提交次数</th>
                                            <th width="7%" style="border: 1px solid #000">增加行数</th>
                                            <th width="7%" style="border: 1px solid #000">修改行数</th>
                                            <th width="7%" style="border: 1px solid #000">删除行数</th>
                                            <th width="8%" style="border: 1px solid #000">变动注释行数</th>
                                            <th width="8%" style="border: 1px solid #000">变动非空非注释行数</th>
                                        </tr>
                                        @foreach ($summary['s_detail'] as $person => $person_data)
                                        <tr>
                                            <td style="border: 1px solid #000;">{{$person}}</td>
                                            <td style="border: 1px solid #000">{{$person_data['commits']}}</td>
                                            <td style="border: 1px solid #000">{{$person_data['invalid']}}</td>
                                            <td style="border: 1px solid #000">{{$person_data['add']}}</td>
                                            <td style="border: 1px solid #000">{{$person_data['mod']}}</td>
                                            <td style="border: 1px solid #000">{{$person_data['del']}}</td>
                                            <td style="border: 1px solid #000">{{$person_data['comment_change']}}</td>
                                            <td style="border: 1px solid #000">{{$person_data['nbnc_line']}}</td>
                                        </tr>
                                        @endforeach
                                    </table>
                                </td>
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