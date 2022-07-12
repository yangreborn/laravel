@if(!empty($closedBugCount))
    <h2>异常关闭Bug（非走验证流程后关闭的bug）</h2>
    <table>
    <tr style="background-color: #da9694">
        <th width="28%">负责小组</th>
        <th width="18%">psr编号</th>
        <th width="18%">严重性</th>
        <th width="18%">创建日期</th>
        <th width="18%">关闭日期</th>
    </tr>
        @foreach($closedBugCount as $key=>$value)
            @foreach($value as $item)
                @if($loop->first)
                    <tr>
                        <td rowspan="{{sizeof($value)}}">{{ $key }}</td>
                        <td>{{ $item['psr_number'] }}</td>
                        <td>{{ $item['seriousness'] }}</td>
                        <td>{{ $item['create_time'] }}</td>
                        <td>{{ $item['close_date'] }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $item['psr_number'] }}</td>
                        <td>{{ $item['seriousness'] }}</td>
                        <td>{{ $item['create_time'] }}</td>
                        <td>{{ $item['close_date'] }}</td>
                    </tr>
                @endif
            @endforeach
        @endforeach
    </table>
@endif