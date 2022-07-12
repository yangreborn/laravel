<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="zh">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Plm报告</title>
</head>
<body style="margin: 24px;">
    <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; width: 100%;margin-bottom: 25px;">
        <tr>
            <td style="text-align: center;"><h1>Plm Bug统计</h1></td>
        </tr>
    </table>
    @if(!empty($summary))
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; width: 100%;margin-bottom: 25px;">
            <tr>
                <td><h2>总结</h2></td>
            </tr>
            <tr>
                <td>{!! $summary !!}</td>
            </tr>
        </table>
    @endif
    <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; width: 100%;">
        <tr>
            <td><h2>Bug总况</h2><td>
        </tr>
    </table>
    <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center; border: 1px solid #a0a0a0; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
        <tr style="background-color: #da9694;">
            <th width="30%" rowspan="2">项目名称</th>
            <th width="30%" colspan="4">Bug状态</th>
            <th width="15%" rowspan="2">合计</th>
            <th width="15%" rowspan="2">备注</th>
        </tr>
        <tr style="background-color: #da9694;">
            <th width="7.5%">待解决</th>
            <th width="7.5%">延期</th>
            <th width="7.5%">待验证</th>
            <th width="7.5%">已关闭</th>
        </tr>
        @foreach($bugcount['projects_data'] as $key => $project)
            <tr>
                <td>{{ $key }}</td>
                <td>{{ $project['unresolved_num'] }}</td>
                <td>{{ $project['delay_num'] }}</td>
                <td>{{ $project['validate_num'] }}</td>
                <td>{{ $project['close_num'] }}</td>
                <td>{{ $project['unresolved_num'] + $project['validate_num'] + $project['close_num'] + $project['delay_num']}}</td>
                <td>
                    <ul style="padding-left: 24px;">
                        <li style="text-align: left;">
                            待解决:
                            <ul style="padding-left: 24px; text-align: left;">
                                <li>新建: {{ $project['create_num'] }}</li>
                                <li>审核: {{ $project['review_num'] }}</li>
                                <li>Resolve: {{ $project['resolve_num'] }}</li>
                                <li>Assign: {{ $project['assign_num'] }}</li>
                                <li>未分配: {{ $project['unassign_num'] }}</li>
                            </ul>
                        </li>
                    </ul>
                </td>
            </tr>
        @endforeach
        <tr>
            <td>本次解决Bug数:</td>
            <td colspan="6">{{ $bugcount['current_data']['current_solved_num'] }}</td>
        </tr>
        <tr>
        <td>本次新增Bug数:</td>
        <td colspan="6">{{ $bugcount['current_data']['current_new_num'] }}</td>
    </tr>
    </table>
    @if(!empty($importanceBugCount))
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; width: 100%;">
            <tr>
                <td><h2>未解决Bug（按严重性分布）</h2></td>
            </tr>
        </table>
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center;border: 1px solid #a0a0a0; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
        <tr style="background-color: #da9694">
            <th width="28%">负责小组</th>
            <th width="7.2%">1-致命</th>
            <th width="7.2%">2-严重</th>
            <th width="7.2%">3-普通</th>
            <th width="7.2%">4-较低</th>
            <th width="7.2%">5-建议</th>
            <th width="7.2%" style="background-color: #00b0f0">待解决Bug数</th>
            <th width="7.2%" style="background-color: #00b0f0">待验证</th>
            <th width="7.2%" style="background-color: #00b0f0">延期</th>
            <th width="7.2%" style="background-color: #95b3d7">本次解决</th>
            <th width="7.2%" style="background-color: #95b3d7">本次增加</th>
        </tr>
            @foreach($importanceBugCount as $key=>$item)
                <tr>
                    <td>{{ $key }}</td>
                    <td>{{ $item['1-致命'] }}</td>
                    <td>{{ $item['2-严重'] }}</td>
                    <td>{{ $item['3-普通'] }}</td>
                    <td>{{ $item['4-较低'] }}</td>
                    <td>{{ $item['5-建议'] }}</td>
                    <td>{{ $item['unresolved'] }}</td>
                    <td>{{ $item['validate'] }}</td>
                    <td>{{ $item['delay'] }}</td>
                    <td>{{ $item['current_resolved'] }}</td>
                    <td>{{ $item['current_new'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    @if(!empty($unresolvedProductBugCount))
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; width: 100%;">
            <tr>
                <td><h2>未解决Bug（按产品分布）</h2></td>
            </tr>
        </table>
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center; border: 1px solid #a0a0a0; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
            <tr  style="background-color: #da9694;">
                <th width="28%">负责小组</th>
                @foreach($unresolved_bug_products as $product_title => $value)
                    <th>{{ $product_title }}</th>
                @endforeach
                <th style="background-color: #00b0f0">待解决</th>
                <th style="background-color: #00b0f0">待验证</th>
                <th style="background-color: #95b3d7">本次解决</th>
                <th style="background-color: #95b3d7">本次新增</th>
            </tr>
            @foreach($unresolvedProductBugCount as $key=>$value)
                <tr>
                    <td>{{ $key }}</td>
                    @foreach($value as $value_data)
                        <td>{{ $value_data }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif
    @if(!empty($unresolvedReviewerBugCount))
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; width: 100%;">
            <tr>
                <td><h2>待解决bug（按当前审阅者分布）</h2></td>
            </tr>
        </table>
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center; border: 1px solid #a0a0a0; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
            <tr style="background-color: #da9694">
                <th width="28%">审阅人</th>
                @foreach($unresolvedReviewerBugCount['status'] as $statu)
                    <th>{{ $statu }}</th>
                @endforeach
            </tr>
            @foreach($unresolvedReviewerBugCount['keys'] as $key)
                <tr>
                    <td>{{ $key }}</td>
                    @foreach($unresolvedReviewerBugCount['status'] as $statu)
                        <td>{{ $unresolvedReviewerBugCount[$key][$statu] }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif
    @if(!empty($testImportanceBugCount))
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; width: 100%;">
            <tr>
                <td><h2>新增Bug（按测试人员分布）</h2></td>
            </tr>
        </table>
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center;border: 1px solid #a0a0a0; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
            <tr style="background-color: #da9694">
                <th width="28%">测试人员</th>
                <th width="12%">1-致命</th>
                <th width="12%">2-严重</th>
                <th width="12%">3-普通</th>
                <th width="12%">4-较低</th>
                <th width="12%">5-建议</th>
                <th width="12%">总计</th>
            </tr>
            @foreach($testImportanceBugCount as $key=>$item)
                <tr>
                    <td>{{ $key }}</td>
                    <td>{{ $item['1-致命'] }}</td>
                    <td>{{ $item['2-严重'] }}</td>
                    <td>{{ $item['3-普通'] }}</td>
                    <td>{{ $item['4-较低'] }}</td>
                    <td>{{ $item['5-建议'] }}</td>
                    <td>{{ $item['总计'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    @if(!empty($lateBugCount))
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif; width: 100%;">
            <tr>
                <td><h2>Bug超期&amp;未填写概况</h2></td>
            </tr>
        </table>
        <table style="font-family: 'Microsoft YaHei',Arial,sans-serif;width: 100%; text-align: center;border: 1px solid #a0a0a0; border-collapse: collapse; font-size: 13px; line-height: 1.5em;" border="1">
            <tr style="background-color: #da9694">
                <th width="28%">负责小组</th>
                <th width="12%">超期2周bug数</th>
                <th width="12%">未填写承诺解决时间bug数</th>
                <th width="12%">合计</th>
            </tr>
            @foreach($lateBugCount as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['overdue_num'] }}</td>
                    <td>{{ $item['unavailable_num'] }}</td>
                    <td>{{ $item['total'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>