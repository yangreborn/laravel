@if(!empty($withoutReviewerBugCount))
    <h2>待解决Bug（当前审阅者信息缺失）</h2>
    <table>
        <tr style="background-color: #da9694;">
            <th width="28%">负责小组</th>
            <th width="12%">psr编号</th>
            <th width="12%">严重性</th>
            <th width="12%">出现频率</th>
            <th width="12%">状态</th>
            <th width="12%">创建者</th>
            <th width="12%">创建日期</th>
        </tr>
        @foreach($withoutReviewerBugCount as $key=>$value)
            @foreach($value as $item)
                @if($loop->first)
                    <tr>
                        <td rowspan="{{sizeof($value)}}">{{ $key }}</td>
                        <td>{{ $item['psr_number'] }}</td>
                        <td>{{ $item['seriousness'] }}</td>
                        <td>{{ $item['fre_occurrence'] }}</td>
                        <td>{{ $item['status'] }}</td>
                        <td>{{ $item['creator'] }}</td>
                        <td>{{ $item['create_time'] }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $item['psr_number'] }}</td>
                        <td>{{ $item['seriousness'] }}</td>
                        <td>{{ $item['fre_occurrence'] }}</td>
                        <td>{{ $item['status'] }}</td>
                        <td>{{ $item['creator'] }}</td>
                        <td>{{ $item['create_time'] }}</td>
                    </tr>
                @endif
            @endforeach
        @endforeach
    </table>
@endif