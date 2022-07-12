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
                @if($review_tool_type === '1')
                    代码评审Phabricator使用报告
                @endif
                @if($review_tool_type === '2')
                    代码评审Gerrit使用报告
                @endif
                @if($review_tool_type === '3')
                    代码评审Gitlab使用报告
                @endif
            </h2>
        </td>
    </tr>
    @if(!empty($commit_summary)||!empty($review_summary))
    <tr>
        <td>
            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                <tr>
                    <td width="50" align="center" valign="top">
                        <h3>总结</h3>
                    </td>
                    <td>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                            @if(!empty($commit_summary))
                                <tr>
                                    <td width="20" align="center" valign="top">&bull;</td>
                                    <td>{!! $commit_summary !!}</td>
                                </tr>
                            @endif
                            @if(!empty($review_summary))
                                <tr>
                                    <td width="20" align="center" valign="top">&bull;</td>
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
                    <td width="50" align="center" valign="top">
                        <h3>详情</h3>
                    </td>
                    <td>
                        <div style="color: #ea4336;">本次统计时间区间为：{{$period['start_time']}}至{{$period['end_time']}}</div>
                        <div style="color: #ea4336;">代码评审统计的提交时间为评审创建时间（git push时间）,与diffcount统计的提交时间（git commit时间）可能有部分差异</div>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">一、</td>
                                <td>
                                    <div style="font-weight: bold;">项目评审数据统计</div>
                                    @if(!empty($project_review_rate))
                                        @if($validity)
                                            <table style="width: 90%;align= left; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                <tr style="background-color: #f0f2f5;">
                                                    <th width="29%" style="border: 1px solid #000">项目流名</th>
                                                    <th width="5%" style="border: 1px solid #000">总提交数</th>
                                                    <th width="5%" style="border: 1px solid #000">总评审创建数</th>
                                                    <th width="5%" style="border: 1px solid #000">总评审处理数</th>
                                                    <th width="5%" style="border: 1px solid #000">总评审处理有效数</th>
                                                    <th width="17%" style="border: 1px solid #000">代码评审覆盖率</th>
                                                    <th width="17%" style="border: 1px solid #000">代码评审及时率</th>
                                                    <th width="17%" style="border: 1px solid #000">代码评审有效率</th>
                                                </tr>
                                                @foreach ($project_review_rate as $review_rate_item)
                                                    <tr>
                                                        <td style="border: 1px solid #000;">{{$review_rate_item['job_name']}}</td>
                                                        <td style="border: 1px solid #000;">{{$review_rate_item['all_commits']}}</td>
                                                        <td style="border: 1px solid #000;">{{$review_rate_item['all_reviews']}}</td>
                                                        <td style="border: 1px solid #000;">{{$review_rate_item['all_deals']}}</td>
                                                        <td style="border: 1px solid #000;">{{$review_rate_item['all_valid']}}</td>
                                                        <td style="border: 1px solid #000;">
                                                            <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                <tr style="padding: 0;">
                                                                    @if($review_rate_item['review_rate'])
                                                                        <td
                                                                                width="{{$review_rate_item['review_rate']}}%"
                                                                                style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                        >
                                                                            {{$review_rate_item['review_rate']}}%
                                                                        </td>
                                                                        <td width="{{100 - $review_rate_item['review_rate']}}%" style="padding: 0;border: none;"></td>
                                                                    @else
                                                                        <td style="padding: 0;border: none;text-align: left;">
                                                                            {{$review_rate_item['review_rate']}}%
                                                                        </td>
                                                                    @endif
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <td style="border: 1px solid #000;">
                                                            <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                <tr style="padding: 0;">
                                                                    @if($review_rate_item['in_time_review_rate'])
                                                                        <td
                                                                                width="{{$review_rate_item['in_time_review_rate']}}%"
                                                                                style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                        >
                                                                            {{$review_rate_item['in_time_review_rate']}}%
                                                                        </td>
                                                                        <td width="{{100 - $review_rate_item['in_time_review_rate']}}%" style="padding: 0;border: none;"></td>
                                                                    @else
                                                                        <td style="text-align: left;border: none;padding: 0;">
                                                                            {{$review_rate_item['in_time_review_rate']}}%
                                                                        </td>
                                                                    @endif
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <td style="border: 1px solid #000;">
                                                            <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                <tr style="padding: 0;">
                                                                    @if($review_rate_item['valid_review_rate'])
                                                                        <td
                                                                                width="{{$review_rate_item['valid_review_rate']}}%"
                                                                                style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                        >
                                                                            {{$review_rate_item['valid_review_rate']}}%
                                                                        </td>
                                                                        <td width="{{100 - $review_rate_item['valid_review_rate']}}%" style="padding: 0;border: none;"></td>
                                                                    @else
                                                                        <td style="text-align: left;border: none;padding: 0;">
                                                                            {{$review_rate_item['valid_review_rate']}}%
                                                                        </td>
                                                                    @endif
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        @else
                                            <table style="width: 90%; align= left;text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                <tr style="background-color: #f0f2f5;">
                                                    <th width="35%" style="border: 1px solid #000">项目流名</th>
                                                    <th width="5%" style="border: 1px solid #000">总提交数</th>
                                                    <th width="5%" style="border: 1px solid #000">总评审创建数</th>
                                                    <th width="5%" style="border: 1px solid #000">总评审处理数</th>
                                                    <th width="25%" style="border: 1px solid #000">代码评审覆盖率</th>
                                                    <th width="25%" style="border: 1px solid #000">代码评审及时率</th>
                                                </tr>
                                                @foreach ($project_review_rate as $review_rate_item)
                                                    <tr>
                                                        <td style="border: 1px solid #000">{{$review_rate_item['job_name']}}</td>
                                                        <td style="border: 1px solid #000">{{$review_rate_item['all_commits']}}</td>
                                                        <td style="border: 1px solid #000">{{$review_rate_item['all_reviews']}}</td>
                                                        <td style="border: 1px solid #000">{{$review_rate_item['all_deals']}}</td>
                                                        <td style="border: 1px solid #000;text-align: left;">
                                                            <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                <tr style="padding: 0;">
                                                                    @if($review_rate_item['review_rate'])
                                                                        <td
                                                                                width="{{$review_rate_item['review_rate']}}%"
                                                                                style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                        >
                                                                            {{$review_rate_item['review_rate']}}%
                                                                        </td>
                                                                        <td width="{{100 - $review_rate_item['review_rate']}}%" style="padding: 0;border: none;"></td>
                                                                    @else
                                                                        <td style="padding: 0;border: none;text-align: left;">
                                                                            {{$review_rate_item['review_rate']}}%
                                                                        </td>
                                                                    @endif
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <td style="border: 1px solid #000;">
                                                            <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                <tr style="padding: 0;">
                                                                    @if($review_rate_item['in_time_review_rate'])
                                                                        <td
                                                                                width="{{$review_rate_item['in_time_review_rate']}}%"
                                                                                style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                        >
                                                                            {{$review_rate_item['in_time_review_rate']}}%
                                                                        </td>
                                                                        <td width="{{100 - $review_rate_item['in_time_review_rate']}}%" style="padding: 0;border: none;"></td>
                                                                    @else
                                                                        <td style="text-align: left;border: none;padding: 0;">
                                                                            {{$review_rate_item['in_time_review_rate']}}%
                                                                        </td>
                                                                    @endif
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">二、</td>
                                <td>
                                    <div style="font-weight: bold;">提交人评审数据统计</div>
                                    @if($review_tool_type === '1')
                                    <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 90%;align= left; text-align: center; border: 1px solid #000; border-collapse: collapse;font-size: 13px;">
                                        <tr style="background-color: #f0f2f5;">
                                            <th width="30%" style="border: 1px solid #000">项目流名</th>
                                            <th width="14%" style="border: 1px solid #000">提交人</th>
                                            <th width="8%" style="border: 1px solid #000">提交总数</th>
                                            <th width="8%" style="border: 1px solid #000">提交前评审创建数</th>
                                            <th width="8%" style="border: 1px solid #000">提交后评审创建数</th>
                                            <th width="8%" style="border: 1px solid #000">未评审提交数</th>
                                            <th width="8%" style="border: 1px solid #000">被驳回数</th>
                                            <th width="8%" style="border: 1px solid #000">提交前评审覆盖率</th>
                                            <th width="8%" style="border: 1px solid #000">提交后评审覆盖率</th>
                                        </tr>
                                        @foreach ($committer_review_rate as $committer_review_rate_key=>$committer_review_rate_item)
                                            @foreach($committer_review_rate_item as $committer_review_rate_value)
                                                @if($loop->first)
                                                    <tr>
                                                        <td rowspan="{{$loop->count}}" style="border: 1px solid #000">{{$committer_review_rate_key}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['author']}}</td>
                                                        <td style="border: 1px solid #000">
                                                            <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                <tr style="padding: 0;">
                                                                    @if($committer_review_rate_value['commits'] != 0)
                                                                        <td
                                                                                width="{{round(100*$committer_review_rate_value['commits']/$committer_review_max[$committer_review_rate_key])}}%"
                                                                                style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                        >
                                                                            {{$committer_review_rate_value['commits']}}
                                                                        </td>
                                                                        <td
                                                                                width="{{100-round(100*$committer_review_rate_value['commits']/$committer_review_max[$committer_review_rate_key])}}%"
                                                                                style="padding: 0;border: none;"
                                                                        >
                                                                        </td>
                                                                    @else
                                                                        <td style="text-align: left;">{{$committer_review_rate_value['commits']}}</td>
                                                                    @endif
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['diffs']}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['audits']}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['not_reviews']}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['rejects']}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['diffRate'] === 'N/A' ? 'N/A' : $committer_review_rate_value['diffRate'].'%'}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['auditRate'] === 'N/A' ? 'N/A' : $committer_review_rate_value['auditRate'].'%'}}</td>
                                                    </tr>
                                                @else
                                                    <tr>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['author']}}</td>
                                                        <td style="border: 1px solid #000">
                                                            <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                <tr style="padding: 0;">
                                                                    <td
                                                                            width="{{round(100*$committer_review_rate_value['commits']/$committer_review_max[$committer_review_rate_key])}}%"
                                                                            style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                    >
                                                                        {{$committer_review_rate_value['commits']}}
                                                                    </td>
                                                                    <td
                                                                            width="{{100-round(100*$committer_review_rate_value['commits']/$committer_review_max[$committer_review_rate_key])}}%"
                                                                            style="padding: 0;border: none;"
                                                                    >
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['diffs']}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['audits']}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['not_reviews']}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['rejects']}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['diffRate'] === 'N/A' ? 'N/A' : $committer_review_rate_value['diffRate'].'%'}}</td>
                                                        <td style="border: 1px solid #000">{{$committer_review_rate_value['auditRate'] === 'N/A' ? 'N/A' : $committer_review_rate_value['auditRate'].'%'}}</td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        @endforeach
                                    </table>
                                    @endif
                                    @if($review_tool_type === '2' || $review_tool_type === '3')
                                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 90%;align= left; text-align: center; border: 1px solid #000; border-collapse: collapse;font-size: 13px;">
                                            <tr style="background-color: #f0f2f5;">
                                                <th width="30%" style="border: 1px solid #000">项目流名</th>
                                                <th width="30%" style="border: 1px solid #000">提交人</th>
                                                <th width="20%" style="border: 1px solid #000">提交数</th>
                                                <th width="20%" style="border: 1px solid #000">被驳回数</th>
                                            </tr>
                                            @foreach ($committer_review_rate as $committer_review_rate_key=>$committer_review_rate_item)
                                                @foreach($committer_review_rate_item as $committer_review_rate_value)
                                                    @if($loop->first)
                                                        <tr>
                                                            <td rowspan="{{$loop->count}}" style="border: 1px solid #000">{{$committer_review_rate_key}}</td>
                                                            <td style="border: 1px solid #000">{{$committer_review_rate_value['author']}}</td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($committer_review_rate_value['commits'] != 0)
                                                                            <td
                                                                                    width="{{round(100*$committer_review_rate_value['commits']/$committer_review_max[$committer_review_rate_key])}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$committer_review_rate_value['commits']}}
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-round(100*$committer_review_rate_value['commits']/$committer_review_max[$committer_review_rate_key])}}%"
                                                                                    style="padding: 0;border: none;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$committer_review_rate_value['commits']}}</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="border: 1px solid #000">{{$committer_review_rate_value['rejects']}}</td>
                                                        </tr>
                                                    @else
                                                        <tr>
                                                            <td style="border: 1px solid #000">{{$committer_review_rate_value['author']}}</td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        <td
                                                                                width="{{round(100*$committer_review_rate_value['commits']/$committer_review_max[$committer_review_rate_key])}}%"
                                                                                style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                        >
                                                                            {{$committer_review_rate_value['commits']}}
                                                                        </td>
                                                                        <td
                                                                                width="{{100-round(100*$committer_review_rate_value['commits']/$committer_review_max[$committer_review_rate_key])}}%"
                                                                                style="padding: 0;border: none;"
                                                                        >
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="border: 1px solid #000">{{$committer_review_rate_value['rejects']}}</td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            @endforeach
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">三、</td>
                                <td name="reviewer">
                                    <div style="font-weight: bold;">评审人数据统计</div>
                                    @if($validity)
                                        <table style="width: 90%; align= left;text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                            <tr style="background-color: #f0f2f5;">
                                                <th width="30%" style="border: 1px solid #000">项目流名</th>
                                                <th width="14%" style="border: 1px solid #000">评审人</th>
                                                <th width="7%" style="border: 1px solid #000">收到评审数</th>
                                                <th width="7%" style="border: 1px solid #000">评审处理数</th>
                                                <th width="7%" style="border: 1px solid #000">及时处理数</th>
                                                <th width="7%" style="border: 1px solid #000">驳回数</th>
                                                <th width="7%" style="border: 1px solid #000">有效处理数</th>
                                                <th width="7%" style="border: 1px solid #000">评审处理率</th>
                                                <th width="7%" style="border: 1px solid #000">评审及时处理率</th>
                                                <th width="7%" style="border: 1px solid #000">评审有效处理率</th>
                                            </tr>
                                            @foreach ($reviewer_review_rate as $reviewer_review_rate_key => $reviewer_review_rate_item)
                                                @foreach($reviewer_review_rate_item as $reviewer_review_rate_value)
                                                    @if($loop->first)
                                                        <tr>
                                                            <td rowspan="{{$loop->count}}" style="border: 1px solid #000">{{$reviewer_review_rate_key}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['reviewer']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['receives']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['deals']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['in_time']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['rejects']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['valid']}}</td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewDealrate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewDealrate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewDealrate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewDealrate']}}%"
                                                                                    style="padding: 0;border: none;text-align: left;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewDealrate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewIntimerate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewIntimerate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewIntimerate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewIntimerate']}}%"
                                                                                    style="padding: 0;border: none;text-align: left;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewIntimerate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewValidrate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewValidrate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewValidrate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewValidrate']}}%"
                                                                                    style="padding: 0;border: none;text-align: left;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewValidrate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    @else
                                                        <tr>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['reviewer']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['receives']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['deals']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['in_time']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['rejects']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['valid']}}</td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewDealrate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewDealrate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewDealrate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewDealrate']}}%"
                                                                                    style="padding: 0;border: none;text-align: left;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewDealrate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewIntimerate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewIntimerate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewIntimerate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewIntimerate']}}%"
                                                                                    style="padding: 0;border: none;text-align: left;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewIntimerate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewValidrate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewValidrate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewValidrate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewValidrate']}}%"
                                                                                    style="padding: 0;border: none;text-align: left;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewValidrate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            @endforeach
                                        </table>
                                    @else
                                        <table style="width: 90%; align= left;text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px; line-height: 1.5em;">
                                            <tr style="background-color: #f0f2f5;">
                                                <th width="30%" style="border: 1px solid #000">项目流名</th>
                                                <th width="14%" style="border: 1px solid #000">评审人</th>
                                                <th width="6%" style="border: 1px solid #000">收到评审数</th>
                                                <th width="6%" style="border: 1px solid #000">评审处理数</th>
                                                <th width="6%" style="border: 1px solid #000">及时处理数</th>
                                                <th width="6%" style="border: 1px solid #000">驳回数</th>
                                                <th width="17%" style="border: 1px solid #000">评审处理率</th>
                                                <th width="17%" style="border: 1px solid #000">评审及时处理率</th>
                                            </tr>
                                            @foreach ($reviewer_review_rate as $reviewer_review_rate_key => $reviewer_review_rate_item)
                                                @foreach($reviewer_review_rate_item as $reviewer_review_rate_value)
                                                    @if($loop->first)
                                                        <tr>
                                                            <td rowspan="{{$loop->count}}" style="border: 1px solid #000">{{$reviewer_review_rate_key}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['reviewer']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['receives']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['deals']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['in_time']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['rejects']}}</td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewDealrate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewDealrate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewDealrate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewDealrate']}}%"
                                                                                    style="padding: 0;border: none;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewDealrate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewIntimerate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewIntimerate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewIntimerate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewIntimerate']}}%"
                                                                                    style="padding: 0;border: none;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewIntimerate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    @else
                                                        <tr>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['reviewer']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['receives']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['deals']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['in_time']}}</td>
                                                            <td style="border: 1px solid #000">{{$reviewer_review_rate_value['rejects']}}</td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewDealrate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewDealrate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewDealrate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewDealrate']}}%"
                                                                                    style="padding: 0;border: none;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewDealrate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="border: 1px solid #000">
                                                                <table width="100%" style="padding: 0; border-collapse: collapse; border: none;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
                                                                    <tr style="padding: 0;">
                                                                        @if($reviewer_review_rate_value['reviewIntimerate'] != 0)
                                                                            <td
                                                                                    width="{{$reviewer_review_rate_value['reviewIntimerate']}}%"
                                                                                    style="background-color: #4284f4;text-align: left;border: none;padding: 0;"
                                                                            >
                                                                                {{$reviewer_review_rate_value['reviewIntimerate']}}%
                                                                            </td>
                                                                            <td
                                                                                    width="{{100-$reviewer_review_rate_value['reviewIntimerate']}}%"
                                                                                    style="padding: 0;border: none;"
                                                                            >
                                                                            </td>
                                                                        @else
                                                                            <td style="text-align: left;">{{$reviewer_review_rate_value['reviewIntimerate']}}%</td>
                                                                        @endif
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            @endforeach
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                            <tr>
                                <td width="20" align="center" valign="top">四、</td>
                                <td name="detail">
                                    <div style="font-weight: bold;">项目评审处理率详细情况（趋势图统计方式为：按自然周统计) </div>
                                    @if($review_tool_type === '1')
                                    <table style="text-align: center;align= left; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px; line-height: 1.5em;width: 90%;">
                                        <tr style="background-color: #f0f2f5;">
                                            <th width="30%" style="border: 1px solid #000">项目流名</th>
                                            <th style="border: 1px solid #000">提交总数</th>
                                            <th style="border: 1px solid #000">提交前评审处理率</th>
                                            <th style="border: 1px solid #000">提交后评审处理率</th>
                                            <th style="border: 1px solid #000">总评审处理率</th>
                                            <th style="border: 1px solid #000;">总评审处理率趋势</th>
                                        </tr>
                                        @foreach ($review_rate_detail as $review_rate_detail_item)
                                            <tr>
                                                <td style="border: 1px solid #000">{{$review_rate_detail_item['repo_name']}}</td>
                                                <td style="border: 1px solid #000">{{$review_rate_detail_item['commits']}}</td>
                                                <td style="border: 1px solid #000">{{$review_rate_detail_item['diff_rate'] === 'N/A' ? 'N/A' : $review_rate_detail_item['diff_rate'].'%'}}</td>
                                                <td style="border: 1px solid #000">{{$review_rate_detail_item['audit_rate'] === 'N/A' ? 'N/A' : $review_rate_detail_item['audit_rate'].'%'}}</td>
                                                <td style="border: 1px solid #000">{{$review_rate_detail_item['all_rate'] === 'N/A' ? 'N/A' : $review_rate_detail_item['all_rate'].'%'}}</td>
                                                <td style="border: 1px solid #000">
                                                    @if($is_preview)
                                                        <img style="display: block;" src="{{$review_rate_detail_item['week_chart']}}" alt="image">
                                                    @else
                                                        <img style="display: block;" src="{{ $message->embedData($review_rate_detail_item['week_chart'], '评审处理率周趋势') }}" alt="image">
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                    @endif
                                    @if($review_tool_type === '2' || $review_tool_type === '3')
                                        <table style="text-align: center;align= left; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px; line-height: 1.5em;width: 90%;">
                                            <tr style="background-color: #f0f2f5;">
                                                <th width="30%" style="border: 1px solid #000">项目流名</th>
                                                <th style="border: 1px solid #000">提交数</th>
                                                <th style="border: 1px solid #000">评审处理率</th>
                                                <th style="border: 1px solid #000;">评审处理率趋势</th>
                                            </tr>
                                            @foreach ($review_rate_detail as $review_rate_detail_item)
                                                <tr>
                                                    <td style="border: 1px solid #000">{{$review_rate_detail_item['repo_name']}}</td>
                                                    <td style="border: 1px solid #000">{{$review_rate_detail_item['commits']}}</td>
                                                    <td style="border: 1px solid #000">{{$review_rate_detail_item['all_rate'] === 'N/A' ? 'N/A' : $review_rate_detail_item['all_rate'].'%'}}</td>
                                                    <td style="border: 1px solid #000">
                                                        @if($is_preview)
                                                            <img style="display: block;" src="{{$review_rate_detail_item['week_chart']}}" alt="image">
                                                        @else
                                                            <img style="display: block;" src="{{ $message->embedData($review_rate_detail_item['week_chart'], '评审处理率周趋势') }}" alt="image">
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        @if($tool_type == '2' && ($user_id == 115 or $user_id == 110))
                            <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%;">
                                <tr>
                                    <td width="20" align="center" valign="top">五、</td>
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
                                    <td width="20" align="center" valign="top">六、</td>
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
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>