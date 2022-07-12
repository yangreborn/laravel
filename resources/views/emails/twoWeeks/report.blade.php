<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="zh">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>报告</title>
</head>
<body>
<div style="font-family: 'Microsoft YaHei',Arial,sans-serif;margin-left:8%;margin-right:8%;padding-left: 8%;padding-right: 8%; font-size: 13px; line-height: 1.5em;text-align: left;">
    <div>
        <p style="text-align: center"><font size="5px"><b>
        {{$subject}}
        </b></font></p>
    </div>
    <hr style="width:112%;height:2px;;border:none;border-top:2px solid black"/>
    <div><p><font size="4px"><b>一、概述</b></font></p></div>
    <div style="margin-left:20px;">
        <p>本次统计周期：{{$bugSystem_data['comment']['start_time']}} --- {{$bugSystem_data['comment']['end_time']}}。</p>
        <p>本次双周报共涉及 {{$bugSystem_data['comment']['total_dep']}} 个部门，合计 {{$bugSystem_data['comment']['total_prj']}} 个项目。</p>
        <p><b>说明:</b></p>
        <ol>
        <li>度量平台统计项目范围确定为纳入SQA例行管理的项目，后续例行半月输出。</li>
        @if($review_summary)
            {!! $review_summary !!}
        @endif
        </ol>
    </div>
    <br/>
    <hr style="width:112%;height:2px;;border:none;border-top:2px solid black"/>
    <div><p><font size="4px"><b>二、质量数据统计</b></font></p></div>
    <div><p>报告中涉及的所有数据均有系统承载，并有相应记录。目前仅包含开发数据。测试数据尚未纳入统计。</p></div>
    <br/>
    <hr style="width:112%;height:1px;;border:none;border-top:1px solid black"/>
    <div><p><font size="3px"><b>1、开发数据</b></font></p></div>
    <div style="margin-left:20px;"><p>开发数据包含缺陷、静态检查、代码评审、编译失败几部分数据。</p></div>
    <br/>
    <hr style="width:112%;height:1px;;border:none;border-top:1px solid black"/>
    <div><p><b>1.1、缺陷状况统计</b></p>
    <div>
        <p><b>说明:</b></p>
        <ul>
            <li>缺陷数据来源于TAPD、PLM。</li>
            <li>PLM工具：待解决包含新建、未分配、审核、延期、Assign、Reolve；待验证包含Validate；遗留缺陷数指：待解决、待验证的缺陷总数；解决缺陷指：已关闭。</li>
            <li>TAPD工具：待解决包含submitted/新建、assigned/未分配、opened/已分配、Postponed/延期、Verified；待验证包含：resolved/已解决；遗留缺陷数指：待解决、待验证的缺陷总数；解决缺陷指：已关闭。</li>
            <li>新解决缺陷数：本次统计时间段内关闭的缺陷数量；</li>
            <li>遗留缺陷总数：截止到统计时间，所有未关闭的缺陷数量；</li>
            <li>双周缺陷总数 = 新解决缺陷数 + 遗留缺陷总数；</li>
            <li>遗留缺陷率 = 遗留缺陷总数 / 双周缺陷总数；</li>
            <li>新解决缺陷率 = 新解决缺陷数 / 双周缺陷总数。</li>
        </ul>
    </div>
    
    @if($is_preview)
    <div><img src="{{$bugRemainChart}}"></div>
    @else
    <div><img src="{{$message->embedData($bugRemainChart, 'image')}}"></div>
    @endif
    <div>
        <ul>
            <li>纳入SQA管控并进行缺陷管理的项目共计 {{$bugSystem_data['comment']['project_num']}} 个，本次统计遗留缺陷数共 {{$bugSystem_data['comment']['bugRemain_num']}} 个，较上次统计<span>
                @if($bugSystem_data['comment']['change_num'] < 0)
                    <b><font color="#337d2f">下降{{abs($bugSystem_data['comment']['change_num'])}}个</font></b>
                @elseif($bugSystem_data['comment']['change_num'] > 0)
                    <b><font color="#FF0000">上升{{$bugSystem_data['comment']['change_num']}}个</font></b>
                @else
                    持平
                @endif
            </span>;</li>
            <li>严重及以上类缺陷共 {{$bugSystem_data['comment']['bugUpSerious_num']}} 个，较上次统计<span>
                @if($bugSystem_data['comment']['upChange_num'] < 0)
                   <b><font color="#337d2f"> 下降{{abs($bugSystem_data['comment']['upChange_num'])}}个</font></b>
                @elseif($bugSystem_data['comment']['upChange_num'] > 0)
                    <b><font color="#FF0000">上升{{$bugSystem_data['comment']['upChange_num']}}个</font></b>
                @else
                    持平
                @endif
            </span>，占本次统计遗留缺陷总数的 {{$bugSystem_data['comment']['bugUpSerious_rate']}}%。</li>
        </ul>
    </div>
    <br/>
    @if($is_preview)
    <div><img src="{{$depBugRemainChart}}"></div>
    @else
    <div><img src="{{$message->embedData($depBugRemainChart, 'image')}}"></div>
    @endif
    <div>
        <ul>
            <li>图中是本次统计中，公司遗留缺陷率较高的{{count($bugSystem_data['summary']['dep_remain_pic_data']['department'])}}个部门。</li>
            <li><b><font color="#FF0000">遗留缺陷率连续3次上榜的部门：</font></b>
            <span>
            @foreach ($bugSystem_data['comment']['rank_dep'] as $project => $data)
                {{$project}}(<b><font color="#FF0000">{{$data}}次</font></b>),
            @endforeach
            </span>相关部门需加强对缺陷修复的重视。</li>
        </ul>
    </div>
    <br/>
    @if($is_preview)
    <div><img src="{{$projectBugRemainChart}}"></div>
    @else
    <div><img src="{{$message->embedData($projectBugRemainChart, 'image')}}"></div>
    @endif
    <div>
        <ul>
            <li>图中是本次统计中，公司遗留缺陷率较高的{{count($bugSystem_data['summary']['remain_pic_data']['project'])}}个项目。</li>
            <li><b><font color="#FF0000">遗留缺陷率连续3次上榜的项目：</font></b>
            @if(count($bugSystem_data['comment']['rank_prj']['project']) > 0)
                <span>
                @foreach ($bugSystem_data['comment']['rank_prj']['project'] as $project => $data)
                    {{$project}}(<b><font color="#FF0000">{{$data}}次</font></b>),&nbsp;
                @endforeach
                </span>相关项目组需加强对缺陷修复的重视。
            @else
                无。
            @endif
            </li>
            <li><b><font color="#FF0000">缺陷遗留率较高的部门：</font></b>
           
            @if(count($bugSystem_data['comment']['rank_prj']['dep']) > 0)
                <span>
                @foreach ($bugSystem_data['comment']['rank_prj']['dep'] as $key => $value)
                {{$value}}，
                @endforeach
                </span>相关部门需加强宣导，及时修复缺陷。
            @else
                无。
            @endif
            </li>
        </ul>
    </div>
    @if($is_resolve_pic)
        <br/>
        @if($is_preview)
        <div><img src="{{$bugResolvedChart}}"></div>
        @else
        <div><img src="{{$message->embedData($bugResolvedChart, 'image')}}"></div>
        @endif
        <div>
            <ul>
                <li>图中是本次统计中，公司新解决缺陷率较高的{{count($bugSystem_data['summary']['close_pic_data']['project'])}}个项目。</li>
                <li>新解决缺陷率较高的项目：<b><font color="#337d2f">{{$bugSystem_data['comment']['rank_close_prj']}}</font></b>。</li>
                <li>新解决缺陷率较高的部门：<b><font color="#337d2f">{{$bugSystem_data['comment']['rank_close_dep']}}</font></b>。</li>
            </ul>
        </div>
    @else
        <br/>
        <div><p><font size="3px">本次统计周期中，未新解决缺陷!</font></p></div>
    @endif
    <br/>
    <hr style="width:112%;height:1px;;border:none;border-top:1px solid black"/>
    <div><p><b>1.2、静态检查遗留数</b></p>
    <ul><li>静态检查遗留数来源于各种工具检查出的问题总和。</li>
    <li>检查工具包含：Findbugs<统计未处理的高优先级问题数>、Tscancode<统计所有未处理问题>、Pclint<统计未处理3色告警数>。</li>
    <li>说明：某工具若未部署，则问题数默认为NA。三种工具均未安装的项目不显示。</li>
    </ul></div>
    @if($companyStaticCheckLineChart)
        @if($is_preview)
        <div><img src="{{$companyStaticCheckLineChart}}"></div>
        @else
        <div><img src="{{$message->embedData($companyStaticCheckLineChart, 'image')}}"></div>
        @endif
        <div><ul>
        @if($static_check_data['total_change'] >= 0)
            <li>静态检查遗留问题，共{{$static_check_data['total_summary']}}个，较上次统计<b><font color="#FF0000">上升{{abs($static_check_data['total_change'])}}个</font></b>。</li>
        @else
            <li>静态检查遗留问题，共{{$static_check_data['total_summary']}}个，较上次统计<b><font color="#337d2f">下降{{abs($static_check_data['total_change'])}}个</font></b>。</li>
        @endif
        </ul></div>
    @endif
    @if($departmentStaticCheckLineChart)
        <br/>
        @if($is_preview)
        <div><img src="{{$departmentStaticCheckLineChart}}"></div>
        @else
        <div><img src="{{$message->embedData($departmentStaticCheckLineChart, 'image')}}"></div>
        @endif
        <div><ul>
        <li>图中是本次统计中，公司静态检查遗留问题数较多的{{$static_check_data['department_count']}}个部门。</li>
        <li>静态检查遗留问题数连续3次上榜的部门：
        @foreach ($static_check_data['repeat_name']['repeat_department'] as $department => $data)
            {{$department}}(<b><font color="#FF0000">{{$data}}次</font></b>),
        @endforeach
        需相关部门加强重视。
        </ul></div>
    @endif
    @if($projectStaticCheckLineChart)
        <br/>
        @if($is_preview)
        <div><img src="{{$projectStaticCheckLineChart}}"></div>
        @else
        <div><img src="{{$message->embedData($projectStaticCheckLineChart, 'image')}}"></div>
        @endif
        <div><ul>
        <li>图中是本次统计中，公司静态检查遗留问题数较多的{{$static_check_data['project_count']}}个项目组。</li>
        <li>静态检查遗留问题数连续3次上榜的项目组：
        @foreach ($static_check_data['repeat_name']['repeat_project'] as $project => $data)
            {{$project}}(<b><font color="#FF0000">{{$data}}次</font></b>) ,
        @endforeach
        需相关部门加强重视。</li>
        </ul></div>
    @endif
    <br/>
    <hr style="width:112%;height:1px;;border:none;border-top:1px solid black"/>
    <div><p><b>1.3、评审处理数统计</b></p></div>
    <ul><li>线上代码评审数据来源于Phabricator。</li>
    <li>评审处理率 = 评审处理总数/ 评审总数；评审有效率 = 有效评审总数 / 评审处理总数。</li>
    <li>有效评审数 = 评审页面停留10秒以上的评审数。</li>
    </ul></div>
    @if($reviewCompanyChart)
        @if($is_preview)
        <div><img src="{{$reviewCompanyChart}}"></div>
        @else
        <div><img src="{{$message->embedData($reviewCompanyChart, 'image')}}"></div>
        @endif
        <div><ul>
        @if($review_datas['analysis']['valid_compare'] > 0)
            <li>本次评审有效率为{{$review_datas["company_datas"]['valid_rate'][7]}}%，较上次<b><font color="#337d2f">上升{{$review_datas['analysis']['valid_compare']}}%</font></b>。</li>
        @elseif($review_datas['analysis']['valid_compare'] < 0)
            <li>本次评审有效率为{{$review_datas["company_datas"]['valid_rate'][7]}}%，较上次<b><font color="#FF0000">下降{{0-$review_datas['analysis']['valid_compare']}}%</font></b>。</li>
        @else
            <li>本次评审有效率为{{$review_datas["company_datas"]['valid_rate'][7]}}%,较上次持平。</li>
        @endif
        @if($review_datas['analysis']['deal_compare'] > 0)
            <li>本次评审处理率为{{$review_datas["company_datas"]['deal_rate'][7]}}%，较上次<b><font color="#337d2f">上升{{$review_datas['analysis']['deal_compare']}}%</font></b>。</li>
        @elseif($review_datas['analysis']['deal_compare'] < 0)
            <li>本次评审处理率为{{$review_datas["company_datas"]['deal_rate'][7]}}%，较上次<b><font color="#FF0000">下降{{0-$review_datas['analysis']['deal_compare']}}%</font></b>。</li>
        @else
            <li>本次评审处理率为{{$review_datas["company_datas"]['valid_rate'][7]}}%,较上次持平。</li>
        @endif
        </ul></div>
    @endif
    @if($reviewDepartChart)
        <br/>
        @if($is_preview)
        <div><img src="{{$reviewDepartChart}}"></div>
        @else
        <div><img src="{{$message->embedData($reviewDepartChart, 'image')}}"></div>
        @endif
        <div><ul>
        @if(isset($review_datas['analysis']['departHdeal']))
        <li>{{$review_datas['analysis']['departHdeal']}}代码提交次数&评审率较高，代码提交评审的处理率为100%，希望后续能继续保持。</li>
        @endif
        </ul></div>
    @endif
    @if($reviewJobHChart)
        <br/>
        @if($is_preview)
        <div><img src="{{$reviewJobHChart}}"></div>
        @else
        <div><img src="{{$message->embedData($reviewJobHChart, 'image')}}"></div>
        @endif
        <div><ul>
        @if(isset($review_datas['analysis']['jobHdeal']))
        <li>{{$review_datas['analysis']['jobHdeal']}}代码提交评审率较高，代码提交评审的处理率为100%，希望后续能继续保持。</li>
        @endif
    @endif
    <br/>
    <hr style="width:112%;height:1px;;border:none;border-top:1px solid black"/>
    <div><p><b>1.4、编译失败数统计</b></p></div>
    <ul><li>版本编译失败数据来源于在CMO处部署的项目。</li>
    <li>失败率=失败总次数/本次统计中所有项目编译总次数<包含每日版本构建、发布版本构建>。</li>
    </ul></div>
    @if($compileCompanyChart)        
        @if($is_preview)
        <div><img src="{{$compileCompanyChart}}"></div>
        @else
        <div><img src="{{$message->embedData($compileCompanyChart, 'image')}}"></div>
        @endif
        @if($compile_datas['company_datas']['compare'] > 0)
            <div><ul><li>本次统计共涉及{{$compile_datas['company_datas']['build_count']}}个版本，共发生{{$compile_datas['company_datas']['failed_count']}}次编译错误，较上次统计<b><font color="#FF0000">上升{{$compile_datas['company_datas']['compare']}}次</font></b>。</li></ul></div>
        @elseif($compile_datas['company_datas']['compare'] < 0)
            <div><ul><li>本次统计共涉及{{$compile_datas['company_datas']['build_count']}}个版本，共发生{{$compile_datas['company_datas']['failed_count']}}次编译错误，较上次统计<b><font color="#337d2f">下降{{0-$compile_datas['company_datas']['compare']}}次</font></b>。</li></ul></div>
        @else
            <div><ul><li>本次统计共涉及{{$compile_datas['company_datas']['build_count']}}个版本，共发生{{$compile_datas['company_datas']['failed_count']}}次编译错误，较上次统计持平。</li></ul></div>
        @endif
    @endif
    @if($compileDepartChart)
        <br/>
        @if($is_preview)
        <div><img src="{{$compileDepartChart}}"></div>
        @else
        <div><img src="{{$message->embedData($compileDepartChart, 'image')}}"></div>
        @endif
        <div><ul>
        <li>图中是本次统计中，公司版本编译失败次数最多的{{count($compile_datas['depart_datas']['name'])}}个部门。</li>
        <li><b><font color="#FF0000">最近编译失败次数连续上榜3次以上的部门：</font></b><span>
        @if(!empty($compile_datas['repeat_name']['project']))
            @foreach ($compile_datas['repeat_name']['depart'] as $depart => $data)
            {{$depart}}(<b><font color="#FF0000">{{$data}}次</font></b>),
            @endforeach
            </span>相关部门需加强对版本编译通过率的重视。</li>
        @else
            无。
        @endif  
        </li></ul></div>
    @endif
    @if($compileJobChart)
        <br/>
        @if($is_preview)
        <div><img src="{{$compileJobChart}}"></div>
        @else
        <div><img src="{{$message->embedData($compileJobChart, 'image')}}"></div>
        @endif
        <div><ul>
        <li>图中是本次统计中，公司版本编译失败次数最多的{{count($compile_datas['job_datas']['name'])}}个项目组。</li>
        <li><b><font color="#FF0000">最近编译失败次数连续上榜3次以上的项目组：</font></b><span>
        @if(!empty($compile_datas['repeat_name']['project']))    
            @foreach ($compile_datas['repeat_name']['project'] as $project => $data)
                {{$project}}(<b><font color="#FF0000">{{$data}}次</font></b>),
            @endforeach
            </span>相关项目组需加强对版本编译通过率的重视。</li>
        @else
            无。
        @endif
        </ul></div>
    @endif
    <br/>
    <hr style="width:112%;height:2px;;border:none;border-top:2px solid black"/>
    
    <div><p><font size="4px"><b>三、各项指标详细数据</b></font></p></div>
    <hr style="width:112%;height:1px;;border:none;border-top:1px solid black"/>
    <div ><font size="3px"><b>1、缺陷状况统计</b></font></div>
    <br/>
    <div>
        <table style="width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
            <tr style="background-color: #f0f2f5;">
                <th style="border: 1px solid #000">一级部门</th>
                <th style="border: 1px solid #000">二级部门</th>
                <th style="border: 1px solid #000">项目</th>
                <th style="border: 1px solid #000">遗留缺陷率</th>
                <th style="border: 1px solid #000">遗留Bug总数</th>
                <th style="border: 1px solid #000">新增Bug总数</th>
                <th style="border: 1px solid #000">新解决Bug总数</th>

            </tr>
            @foreach ($bugSystem_data['details'] as $project => $data)
                @if(!($data['remain_num']==0 && $data['inc_num']==0 && $data['close_num']==0))
                <tr>
                    <td style="border: 1px solid #000">{{$data['proline']}}</td>
                    <td style="border: 1px solid #000">{{$data['department']}}</td>
                    <td style="text-align: left; border: 1px solid #000">{{$project}}</td>
                    <td style="border: 1px solid #000">{{$data['remain_rate']}}%
                        @if($data['remain_rate_trend'] > 0)
                            (<font color="#FF0000">↑{{$data['remain_rate_trend']}}%</font>)
                        @elseif($data['remain_rate_trend'] < 0)
                            (<font color="green">↓{{abs($data['remain_rate_trend'])}}%</font>)
                        @else
                            (-)
                        @endif
                    </td>
                    <td style="border: 1px solid #000">{{$data['remain_num']}}
                        @if($data['remain_num_trend'] > 0)
                            (<font color="#FF0000">↑{{$data['remain_num_trend']}}</font>)
                        @elseif($data['remain_num_trend'] < 0)
                            (<font color="green">↓{{abs($data['remain_num_trend'])}}</font>)
                        @else
                            (-)
                        @endif
                    </td>
                    <td style="border: 1px solid #000">{{$data['inc_num']}}</td>
                    <td style="border: 1px solid #000">{{$data['close_num']}}</td>
                </tr>
                @endif
            @endforeach
        </table>
        @if(count($bugSystem_data['comment']['bug_info_zero']) > 0)
            <div><span><b>以下项目数据均为0：</b></span><ul>
            @foreach ($bugSystem_data['comment']['bug_info_zero'] as $key => $value)
                <li>{{$value}}</li>
            @endforeach
            </ul></div>
        @endif
    </div>
    <br/>
    <div><font size="3px"><b>2、静态检查遗留数</b></font></div>
    <br/>
    <div>
        <table style="width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
            <tr style="background-color: #f0f2f5;">
                <th style="border: 1px solid #000">一级部门</th>
                <th style="border: 1px solid #000">二级部门</th>
                <th style="border: 1px solid #000">项目</th>
                <th style="border: 1px solid #000">遗留问题总数趋势图</th>
                <th style="border: 1px solid #000">Findbugs</th>
                <th style="border: 1px solid #000">Tscancode</th>
                <th style="border: 1px solid #000">PC-lint</th>
                <th style="border: 1px solid #000">Eslint</th>

            </tr>
            @foreach ($static_check_data['table_datas'] as $items)
            <tr>
                <td style="border: 1px solid #000">{{$items['first_level']}}</td>
                <td style="border: 1px solid #000">{{$items['second_level']}}</td>
                <td style="text-align: left; border: 1px solid #000">{{$items['project_name']}}</td>
                <td style="border: 1px solid #000">
                    @if($is_preview)
                    <img src="{{$items['static_line_charts']}}">
                    @else
                    <img src="{{$message->embedData($items['static_line_charts'], 'image')}}">
                    @endif
                </td>
                <td style="border: 1px solid #000">{{$items['findbugs_high']}}</td>
                <td style="border: 1px solid #000">{{$items['tscan_summary']}}</td>
                <td style="border: 1px solid #000">{{$items['pclint_error']}}</td>
                <td style="border: 1px solid #000">{{$items['eslint_summary']}}</td>
            </tr>
            @endforeach
        </table>
    </div>
    <br/>
    <div><font size="3px"><b>3、项目评审处理统计</b></font></div>
    <br/>
    <div>
        <table style="width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
            <tr style="background-color: #f0f2f5;">
                <th style="border: 1px solid #000">一级部门</th>
                <th style="border: 1px solid #000">二级部门</th>
                <th style="border: 1px solid #000">项目</th>
                <th style="border: 1px solid #000">评审提交数</th>
                <th style="border: 1px solid #000">评审处理率</th>

            </tr>
            @for ($i = 0; count($review_datas['job_Hdatas']['depart1'])-$i; ++$i)
                <tr>
                    <td style="border: 1px solid #000;">{{$review_datas['job_Hdatas']['depart1'][$i]}}</td>
                    <td style="border: 1px solid #000">{{$review_datas['job_Hdatas']['depart2'][$i]}}</td>
                    <td style="text-align: left; border: 1px solid #000">{{$review_datas['job_Hdatas']['name'][$i]}}</td>
                    <td style="border: 1px solid #000">{{$review_datas['job_Hdatas']['review_num'][$i]}}
                    @if($review_datas['job_Hdatas']['review_num_trend'][$i] > 0)
                        (<font color="green">↑{{$review_datas['job_Hdatas']['review_num_trend'][$i]}}</font>)
                    @elseif($review_datas['job_Hdatas']['review_num_trend'][$i] < 0)
                        (<font color="#FF0000">↓{{abs($review_datas['job_Hdatas']['review_num_trend'][$i])}}</font>)
                    @else
                        (-)
                    @endif
                    </td>
                    <td style="border: 1px solid #000">{{$review_datas['job_Hdatas']['deal_rate'][$i]}}%
                    @if($review_datas['job_Hdatas']['deal_rate_trend'][$i] > 0)
                        (<font color="green">↑{{$review_datas['job_Hdatas']['deal_rate_trend'][$i]}}%</font>)
                    @elseif($review_datas['job_Hdatas']['deal_rate_trend'][$i] < 0)
                        (<font color="#FF0000">↓{{abs($review_datas['job_Hdatas']['deal_rate_trend'][$i])}}%</font>)
                    @else
                        (-)
                    @endif
                    </td>
                </tr>
            @endfor
        </table>
    </div>
    <br/>
    <div><font size="3px"><b>4、项目编译失败统计</b></font></div>
    <br/>
    <div>
        <table style="width: 100%; text-align: center; border: 1px solid #000; border-collapse: collapse;font-family: 'Microsoft YaHei',Arial,sans-serif;font-size: 13px;">
            <tr style="background-color: #f0f2f5;">
                <th style="border: 1px solid #000">一级部门</th>
                <th style="border: 1px solid #000">二级部门</th>
                <th style="border: 1px solid #000">项目</th>
                <th style="border: 1px solid #000">编译失败次数</th>

            </tr>
            @for ($i = 0; count($compile_datas['job_datas']['name'])-$i; ++$i)
                <tr>
                    <td style="border: 1px solid #000;">{{$compile_datas['job_datas']['depart1'][$i]}}</td>
                    <td style="border: 1px solid #000">{{$compile_datas['job_datas']['depart2'][$i]}}</td>
                    <td style="text-align: left; border: 1px solid #000">{{$compile_datas['job_datas']['name'][$i]}}</td>
                    <td style="border: 1px solid #000">{{$compile_datas['job_datas']['failed_count'][$i]}}</td>
                </tr>
            @endfor
        </table>
    </div>
    <br/>
</div>
</body>
</html>