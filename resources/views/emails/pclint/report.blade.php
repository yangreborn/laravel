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
        <td><h2 style="text-align: center">PC-Lint 报告</h2></td>
    </tr>
    <tr>
        <td><h3>概况</h3></td>
    </tr>
    <tr>
        <td>
            <ol>
                <li>
                    <strong>本次统计的是截止至{{$deadline}}的Pclint静态检查结果！</strong>
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
                @if(!empty($overview['git']['error_top']||!empty($overview['git']['warning_top'])||!empty($overview['git']['color_warning_top'])))
                    <li>
                    <strong>GIT仓库代码概况（只显示TOP3数据）</strong>
                    <ol type="a">
                        <li>
                            <strong>Error</strong>：
                            <ul>
                                <li>
                                    目前Error遗留最多的流是：
                                    @if(!empty($overview['git']['error_top']))
                                        @foreach($overview['git']['error_top'] as $git_error_top)
                                            @if($loop->last)
                                                {{$git_error_top['name']}}<strong>({{$git_error_top['value']}})</strong>
                                            @else
                                                {{$git_error_top['name']}}<strong>({{$git_error_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                                <li>
                                    较上次统计改善的最多的流是：
                                    <span style="color: green;">
                                        @if(!empty($overview['git']['error_decrease_top']))
                                            @foreach($overview['git']['error_decrease_top'] as $git_error_decrease_top)
                                                @if($loop->last)
                                                    {{$git_error_decrease_top['name']}}<strong>(↓{{abs($git_error_decrease_top['value'])}})</strong>
                                                @else
                                                    {{$git_error_decrease_top['name']}}<strong>(↓{{abs($git_error_decrease_top['value'])}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                                <li>
                                    较上次统计上升最多的流是：
                                    <span style="color: #f00">
                                        @if(!empty($overview['git']['error_increase_top']))
                                            @foreach($overview['git']['error_increase_top'] as $git_error_increase_top)
                                                @if($loop->last)
                                                    {{$git_error_increase_top['name']}}<strong>(↑{{$git_error_increase_top['value']}})</strong>
                                                @else
                                                    {{$git_error_increase_top['name']}}<strong>(↑{{$git_error_increase_top['value']}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>标色告警</strong>：
                            <ul>
                                <li>
                                    目前标色告警遗留最多的流是：
                                    @if(!empty($overview['git']['color_warning_top']))
                                        @foreach($overview['git']['color_warning_top'] as $git_color_warning_top)
                                            @if($loop->last)
                                                {{$git_color_warning_top['name']}}<strong>({{$git_color_warning_top['value']}})</strong>
                                            @else
                                                {{$git_color_warning_top['name']}}<strong>({{$git_color_warning_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                                <li>
                                    较上次统计改善的最多的流是：
                                    <span style="color: green">
                                        @if(!empty($overview['git']['color_warning_decrease_top']))
                                            @foreach($overview['git']['color_warning_decrease_top'] as $git_color_warning_decrease_top)
                                                @if($loop->last)
                                                    {{$git_color_warning_decrease_top['name']}}<strong>(↓{{abs($git_color_warning_decrease_top['value'])}})</strong>
                                                @else
                                                    {{$git_color_warning_decrease_top['name']}}<strong>(↓{{abs($git_color_warning_decrease_top['value'])}})</strong>,&nbsp;
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
                                        @if(!empty($overview['git']['color_warning_increase_top']))
                                            @foreach($overview['git']['color_warning_increase_top'] as $git_color_warning_increase_top)
                                                @if($loop->last)
                                                    {{$git_color_warning_increase_top['name']}}<strong>(↑{{$git_color_warning_increase_top['value']}})</strong>
                                                @else
                                                    {{$git_color_warning_increase_top['name']}}<strong>(↑{{$git_color_warning_increase_top['value']}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>告警</strong>：
                            <ul>
                                <li>
                                    目前告警遗留最多的流是：
                                    @if(!empty($overview['git']['warning_top']))
                                        @foreach($overview['git']['warning_top'] as $git_warning_top)
                                            @if($loop->last)
                                                {{$git_warning_top['name']}}<strong>({{$git_warning_top['value']}})</strong>
                                            @else
                                                {{$git_warning_top['name']}}<strong>({{$git_warning_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                                <li>
                                    较上次统计改善的最多的流是：
                                    <span style="color: green">
                                        @if(!empty($overview['git']['warning_decrease_top']))
                                            @foreach($overview['git']['warning_decrease_top'] as $git_warning_decrease_top)
                                                @if($loop->last)
                                                    {{$git_warning_decrease_top['name']}}<strong>(↓{{abs($git_warning_decrease_top['value'])}})</strong>
                                                @else
                                                    {{$git_warning_decrease_top['name']}}<strong>(↓{{abs($git_warning_decrease_top['value'])}})</strong>,&nbsp;
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
                                        @if(!empty($overview['git']['warning_increase_top']))
                                            @foreach($overview['git']['warning_increase_top'] as $git_warning_increase_top)
                                                @if($loop->last)
                                                    {{$git_warning_increase_top['name']}}<strong>(↑{{$git_warning_increase_top['value']}})</strong>
                                                @else
                                                    {{$git_warning_increase_top['name']}}<strong>(↑{{$git_warning_increase_top['value']}})</strong>,&nbsp;
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
                @if(!empty($overview['svn']['error_top']||!empty($overview['svn']['warning_top'])||!empty($overview['svn']['color_warning_top'])))
                    <li>
                    <strong>SVN仓库代码概况（只显示TOP3数据）</strong>
                    <ol type="a">
                        <li>
                            <strong>Error</strong>：
                            <ul>
                                <li>
                                    目前Error遗留最多的流是：
                                    @if(!empty($overview['svn']['error_top']))
                                        @foreach($overview['svn']['error_top'] as $svn_error_top)
                                            @if($loop->last)
                                                {{$svn_error_top['name']}}<strong>({{$svn_error_top['value']}})</strong>
                                            @else
                                                {{$svn_error_top['name']}}<strong>({{$svn_error_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                                <li>
                                    较上次统计改善的最多的流是：
                                    <span style="color: green">
                                        @if(!empty($overview['svn']['error_decrease_top']))
                                            @foreach($overview['svn']['error_decrease_top'] as $svn_error_decrease_top)
                                                @if($loop->last)
                                                    {{$svn_error_decrease_top['name']}}<strong>(↓{{abs($svn_error_decrease_top['value'])}})</strong>
                                                @else
                                                    {{$svn_error_decrease_top['name']}}<strong>(↓{{abs($svn_error_decrease_top['value'])}})</strong>,&nbsp;
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
                                        @if(!empty($overview['svn']['error_increase_top']))
                                            @foreach($overview['svn']['error_increase_top'] as $svn_error_increase_top)
                                                @if($loop->last)
                                                    {{$svn_error_increase_top['name']}}<strong>(↑{{$svn_error_increase_top['value']}})</strong>
                                                @else
                                                    {{$svn_error_increase_top['name']}}<strong>(↑{{$svn_error_increase_top['value']}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>标色告警</strong>：
                            <ul>
                                <li>
                                    目前标色告警遗留最多的流是：
                                    @if(!empty($overview['svn']['color_warning_top']))
                                        @foreach($overview['svn']['color_warning_top'] as $svn_color_warning_top)
                                            @if($loop->last)
                                                {{$svn_color_warning_top['name']}}<strong>({{$svn_color_warning_top['value']}})</strong>
                                            @else
                                                {{$svn_color_warning_top['name']}}<strong>({{$svn_color_warning_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                                <li>
                                    较上次统计改善的最多的流是：
                                    <span style="color: green">
                                        @if(!empty($overview['svn']['color_warning_decrease_top']))
                                            @foreach($overview['svn']['color_warning_decrease_top'] as $svn_color_warning_decrease_top)
                                                @if($loop->last)
                                                    {{$svn_color_warning_decrease_top['name']}}<strong>(↓{{abs($svn_color_warning_decrease_top['value'])}})</strong>
                                                @else
                                                    {{$svn_color_warning_decrease_top['name']}}<strong>(↓{{abs($svn_color_warning_decrease_top['value'])}})</strong>,&nbsp;
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
                                        @if(!empty($overview['svn']['color_warning_increase_top']))
                                            @foreach($overview['svn']['color_warning_increase_top'] as $svn_color_warning_increase_top)
                                                @if($loop->last)
                                                    {{$svn_color_warning_increase_top['name']}}<strong>(↑{{$svn_color_warning_increase_top['value']}})</strong>
                                                @else
                                                    {{$svn_color_warning_increase_top['name']}}<strong>(↑{{$svn_color_warning_increase_top['value']}})</strong>,&nbsp;
                                                @endif
                                            @endforeach
                                        @else
                                            无
                                        @endif
                                    </span>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <strong>告警</strong>：
                            <ul>
                                <li>
                                    目前告警遗留最多的流是：
                                    @if(!empty($overview['svn']['warning_top']))
                                        @foreach($overview['svn']['warning_top'] as $svn_warning_top)
                                            @if($loop->last)
                                                {{$svn_warning_top['name']}}<strong>({{$svn_warning_top['value']}})</strong>
                                            @else
                                                {{$svn_warning_top['name']}}<strong>({{$svn_warning_top['value']}})</strong>,&nbsp;
                                            @endif
                                        @endforeach
                                    @else
                                        无
                                    @endif
                                </li>
                                <li>
                                    较上次统计改善的最多的流是：
                                    <span style="color: green">
                                        @if(!empty($overview['svn']['warning_decrease_top']))
                                            @foreach($overview['svn']['warning_decrease_top'] as $svn_warning_decrease_top)
                                                @if($loop->last)
                                                    {{$svn_warning_decrease_top['name']}}<strong>(↓{{abs($svn_warning_decrease_top['value'])}})</strong>
                                                @else
                                                    {{$svn_warning_decrease_top['name']}}<strong>(↓{{abs($svn_warning_decrease_top['value'])}})</strong>,&nbsp;
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
                                        @if(!empty($overview['svn']['warning_increase_top']))
                                            @foreach($overview['svn']['warning_increase_top'] as $svn_warning_increase_top)
                                                @if($loop->last)
                                                    {{$svn_warning_increase_top['name']}}<strong>(↑{{$svn_warning_increase_top['value']}})</strong>
                                                @else
                                                    {{$svn_warning_increase_top['name']}}<strong>(↑{{$svn_warning_increase_top['value']}})</strong>,&nbsp;
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
                    <a href="http://172.16.1.143/index.php?category-view-30" target="_blank">http://172.16.1.143/index.php?category-view-30</a>
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
                    <th width="16%">平台/SVN流</th>
                    <th width="10%">error总数</th>
                    <th width="37%">error总趋势</th>
                    <th width="37%">备注(各组件error数量及趋势，均为TOP3数据)</th>
                </tr>
                @foreach ($error_data as $item)
                    <tr style="background-color: {{$item['version_tool'] == 1 ? '#E4DFEC' : '#C5D9F1'}}">
                        <td>{!! $item['job_name'] !!}</td>
                        @if ($item['error_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['error']}}
                                    (↑{{$item['error_change']}})
                                </span>
                            </td>
                        @elseif ($item['error_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['error']}}
                                    (↓{{abs($item['error_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['error']}}
                                (→)
                            </td>
                        @endif
                        <td>
                            @if($is_preview)
                            <img src="{{$item['error_image']}}">
                            @else
                            <img src="{{$message->embedData($item['error_image'], 'image')}}">
                            @endif
                        </td>
                        <td style="font-size: 13px; text-align: left;">
                            @if(
                                !empty($item['error_top'])||
                                !empty($item['error_increase_top'])||
                                !empty($item['error_decrease_top'])
                            )
                                <div>
                                    @foreach($item['error_top'] as $error_top_item )
                                        @if($loop->last)
                                            {{$error_top_item['name']}}<strong>({{$error_top_item['value']}})</strong>
                                        @else
                                            {{$error_top_item['name']}}<strong>({{$error_top_item['value']}})</strong>,&nbsp;
                                        @endif
                                    @endforeach
                                </div>
                                <div style="color: red">
                                    @foreach($item['error_increase_top'] as $error_increase_top_item )
                                        @if($loop->last)
                                            {{$error_increase_top_item['name']}}<strong>(↑{{$error_increase_top_item['value']}})</strong>
                                        @else
                                            {{$error_increase_top_item['name']}}<strong>(↑{{$error_increase_top_item['value']}})</strong>,&nbsp;
                                        @endif
                                    @endforeach
                                </div>
                                <div style="color: green">
                                    @foreach($item['error_decrease_top'] as $error_decrease_top_item )
                                        @if($loop->last)
                                            {{$error_decrease_top_item['name']}}<strong>(↓{{abs($error_decrease_top_item['value'])}})</strong>
                                        @else
                                            {{$error_decrease_top_item['name']}}<strong>(↓{{abs($error_decrease_top_item['value'])}})</strong>,&nbsp;
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                无
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
                <tr style="background-color: #92D050;">
                    <th width="16%">平台/SVN流</th>
                    <th width="10%">标色告警总数</th>
                    <th width="37%">标色告警</th>
                    <th width="37%">备注(各组件标色告警数量及趋势，均为TOP3数据)</th>
                </tr>
                @foreach ($color_warning_data as $item)
                    <tr style="background-color: {{$item['version_tool'] == 1 ? '#E4DFEC' : '#C5D9F1'}}">
                        <td>{!! $item['job_name'] !!}</td>
                        @if ($item['color_warning_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['color_warning']}}
                                    (↑{{$item['color_warning_change']}})
                                </span>
                            </td>
                        @elseif ($item['color_warning_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['color_warning']}}
                                    (↓{{abs($item['color_warning_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['color_warning']}}
                                (→)
                            </td>
                        @endif
                        <td>
                            @if($is_preview)
                                <img src="{{$item['color_warning_image']}}">
                            @else
                                <img src="{{$message->embedData($item['color_warning_image'], 'image')}}">
                            @endif
                        </td>
                        <td style="font-size: 13px; text-align: left;">
                            @if(
                                !empty($item['color_warning_top'])||
                                !empty($item['color_warning_increase_top'])||
                                !empty($item['color_warning_decrease_top'])
                            )
                                <div>
                                    @foreach($item['color_warning_top'] as $color_warning_top_item )
                                        @if($loop->last)
                                            {{$color_warning_top_item['name']}}<strong>({{$color_warning_top_item['value']}})</strong>
                                        @else
                                            {{$color_warning_top_item['name']}}<strong>({{$color_warning_top_item['value']}})</strong>,&nbsp;
                                        @endif
                                    @endforeach
                                </div>
                                <div style="color: red">
                                    @foreach($item['color_warning_increase_top'] as $color_warning_increase_top_item )
                                        @if($loop->last)
                                            {{$color_warning_increase_top_item['name']}}<strong>(↑{{$color_warning_increase_top_item['value']}})</strong>
                                        @else
                                            {{$color_warning_increase_top_item['name']}}<strong>(↑{{$color_warning_increase_top_item['value']}})</strong>,&nbsp;
                                        @endif
                                    @endforeach
                                </div>
                                <div style="color: green">
                                    @foreach($item['color_warning_decrease_top'] as $color_warning_decrease_top_item )
                                        @if($loop->last)
                                            {{$color_warning_decrease_top_item['name']}}<strong>(↓{{abs($color_warning_decrease_top_item['value'])}})</strong>
                                        @else
                                            {{$color_warning_decrease_top_item['name']}}<strong>(↓{{abs($color_warning_decrease_top_item['value'])}})</strong>,&nbsp;
                                        @endif

                                    @endforeach
                                </div>
                            @else
                                无
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
                <tr style="background-color: #92D050;">
                    <th width="16%">平台/SVN流</th>
                    <th width="10%">告警总数</th>
                    <th width="37%">告警</th>
                    <th width="37%">备注(各组件告警数量及趋势，均为TOP3数据)</th>
                </tr>
                @foreach ($warning_data as $item)
                    <tr  style="background-color: {{$item['version_tool'] == 1 ? '#E4DFEC' : '#C5D9F1'}}">
                        <td>{!! $item['job_name'] !!}</td>
                        @if ($item['warning_change'] > 0)
                            <td>
                                <span style="color: red;">
                                    {{$item['warning']}}
                                    (↑{{$item['warning_change']}})
                                </span>
                            </td>
                        @elseif ($item['warning_change'] < 0)
                            <td>
                                <span style="color: green;">
                                    {{$item['warning']}}
                                    (↓{{abs($item['warning_change'])}})
                                </span>
                            </td>
                        @else
                            <td>
                                {{$item['warning']}}
                                (→)
                            </td>
                        @endif
                        <td>
                            @if($is_preview)
                                <img src="{{$item['warning_image']}}" alt="image">
                            @else
                                <img src="{{$message->embedData($item['warning_image'], 'image')}}" alt="image">
                            @endif
                        </td>
                        <td style="font-size: 13px; text-align: left;">
                            @if(
                                !empty($item['warning_top'])||
                                !empty($item['warning_increase_top'])||
                                !empty($item['warning_decrease_top'])
                            )
                                <div>
                                    @foreach($item['warning_top'] as $warning_top_item )
                                        @if($loop->last)
                                            {{$warning_top_item['name']}}<strong>({{$warning_top_item['value']}})</strong>
                                        @else
                                            {{$warning_top_item['name']}}<strong>({{$warning_top_item['value']}})</strong>,&nbsp;
                                        @endif
                                    @endforeach
                                </div>
                                <div style="color: red">
                                    @foreach($item['warning_increase_top'] as $warning_increase_top_item )
                                        @if($loop->last)
                                            {{$warning_increase_top_item['name']}}<strong>(↑{{$warning_increase_top_item['value']}})</strong>
                                        @else
                                            {{$warning_increase_top_item['name']}}<strong>(↑{{$warning_increase_top_item['value']}})</strong>,&nbsp;
                                        @endif
                                    @endforeach
                                </div>
                                <div style="color: green">
                                    @foreach($item['warning_decrease_top'] as $warning_decrease_top_item )
                                        @if($loop->last)
                                            {{$warning_decrease_top_item['name']}}<strong>(↓{{abs($warning_decrease_top_item['value'])}})</strong>
                                        @else
                                            {{$warning_decrease_top_item['name']}}<strong>(↓{{abs($warning_decrease_top_item['value'])}})</strong>,&nbsp;
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                无
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