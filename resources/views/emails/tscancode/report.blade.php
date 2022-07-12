<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="zh">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>周报</title>
</head>
<body style="margin: 24px;">
<table style="width: 100%;font-family: 'Microsoft YaHei',Arial,Helvetica,sans-serif,'宋体'; font-size: 13px; line-height: 1.5em;">
    <tr>
        <td><h2 style="text-align: center">TscanCode 报告</h2></td>
    </tr>
    <tr>
        <td><h3>概况</h3></td>
    </tr>
    <tr>
        <td>
            <ol>
                <li>
                    <strong>本次统计的是截止至{{$deadline}}的tscancode静态检查结果！</strong>
                    @if(!empty($origin['git'])||!empty($origin['svn']))
                        <br/>数据来源请参考：
                        @if(!empty($origin['git']))
                            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; font-size: 13px; line-height: 1.5em;">
                                @foreach($origin['git'] as $origin_git_item)
                                    @if ($loop->first)
                                        <tr>
                                            <td rowspan={{$loop->count}} style="vertical-align:text-top;">git:</td>
                                            <td><a href="{{$origin_git_item}}">{{$origin_git_item}}</a></td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td><a href="{{$origin_git_item}}">{{$origin_git_item}}</a></td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>
                        @endif
                        @if(!empty($origin['svn']))
                            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; font-size: 13px; line-height: 1.5em;">
                                @foreach($origin['svn'] as $origin_svn_item)
                                    @if ($loop->first)
                                        <tr>
                                            <td rowspan={{$loop->count}} style="vertical-align:text-top;">svn:</td>
                                            <td><a href="{{$origin_svn_item}}">{{$origin_svn_item}}</a></td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td><a href="{{$origin_svn_item}}">{{$origin_svn_item}}</a></td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>
                        @endif
                    @endif
                </li>
                @if(!empty($overview['git']['nullpointer_top']||!empty($overview['git']['bufoverrun_top'])||!empty($overview['git']['memleak_top'])||!empty($overview['git']['compute_top'])||!empty($overview['git']['logic_top'])||!empty($overview['git']['suspicious_top'])||!empty($overview['git']['summary_warning_top'])))
                    <li>
                    <strong>GIT仓库代码概况（TOP3）</strong>
                    <ol type="a">
                        <li>
                            <strong>空指针</strong>：
                            <ul>
                                <li>
                                    目前空指针遗留最多的流是：
                                    @if(!empty($overview['git']['nullpointer_top']))
                                        @foreach($overview['git']['nullpointer_top'] as $git_nullpointer_top)
                                            @if($loop->last)
                                                {{$git_nullpointer_top['name']}}<strong>({{$git_nullpointer_top['value']}})</strong>
                                            @else
                                                {{$git_nullpointer_top['name']}}<strong>({{$git_nullpointer_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>内存溢出</strong>：
                            <ul>
                                <li>
                                    目前内存溢出遗留最多的流是：
                                    @if(!empty($overview['git']['bufoverrun_top']))
                                        @foreach($overview['git']['bufoverrun_top'] as $git_bufoverrun_top)
                                            @if($loop->last)
                                                {{$git_bufoverrun_top['name']}}<strong>({{$git_bufoverrun_top['value']}})</strong>
                                            @else
                                                {{$git_bufoverrun_top['name']}}<strong>({{$git_bufoverrun_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>内存泄露</strong>：
                            <ul>
                                <li>
                                    目前内存泄露遗留最多的流是：
                                    @if(!empty($overview['git']['memleak_top']))
                                        @foreach($overview['git']['memleak_top'] as $git_memleak_top)
                                            @if($loop->last)
                                                {{$git_memleak_top['name']}}<strong>({{$git_memleak_top['value']}})</strong>
                                            @else
                                                {{$git_memleak_top['name']}}<strong>({{$git_memleak_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>计算错误</strong>：
                            <ul>
                                <li>
                                    目前计算错误遗留最多的流是：
                                    @if(!empty($overview['git']['compute_top']))
                                        @foreach($overview['git']['compute_top'] as $git_compute_top)
                                            @if($loop->last)
                                                {{$git_compute_top['name']}}<strong>({{$git_compute_top['value']}})</strong>
                                            @else
                                                {{$git_compute_top['name']}}<strong>({{$git_compute_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>逻辑错误</strong>：
                            <ul>
                                <li>
                                    目前逻辑错误遗留最多的流是：
                                    @if(!empty($overview['git']['logic_top']))
                                        @foreach($overview['git']['logic_top'] as $git_logic_top)
                                            @if($loop->last)
                                                {{$git_logic_top['name']}}<strong>({{$git_logic_top['value']}})</strong>
                                            @else
                                                {{$git_logic_top['name']}}<strong>({{$git_logic_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>可疑代码</strong>：
                            <ul>
                                <li>
                                    目前可疑代码遗留最多的流是：
                                    @if(!empty($overview['git']['suspicious_top']))
                                        @foreach($overview['git']['suspicious_top'] as $git_suspicious_top)
                                            @if($loop->last)
                                                {{$git_suspicious_top['name']}}<strong>({{$git_suspicious_top['value']}})</strong>
                                            @else
                                                {{$git_suspicious_top['name']}}<strong>({{$git_suspicious_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>异常总数</strong>：
                            <ul>
                                <li>
                                    目前异常遗留最多的流是：
                                    @if(!empty($overview['git']['summary_warning_top']))
                                        @foreach($overview['git']['summary_warning_top'] as $git_summary_warning_top)
                                            @if($loop->last)
                                                {{$git_summary_warning_top['name']}}<strong>({{$git_summary_warning_top['value']}})</strong>
                                            @else
                                                {{$git_summary_warning_top['name']}}<strong>({{$git_summary_warning_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                                <li>
                                    较上次统计改善的最多的流是：
                                    <span style="color: green">
                                        @if(!empty($overview['git']['summary_warning_decrease_top']))
                                            @foreach($overview['git']['summary_warning_decrease_top'] as $git_summary_warning_decrease_top)
                                                @if($loop->last)
                                                    {{$git_summary_warning_decrease_top['name']}}<strong>(↓{{abs($git_summary_warning_decrease_top['value'])}})</strong>
                                                @else
                                                    {{$git_summary_warning_decrease_top['name']}}<strong>(↓{{abs($git_summary_warning_decrease_top['value'])}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                                <li>
                                    较上次统计上升最多的流是：
                                    <span style="color: red">
                                        @if(!empty($overview['git']['summary_warning_increase_top']))
                                            @foreach($overview['git']['summary_warning_increase_top'] as $git_summary_warning_increase_top)
                                                @if($loop->last)
                                                    {{$git_summary_warning_increase_top['name']}}<strong>(↑{{$git_summary_warning_increase_top['value']}})</strong>
                                                @else
                                                    {{$git_summary_warning_increase_top['name']}}<strong>(↑{{$git_summary_warning_increase_top['value']}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                            </ul>
                        </li>
                    </ol>
                </li>
                @endif
                @if(!empty($overview['svn']['nullpointer_top']||!empty($overview['svn']['bufoverrun_top'])||!empty($overview['svn']['memleak_top'])||!empty($overview['svn']['compute_top'])||!empty($overview['svn']['logic_top'])||!empty($overview['svn']['suspicious_top'])||!empty($overview['svn']['summary_warning_top'])))
                    <li>
                    <strong>SVN仓库代码概况（TOP3）</strong>
                    <ol type="a">
                        <li>
                            <strong>空指针</strong>：
                            <ul>
                                <li>
                                    目前空指针遗留最多的流是：
                                    @if(!empty($overview['svn']['nullpointer_top']))
                                        @foreach($overview['svn']['nullpointer_top'] as $svn_nullpointer_top)
                                            @if($loop->last)
                                                {{$svn_nullpointer_top['name']}}<strong>({{$svn_nullpointer_top['value']}})</strong>
                                            @else
                                                {{$svn_nullpointer_top['name']}}<strong>({{$svn_nullpointer_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>内存溢出</strong>：
                            <ul>
                                <li>
                                    目前内存溢出遗留最多的流是：
                                    @if(!empty($overview['svn']['bufoverrun_top']))
                                        @foreach($overview['svn']['bufoverrun_top'] as $svn_bufoverrun_top)
                                            @if($loop->last)
                                                {{$svn_bufoverrun_top['name']}}<strong>({{$svn_bufoverrun_top['value']}})</strong>
                                            @else
                                                {{$svn_bufoverrun_top['name']}}<strong>({{$svn_bufoverrun_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>内存泄露</strong>：
                            <ul>
                                <li>
                                    目前内存泄露遗留最多的流是：
                                    @if(!empty($overview['svn']['memleak_top']))
                                        @foreach($overview['svn']['memleak_top'] as $svn_memleak_top)
                                            @if($loop->last)
                                                {{$svn_memleak_top['name']}}<strong>({{$svn_memleak_top['value']}})</strong>
                                            @else
                                                {{$svn_memleak_top['name']}}<strong>({{$svn_memleak_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>计算错误</strong>：
                            <ul>
                                <li>
                                    目前计算错误遗留最多的流是：
                                    @if(!empty($overview['svn']['compute_top']))
                                        @foreach($overview['svn']['compute_top'] as $svn_compute_top)
                                            @if($loop->last)
                                                {{$svn_compute_top['name']}}<strong>({{$svn_compute_top['value']}})</strong>
                                            @else
                                                {{$svn_compute_top['name']}}<strong>({{$svn_compute_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>逻辑错误</strong>：
                            <ul>
                                <li>
                                    目前逻辑错误遗留最多的流是：
                                    @if(!empty($overview['svn']['logic_top']))
                                        @foreach($overview['svn']['logic_top'] as $svn_logic_top)
                                            @if($loop->last)
                                                {{$svn_logic_top['name']}}<strong>({{$svn_logic_top['value']}})</strong>
                                            @else
                                                {{$svn_logic_top['name']}}<strong>({{$svn_logic_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>可疑代码</strong>：
                            <ul>
                                <li>
                                    目前可疑代码遗留最多的流是：
                                    @if(!empty($overview['svn']['suspicious_top']))
                                        @foreach($overview['svn']['suspicious_top'] as $svn_suspicious_top)
                                            @if($loop->last)
                                                {{$svn_suspicious_top['name']}}<strong>({{$svn_suspicious_top['value']}})</strong>
                                            @else
                                                {{$svn_suspicious_top['name']}}<strong>({{$svn_suspicious_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>异常总数</strong>：
                            <ul>
                                <li>
                                    目前异常遗留最多的流是：
                                    @if(!empty($overview['svn']['summary_warning_top']))
                                        @foreach($overview['svn']['summary_warning_top'] as $svn_summary_warning_top)
                                            @if($loop->last)
                                                {{$svn_summary_warning_top['name']}}<strong>({{$svn_summary_warning_top['value']}})</strong>
                                            @else
                                                {{$svn_summary_warning_top['name']}}<strong>({{$svn_summary_warning_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                                <li>
                                    较上次统计改善的最多的流是：
                                    <span style="color: green">
                                        @if(!empty($overview['svn']['summary_warning_decrease_top']))
                                            @foreach($overview['svn']['summary_warning_decrease_top'] as $svn_summary_warning_decrease_top)
                                                @if($loop->last)
                                                    {{$svn_summary_warning_decrease_top['name']}}<strong>(↓{{abs($svn_summary_warning_decrease_top['value'])}})</strong>
                                                @else
                                                    {{$svn_summary_warning_decrease_top['name']}}<strong>(↓{{abs($svn_summary_warning_decrease_top['value'])}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                                <li>
                                    较上次统计上升最多的流是：
                                    <span style="color: red">
                                        @if(!empty($overview['svn']['summary_warning_increase_top']))
                                            @foreach($overview['svn']['summary_warning_increase_top'] as $svn_summary_warning_increase_top)
                                                @if($loop->last)
                                                    {{$svn_summary_warning_increase_top['name']}}<strong>(↑{{$svn_summary_warning_increase_top['value']}})</strong>
                                                @else
                                                    {{$svn_summary_warning_increase_top['name']}}<strong>(↑{{$svn_summary_warning_increase_top['value']}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                            </ul>
                        </li>
                    </ol>
                </li>
                @endif
                @if(!empty($summary))
                <li>
                    <strong>综上所述，统计结果说明：</strong><br/>
                    {!! $summary !!}
                </li>
                @endif
                <li>
                    <strong>错误清理样例参考：</strong>
                    <a href="https://github.com/Tencent/TscanCode/tree/master/samples/cpp">https://github.com/Tencent/TscanCode/tree/master/samples/cpp</a>
                </li>
            </ol>
        </td>
    </tr>
    <tr>
        <td><h3>详情</h3></td>
    </tr>
    <tr>
        <td>
            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
                <tr style="background-color: #92D050;">
                    <th width="16%">平台/代码库</th>
                    <th width="10%">空指针</th>
                    <th width="10%">内存溢出</th>
                    <th width="10%">内存泄漏</th>
                    <th width="10%">计算错误</th>
                    <th width="10%">逻辑错误</th>
                    <th width="10%">可疑代码</th>
                    <th width="10%">异常总数</th>
                    <th width="37%">异常总数趋势(间隔:一周)</th>
                </tr>
                    @foreach ($summary_warning_data as $item)
                    <tr style="background-color: {{$item['version_tool'] == 1 ? '#E4DFEC' : '#C5D9F1'}}">
                        <td>{!! $item['job_url'] !!}</td>
                        @if ($item['nullpointer_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['nullpointer']}}
                                    (↑{{$item['nullpointer_change']}})
                                </span>
                            </td>
                        @elseif ($item['nullpointer_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['nullpointer']}}
                                    (↓{{abs($item['nullpointer_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['nullpointer']}}
                                (→)
                            </td>
                        @endif
                        @if ($item['bufoverrun_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['bufoverrun']}}
                                    (↑{{$item['bufoverrun_change']}})
                                </span>
                            </td>
                        @elseif ($item['bufoverrun_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['bufoverrun']}}
                                    (↓{{abs($item['bufoverrun_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['bufoverrun']}}
                                (→)
                            </td>
                        @endif
                        @if ($item['memleak_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['memleak']}}
                                    (↑{{$item['memleak_change']}})
                                </span>
                            </td>
                        @elseif ($item['memleak_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['memleak']}}
                                    (↓{{abs($item['memleak_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['memleak']}}
                                (→)
                            </td>
                        @endif
                        @if ($item['compute_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['compute']}}
                                    (↑{{$item['compute_change']}})
                                </span>
                            </td>
                        @elseif ($item['compute_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['compute']}}
                                    (↓{{abs($item['compute_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['compute']}}
                                (→)
                            </td>
                        @endif
                        @if ($item['logic_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['logic']}}
                                    (↑{{$item['logic_change']}})
                                </span>
                            </td>
                        @elseif ($item['logic_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['logic']}}
                                    (↓{{abs($item['logic_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['logic']}}
                                (→)
                            </td>
                        @endif
                        @if ($item['suspicious_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['suspicious']}}
                                    (↑{{$item['suspicious_change']}})
                                </span>
                            </td>
                        @elseif ($item['suspicious_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['suspicious']}}
                                    (↓{{abs($item['suspicious_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['suspicious']}}
                                (→)
                            </td>
                        @endif
                        @if ($item['summary_warning_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['summary_warning']}}
                                    (↑{{$item['summary_warning_change']}})
                                </span>
                            </td>
                        @elseif ($item['summary_warning_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['summary_warning']}}
                                    (↓{{abs($item['summary_warning_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['summary_warning']}}
                                (→)
                            </td>
                        @endif
                        <td>
                            @if($is_preview)
                                <img src="{{$item['summary_warning_image']}}">
                            @else
                                <img src="{{$message->embedData($item['summary_warning_image'], 'image')}}">
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
</body>
</html>