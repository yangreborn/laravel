<h2>Bug总况</h2>
<table>
    <tr style="background-color: #da9694;">
        <th width="30%" rowspan="2">{{ $name_to_show === 'project' ? '项目名称' : '产品名称' }}</th>
        <th width="30%" colspan="4">Bug状态</th>
        <th width="15%" rowspan="2">合计</th>
        <th width="15%" rowspan="2">备注</th>
    </tr>
    <tr style="background-color: #da9694;">
        <th width="7.5%">待解决</th>
        <th width="7.5%">延期</th>
        <th width="7.5%">待验证</th>
        <th width="7.5%">关闭</th>
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
                                <li>未分配: {{ $project['unassign_num'] }}</li>
                                <li>审核: {{ $project['review_num'] }}</li>
                                <li>Assign: {{ $project['assign_num'] }}</li>
                                <li>Resolve: {{ $project['resolve_num'] }}</li>
                            </ul>
                        </li>
                    </ul>
                </td>
            </tr>
        @endforeach
    <tr>
        <td>本次解决Bug数:（{{$count_start_time}}至{{$count_end_time}}）</td>
        <td colspan="6">{{ $bugcount['current_data']['current_solved_num'] }}</td>
    </tr>
    <tr>
    <td>本次新增Bug数:（{{$count_start_time}}至{{$count_end_time}}）</td>
    <td colspan="6">{{ $bugcount['current_data']['current_new_num'] }}</td>
</tr>
</table>
@if(!empty($chartList['bugcount']))
    <table style="border: none;">
    <tr style="border: none;">
        <td style="border: none;">
            @if($is_preview)
                <img src="{{ $chartList['bugcount'] }}" alt="image">
            @else
                <img src="{{ $message->embedData($chartList['bugcount'], 'image') }}">
            @endif
        </td>
    </tr>
</table>
@endif
@if(!empty($chartList['changed_bug_count']))
        <table style="border: none;">
            <tr style="border: none;">
                <td style="border: none;">
                    @if($is_preview)
                        <img src="{{ $chartList['changed_bug_count'] }}" alt="image">
                    @else
                        <img src="{{ $message->embedData($chartList['changed_bug_count'], 'image') }}">
                    @endif
                </td>
            </tr>
        </table>
    @endif