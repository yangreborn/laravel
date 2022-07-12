@if(!empty($rejectedBugCount))
    <h2>待解决Bug（按拒绝次数分布）</h2>
    <table>
    <tr style="background-color: #da9694">
        <th width="28%">负责小组</th>
        <th width="12%">psr编号</th>
        <th width="12%">严重性</th>
        <th width="12%">状态</th>
        <th width="12%">拒绝次数</th>
        <th width="12%">创建日期</th>
        <th width="12%">承诺解决日期</th>
    </tr>
        @foreach($rejectedBugCount as $key=>$value)
            @foreach($value as $item)
                @if($loop->first)
                    <tr>
                        <td rowspan="{{sizeof($value)}}">{{ $key }}</td>
                        <td>{{ $item['psr_number'] }}</td>
                        <td>{{ $item['seriousness'] }}</td>
                        <td>{{ $item['status'] }}</td>
                        <td>{{ $item['reject'] }}</td>
                        <td>{{ $item['create_time'] }}</td>
                        <td>{{ $item['pro_solve_date'] }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $item['psr_number'] }}</td>
                        <td>{{ $item['seriousness'] }}</td>
                        <td>{{ $item['status'] }}</td>
                        <td>{{ $item['reject'] }}</td>
                        <td>{{ $item['create_time'] }}</td>
                        <td>{{ $item['pro_solve_date'] }}</td>
                    </tr>
                @endif
            @endforeach
        @endforeach
    </table>
@endif