<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="zh">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>报告</title>
    <style>
        .info{
            font-style: italic;
        }
    </style>
</head>
<body>
<div style="font-family: 'Microsoft YaHei',Arial,sans-serif;margin-left:8%;margin-right:8%;padding-left: 8%;padding-right: 8%; font-size: 13px; line-height: 1.5em;text-align: left;">
    <div>
        <p style="text-align: center"><font size="5px"><b>
        {{$subject}}
        </b></font></p>
    </div>
    <hr style="width:112%;height:2px;;border:none;border-top:2px solid black"/>
    <div><p><font size="4px" color="#FF0000"><b>概述</b></font></p></div>
    <div style="margin-left:20px;">
        <div>
            <ol>
                <li>本次统计周期：{{$summary['period']['start']}} --- {{$summary['period']['end']}}。</li>
                <li>确定为纳入SQA例行管理的项目，后续例行每季度输出！</li>
                <li>季报纳入统计项目共计{{$summary['sum']}}个，其中基础产品{{$summary['basic_product']}}个，解决方案{{$summary['solution']}}个，资源部门{{$summary['resource_sector']}}个。</li>
                <li>指标采纳率：<br/>
                    <span>&nbsp;&nbsp;&nbsp;从产品类别来看，各类别平均采纳率分别为：{{$base_adopt_info['class_sort']}}。</span><br/>
                    <span>&nbsp;&nbsp;&nbsp;从二级部门来看，采纳率较高部门为：<font color="#337d2f">{{$base_adopt_info['high_sort']}}；</font>较低的部门为：<font color="#FF0000"> {{$base_adopt_info['low_sort']}}。 </font></span>
                </li>
                <li>指标达标率：<br/>
                    <span>&nbsp;&nbsp;&nbsp;从产品类别来看，各类别平均达标率分别为：{{$base_reach_info['class_sort']}}。</span><br/>
                    <span>&nbsp;&nbsp;&nbsp;从二级部门来看，达标率较高部门为：<font color="#337d2f">{{$base_reach_info['high_sort']}}；</font>较低的部门为：<font color="#FF0000"> {{$base_reach_info['low_sort']}}。 </font>
                </li>
                <li>各项目基本指标达标率：<br/>
                    <span>&nbsp;&nbsp;&nbsp;项目达标情况较高的有：<font color="#337d2f">{{$project_reach_info['top_three']}}。</font></span><br/>
                    <span>&nbsp;&nbsp;&nbsp;项目达标情况较低的有：<font color="#FF0000">{{$project_reach_info['bottom_three']}}。</font></span>
                </li>
                <li>
                    各阶段指标平均达标率：<br/>
                    <span>&nbsp;&nbsp;&nbsp;从各阶段指标达标情况来看：<font color="#337d2f">{{$stage_reach_info['stage_sort']}}</font>达标率均较高，请各部门继续保持。</span><br/>
                    <span>&nbsp;&nbsp;&nbsp;从各阶段产线来看：设计阶段<font color="#337d2f">{{$stage_reach_info['design_stage']['high_rate']}}</font>，开发阶段<font color="#337d2f">{{$stage_reach_info['develop_stage']['high_rate']}}</font>，测试阶段<font color="#337d2f">{{$stage_reach_info['publish_stage']['high_rate']}}</font>平均达标率较高。</span>
                </li>
                <li>各指标达标率概况：<br/>
                    <span>&nbsp;&nbsp;&nbsp;开发阶段：公司<font color="#FF0000">{{$improve_info['develop_reach_info']}}</font>偏低。</span><br/>
                    <span>&nbsp;&nbsp;&nbsp;测试阶段：公司<font color="#FF0000">{{$improve_info['test_publish_reach_info']}}</font>偏低。</span>
                </li>
            </ol>
        </div>
        
        <div class="info" style="margin-left:20px;">
            <p><font color="#0070c0"><b>指标说明</b></font></p>
            <p>基本指标说明：</p>
            <ul>
            <li>根据不同项目类型，基本指标总数不同，其中基础产品14个指标、解决方案10个指标、资源部门10个指标</li>
            <li>基础产品：设计阶段（设计文档齐套率、设计文档评审覆盖率、设计文档缺陷解决率），开发阶段（静态检查严重缺陷遗留数、代码注释率、人均评审时长、代码评审覆盖率-线上、评审有效率-线上、评审及时率），测试阶段（系统测试用例评审覆盖率），发布阶段（发布遗留静态检查严重缺陷数、发布代码线上评审覆盖率、 发布遗留测试缺陷数、发布遗留测试严重缺陷数）</li>
            <li>解决方案：设计阶段（设计文档评审覆盖率、设计文档缺陷解决率），开发阶段（静态检查严重缺陷遗留数、代码注释率、人均评审时长、代码评审覆盖率-线上、评审有效率-线上、评审及时率），测试阶段（系统测试用例评审覆盖率）</li>
            <li>资源部门：设计阶段（设计文档评审覆盖率、设计文档缺陷解决率），开发阶段（静态检查严重缺陷遗留数、代码注释率、人均评审时长、代码评审覆盖率-线上、评审有效率-线上、评审及时率），测试阶段（系统测试用例评审覆盖率）</li>
            </ul>
            <p>项目基本指标采纳率：</p>
            <ul>
                <li>项目基本指标采纳率 = 项目已采用指标数 / 基本指标总数</li>
                <li>部门指标采纳率 = 部门内项目指标采纳率之和 / 部门项目数</li>
            </ul>
            <p>项目基本指标达标率：</p>
            <ul>
                <li>项目基本指标达标率 = 项目基本指标达标数 / 项目已采纳指标数</li>
                <li>部门指标达标率 = 部门内项目指标达标率之和 / 部门项目数</li>
            </ul>
        </div>
    </div>
    <br/>
    <div><p><font size="4px" color="#FF0000"><b>详情</b></font></p></div>
    <p><font color="#0070c0" size="3px">一、基本指标采纳率</font></p>
    <div style="margin-left:20px;">
        @if($is_preview)
        <p><img src="{{$base_adopt_bar['basic_product']}}"></p>
        <p><img src="{{$base_adopt_bar['solution']}}"></p>
        <p><img src="{{$base_adopt_bar['resource_sector']}}"></p>
        @else
        <p><img src="{{$message->embedData($base_adopt_bar['basic_product'],'image')}}"></p>
        <p><img src="{{$message->embedData($base_adopt_bar['solution'],'image')}}"></p>
        <p><img src="{{$message->embedData($base_adopt_bar['resource_sector'],'image')}}"></p>
        @endif
        
        <p><font color="#0070c0"><b>说明：</b></font></p>
        <ul>
            <li>本次统计截止至{{$summary['period']['end']}}最新数据；</li>
            <li>横坐标代表产线及二级部门，SQA未参与的部门不在此图中；</li>
            <li>纵坐标代表二级部门所有软件质量要求相关指标采纳率；</li>
            <li>公司平均线代表各类别所有部门指标采纳率的平均值；</li>
            <li>指标采纳率越高越好，目标100%，全部采纳。</li>
        </ul>
        <p><font color="#FF0000"><b>统计分析：</b></font></p>
        <ol>
            <li>公司平均采纳率为{{$base_adopt_info['company_rate']}}%；</li>
            <li>从类别来看，采纳率由高到低分别为：{{$base_adopt_info['class_sort']}}；</li>
            <li>从二级部门来看，采纳率较高的部门为：<font color="#337d2f">{{$base_adopt_info['high_sort']}}；</font>采纳率较低的部门为：<font color="#FF0000">{{$base_adopt_info['low_sort']}}。</font></li>
        </ol>
    </div>
    
    
    <p><font color="#0070c0" size="3px">二、基本指标达标率</font></p>
    <div style="margin-left:20px;">
        @if($is_preview)
        <p><img src="{{$base_reach_bar['basic_product']}}"></p>
        <p><img src="{{$base_reach_bar['solution']}}"></p>
        <p><img src="{{$base_reach_bar['resource_sector']}}"></p>
        @else
        <p><img src="{{$message->embedData($base_reach_bar['basic_product'],'image')}}"></p>
        <p><img src="{{$message->embedData($base_reach_bar['solution'],'image')}}"></p>
        <p><img src="{{$message->embedData($base_reach_bar['resource_sector'],'image')}}"></p>
        @endif
        <p><font color="#0070c0"><b>说明：</b></font></p>
        <ul>
            <li>本次统计截止至{{$summary['period']['end']}}最新数据；</li>
            <li>横坐标代表产线及二级部门，SQA未参与的部门不在此图中；</li>
            <li>纵坐标代表本部门所有软件质量要求相关指标达标率；</li>
            <li>公司平均，代表各类别所有部门指标采纳率的平均值；</li>
            <li>指标达标率越高越好，目标100%，全部达标。</li>
        </ul>
        <p><font color="#FF0000"><b>统计分析：</b></font></p>
        <ol>
            <li>公司平均达标率为{{$base_reach_info['company_rate']}}%；</li>
            <li>从类别来看，达标率由高到低分别为：{{$base_reach_info['class_sort']}}；</li>
            <li>从二级部门来看，达标率较高的部门为：<font color="#337d2f">{{$base_reach_info['high_sort']}}；</font>达标率较低的部门为：<font color="#FF0000">{{$base_reach_info['low_sort']}}。</font></li>
        </ol>
    </div>
    
    
    <p><font color="#0070c0" size="3px">三、各项目基本指标达标率</font></p>
    <div style="margin-left:20px;">
        @foreach ($project_reach_bar as $value)
            @if($is_preview)
            <p><img src="{{$value}}"></p>
            @else
            <p><img src="{{$message->embedData($value,'image')}}"></p>
            @endif
        @endforeach
        <p><font color="#0070c0"><b>说明：</b></font></p>
        <ul>
            <li>如果参与项目过多，图表则分2图显示达标率排名前15和后15项目；</li>
            <li>达标率跟采纳指标数有关联，采纳指标数较少的项目容易达到100%；</li>
            <li>横坐标代表在研项目指标达标率情况，纵坐标代表SQA参与的项目；</li>
            <li>达标率越高越好，目标希望全部达标，达标率达100%。</li>
        </ul>
        <p><font color="#FF0000"><b>统计分析：</b></font></p>
        <ol>
            <li>达标率较高项目的平均达标率为{{$project_reach_data['top']['average']}}%，达标率较低项目的平均达标率为{{$project_reach_data['bottom']['average']}}%。</li>
            <li>指标达标率为100%项目个数为 {{$project_reach_info['num']}}；</li>
            <li>指标达标率较高Top3项目有：<font color="#337d2f">{{$project_reach_info['top_three']}}</font>；</li>
            <li>指标达标率较低Top3项目有：<font color="#FF0000">{{$project_reach_info['bottom_three']}}</font>。</li>
        </ol>
    </div>
    
    <p><font color="#0070c0" size="3px">四、各阶段基本指标达标率</font></p>
    <div style="margin-left:20px;">
        @if($is_preview)
        <img src="{{$stage_reach_bar['design_stage']}}">
        <img src="{{$stage_reach_bar['develop_stage']}}">
        <img src="{{$stage_reach_bar['publish_stage']}}">
        @else
        <img src="{{$message->embedData($stage_reach_bar['design_stage'],'image')}}">
        <img src="{{$message->embedData($stage_reach_bar['develop_stage'],'image')}}">
        <img src="{{$message->embedData($stage_reach_bar['publish_stage'],'image')}}">
        @endif
        <p><font color="#FF0000"><b>统计分析：</b></font></p>
        <ol>
            <li>阶段达标率由高到低依次为：{{$stage_reach_info['stage_sort']}}；</li>
            <li>设计阶段达标率较高的为：<font color="#337d2f">{{$stage_reach_info['design_stage']['high_rate']}}</font>，<font color="#FF0000">{{$stage_reach_info['design_stage']['low_rate']}}</font>需要加强文档输出及评审力度
            @if(!empty($stage_reach_info['design_stage']['lost']))
                ，{{$stage_reach_info['design_stage']['lost']}}没有此阶段指标数据；
            @else
                ；
            @endif
            </li>
            <li>开发阶段达标率较高的为：<font color="#337d2f">{{$stage_reach_info['develop_stage']['high_rate']}}</font>，<font color="#FF0000">{{$stage_reach_info['develop_stage']['low_rate']}}</font>有较大的提升空间
            @if(!empty($stage_reach_info['develop_stage']['lost']))
                ，{{$stage_reach_info['develop_stage']['lost']}}没有此阶段指标数据；
            @else
                ；
            @endif
            </li>
            <li>测试&&发布阶段达标率较高的为：<font color="#337d2f">{{$stage_reach_info['publish_stage']['high_rate']}}</font>，<font color="#FF0000">{{$stage_reach_info['publish_stage']['low_rate']}}</font>低于平均水平
            @if(!empty($stage_reach_info['publish_stage']['lost']))
                ，{{$stage_reach_info['publish_stage']['lost']}}没有此阶段指标数据；
            @else
                ；
            @endif
            </li>
        </ol>
    </div>
    
    <p><font color="#0070c0" size="3px">五、各阶段具体指标达标率</font></p>
    <div class="info" style="margin-left:20px;">
        <p><font color="#0070c0"><b>具体指标说明</b></font></p>
        <p>设计阶段指标说明：</p>
        <ul>
            <li>设计文档齐套率 = 实际输出设计文档数/计划输出设计文档数，达标值90%以上</li>
            <li>设计文档评审覆盖率 = 设计文档评审数/实际输出设计文档数，达标值90%以上</li>
            <li>设计文档评审缺陷解决率 = 已解决设计文档评审缺陷数/设计文档评审缺陷总数，达标值100%以上</li>
            <li>系统测试用例评审覆盖率 = 系统测试用例评审数/实际输出系统测试用例数，达标值90%以上</li>
        </ul>
        <p>开发阶段指标说明</p>
        <ul>
            <li>静态检查严重缺陷遗留数，达标值10个以下</li>
            <li>代码注释率 = 代码注释行数/有效代码行数，达标值16%以上</li>
            <li>人均评审时长-线下 = 评审时长*开发人员评审人数/部门人数（度量平台上人数），达标值0.75小时以上</li>
            <li>代码评审覆盖率-线上 = 代码评审次数/代码提交次数，达标值100%以上</li>
            <li>评审有效率-线上 = 代码有效评审次数/代码评审次数，达标值60%以上</li>
            <li>评审及时率-线上 = 代码及时评审次数/代码评审次数，达标值90%以上</li>
        </ul>
        <p>发布阶段指标说明</p>
        <ul>
            <li>发布遗留静态检查严重缺陷数，达标值10个以下</li>
            <li>发布代码线上评审覆盖率 = 代码提交经评审次数/代码提交次数，达标值80%以上</li>
            <li>发布遗留测试缺陷数，达标值10个以下</li>
            <li>发布遗留测试严重缺陷数，达标值0个以下</li>
        </ul>
    </div>
    <p><b>5.1&nbsp;设计阶段各指标达标率按产线统计情况</b></p>
    <div style="margin-left:20px;">
        @foreach ($stage_specific_reach_bar['design'] as $key=>$value)
            @if($key != 'test_case_review_coverage_rate')
                @if($is_preview)
                <img src="{{$value}}">
                @else
                <img src="{{$message->embedData($value,'image')}}">
                @endif
            @endif
        @endforeach
        @if($is_preview)
        <img src="{{$stage_specific_reach_bar['design']['test_case_review_coverage_rate']}}">
        @else
        <img src="{{$message->embedData($stage_specific_reach_bar['design']['test_case_review_coverage_rate'],'image')}}">
        @endif
        <p><font color="#0070c0"><b>说明：</b></font></p>
        <ul>
            <li>本次统计的是SQA覆盖项目；</li>
            <li>横坐标代表各产线设计阶段各指标，SQA未参与的项目统计不在此图中；</li>
            <li>纵坐标代表具体指标指标达标率；</li>
            <li>达标率越高越好，目标80%以上；</li>
            <li>为防止达标率虚高，各所辖管控范围的SQA，会有详细的计算统计详情，供各位综合考量。</li>
        </ul>
        <p><font color="#FF0000"><b>统计分析：</b></font></p>
        <ol>
            <li>设计阶段平均达标率依次为{{$avg_comment['design']}}</li>
            <li>{{$design_reach_info['design_doc_finish_rate'][0]}}<font color="#337d2f">{{$design_reach_info['design_doc_finish_rate'][1]}}</font><font color="#FF0000">{{$design_reach_info['design_doc_finish_rate'][2]}}</font>{{$design_reach_info['design_doc_finish_rate'][3]}}{{$design_reach_info['design_doc_finish_rate'][4]}}</li>
            <li>{{$design_reach_info['design_doc_review_coverage_rate'][0]}}<font color="#337d2f">{{$design_reach_info['design_doc_review_coverage_rate'][1]}}</font><font color="#FF0000">{{$design_reach_info['design_doc_review_coverage_rate'][2]}}</font>{{$design_reach_info['design_doc_review_coverage_rate'][3]}}{{$design_reach_info['design_doc_review_coverage_rate'][4]}}</li>
            <li>{{$design_reach_info['design_doc_review_debug_rate'][0]}}<font color="#337d2f">{{$design_reach_info['design_doc_review_debug_rate'][1]}}</font><font color="#FF0000">{{$design_reach_info['design_doc_review_debug_rate'][2]}}</font>{{$design_reach_info['design_doc_review_debug_rate'][3]}}{{$design_reach_info['design_doc_review_debug_rate'][4]}}</li>
            <li>{{$design_reach_info['test_case_review_coverage_rate'][0]}}<font color="#337d2f">{{$design_reach_info['test_case_review_coverage_rate'][1]}}</font><font color="#FF0000">{{$design_reach_info['test_case_review_coverage_rate'][2]}}</font>{{$design_reach_info['test_case_review_coverage_rate'][3]}}{{$design_reach_info['test_case_review_coverage_rate'][4]}}</li>
        </ol>
    </div>
    <p><b>5.2&nbsp;开发阶段各指标达标率按产线统计情况</b></p>
    <div style="margin-left:20px;">
        @if($is_preview)
        <img src="{{$stage_specific_reach_bar['develop']['static_check_serious_bug_count']}}">
        <img src="{{$stage_specific_reach_bar['develop']['code_annotation_rate']}}">
        <img src="{{$stage_specific_reach_bar['develop']['code_online_review_coverage_rate']}}">
        <img src="{{$stage_specific_reach_bar['develop']['code_online_review_efficiency_rate']}}">
        <img src="{{$stage_specific_reach_bar['develop']['code_online_review_timely_rate']}}">
        @else
        <img src="{{$message->embedData($stage_specific_reach_bar['develop']['static_check_serious_bug_count'],'image')}}">
        <img src="{{$message->embedData($stage_specific_reach_bar['develop']['code_annotation_rate'],'image')}}">
        <img src="{{$message->embedData($stage_specific_reach_bar['develop']['code_online_review_coverage_rate'],'image')}}">
        <img src="{{$message->embedData($stage_specific_reach_bar['develop']['code_online_review_efficiency_rate'],'image')}}">
        <img src="{{$message->embedData($stage_specific_reach_bar['develop']['code_online_review_timely_rate'],'image')}}">
        @endif
        <p><font color="#0070c0"><b>说明：</b></font></p>
        <ul>
            <li>本次统计的是SQA覆盖项目；</li>
            <li>横坐标代表各产线开发阶段各指标，SQA未参与的项目统计不在此图中；</li>
            <li>纵坐标代表具体指标指标达标率；</li>
            <li>达标率越高越好，目标80%以上；</li>
            <li>为防止达标率虚高，各所辖管控范围的SQA，会有详细的计算统计详情，供各位综合考量。</li>
        </ul>
        <p><font color="#FF0000"><b>统计分析：</b></font></p>
        <ol>
            <li>开发阶段平均达标率依次为{{$avg_comment['develop']}}</li> 
            <li>{{$develop_reach_info['static_check_serious_bug_count'][0]}}<font color="#337d2f">{{$develop_reach_info['static_check_serious_bug_count'][1]}}</font><font color="#FF0000">{{$develop_reach_info['static_check_serious_bug_count'][2]}}</font>{{$develop_reach_info['static_check_serious_bug_count'][3]}}{{$develop_reach_info['static_check_serious_bug_count'][4]}}</li>
            <li>{{$develop_reach_info['code_annotation_rate'][0]}}<font color="#337d2f">{{$develop_reach_info['code_annotation_rate'][1]}}</font><font color="#FF0000">{{$develop_reach_info['code_annotation_rate'][2]}}</font>{{$develop_reach_info['code_annotation_rate'][3]}}{{$develop_reach_info['code_annotation_rate'][4]}}</li>
            <li>{{$develop_reach_info['review_time_per_capita_count'][0]}}<font color="#337d2f">{{$develop_reach_info['review_time_per_capita_count'][1]}}</font><font color="#FF0000">{{$develop_reach_info['review_time_per_capita_count'][2]}}</font>{{$develop_reach_info['review_time_per_capita_count'][3]}}{{$develop_reach_info['review_time_per_capita_count'][4]}}</li>
            <li>{{$develop_reach_info['code_online_review_coverage_rate'][0]}}<font color="#337d2f">{{$develop_reach_info['code_online_review_coverage_rate'][1]}}</font><font color="#FF0000">{{$develop_reach_info['code_online_review_coverage_rate'][2]}}</font>{{$develop_reach_info['code_online_review_coverage_rate'][3]}}{{$develop_reach_info['code_online_review_coverage_rate'][4]}}</li>
            <li>{{$develop_reach_info['code_online_review_efficiency_rate'][0]}}<font color="#337d2f">{{$develop_reach_info['code_online_review_efficiency_rate'][1]}}</font><font color="#FF0000">{{$develop_reach_info['code_online_review_efficiency_rate'][2]}}</font>{{$develop_reach_info['code_online_review_efficiency_rate'][3]}}{{$develop_reach_info['code_online_review_efficiency_rate'][4]}}</li>
            <li>{{$develop_reach_info['code_online_review_timely_rate'][0]}}<font color="#337d2f">{{$develop_reach_info['code_online_review_timely_rate'][1]}}</font><font color="#FF0000">{{$develop_reach_info['code_online_review_timely_rate'][2]}}</font>{{$develop_reach_info['code_online_review_timely_rate'][3]}}{{$develop_reach_info['code_online_review_timely_rate'][4]}}</li>
        </ol>
    </div>
    <p><b>5.3&nbsp;发布阶段各指标达标率按产线统计情况</b></p>
    <div style="margin-left:20px;">
        @foreach ($stage_specific_reach_bar['test'] as $key=>$value)
            @if($is_preview)
            <img src="{{$value}}">
            @else
            <img src="{{$message->embedData($value,'image')}}">
            @endif
        @endforeach
        <p><font color="#0070c0"><b>说明：</b></font></p>
        <ul>
            <li>本次统计的是SQA覆盖项目；</li>
            <li>横坐标代表各产线测试阶段各指标，SQA未参与的项目统计不在此图中；</li>
            <li>纵坐标代表具体指标指标达标率；</li>
            <li>达标率越高越好，目标80%以上；</li>
            <li>为防止达标率虚高，各所辖管控范围的SQA，会有详细的计算统计详情，供各位综合考量。</li>
        </ul>
        <p><font color="#FF0000"><b>统计分析：</b></font></p>
        <ol>
            <li>发布阶段平均达标率依次为{{$avg_comment['test']}}</li>
            <li>{{$test_publish_reach_info['issue_static_check_serious_bug_count'][0]}}<font color="#337d2f">{{$test_publish_reach_info['issue_static_check_serious_bug_count'][1]}}</font><font color="#FF0000">{{$test_publish_reach_info['issue_static_check_serious_bug_count'][2]}}</font>{{$test_publish_reach_info['issue_static_check_serious_bug_count'][3]}}{{$test_publish_reach_info['issue_static_check_serious_bug_count'][4]}}</li>
            <li>{{$test_publish_reach_info['issue_code_review_coverage_online_rate'][0]}}<font color="#337d2f">{{$test_publish_reach_info['issue_code_review_coverage_online_rate'][1]}}</font><font color="#FF0000">{{$test_publish_reach_info['issue_code_review_coverage_online_rate'][2]}}</font>{{$test_publish_reach_info['issue_code_review_coverage_online_rate'][3]}}{{$test_publish_reach_info['issue_code_review_coverage_online_rate'][4]}}</li>
            <li>{{$test_publish_reach_info['issue_bug_count'][0]}}<font color="#337d2f">{{$test_publish_reach_info['issue_bug_count'][1]}}</font><font color="#FF0000">{{$test_publish_reach_info['issue_bug_count'][2]}}</font>{{$test_publish_reach_info['issue_bug_count'][3]}}{{$test_publish_reach_info['issue_bug_count'][4]}}</li>
            <li>{{$test_publish_reach_info['issue_serious_bug_count'][0]}}<font color="#337d2f">{{$test_publish_reach_info['issue_serious_bug_count'][1]}}</font><font color="#FF0000">{{$test_publish_reach_info['issue_serious_bug_count'][2]}}</font>{{$test_publish_reach_info['issue_serious_bug_count'][3]}}{{$test_publish_reach_info['issue_serious_bug_count'][4]}}</li>
        </ol>
    </div>
    
    <div><p><font size="4px" color="#FF0000"><b>改进措施</b></font></p></div>
    <div style="margin-left:20px;">
        <ul>
            <li>提高指标采纳率：各个产线需加强软件质量管理要求，不能降低软件质量管理活动要求，否则质量无法得到较大改进！</li>
            <li>提高指标达标率：各部门应有效利用代码线上评审流程工具，如未部署该工具，需走代码线下评审！避免因代码本身质量问题导致发布遗留测试数较多等。</li>
        </ul>
    </div>
</div>
</body>
</html>