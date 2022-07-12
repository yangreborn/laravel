<table>
    <thead>
    <tr>
        <th>一级部门</th>
        <th>二级部门</th>
        <th>项目</th>
        <th>评审提交数</th>
        <th>评审处理率</th>
    </tr>
    </thead>
    <tbody>
    @for($x=0;count($data['name']) - $x;$x++)
        <tr>
            <td>{{$data['depart1'][$x]}}</td>
            <td>{{$data['depart2'][$x]}}</td>
            <td>{{$data['name'][$x]}}</td>
            <td>{{$data['review_num'][$x]}}
            @if($data['review_num_trend'][$x] > 0)
                (<font color="#FF0000">↑{{$data['review_num_trend'][$x]}}</font>)
            @elseif($data['review_num_trend'][$x] < 0)
                (<font color="green">↓{{abs($data['review_num_trend'][$x])}}</font>)
            @else
                (-)
            @endif
            </td>
            <td>{{$data['deal_rate'][$x]}}%
            @if($data['deal_rate_trend'][$x] > 0)
                (<font color="#FF0000">↑{{$data['deal_rate_trend'][$x]}}%</font>)
            @elseif($data['deal_rate_trend'][$x] < 0)
                (<font color="green">↓{{abs($data['deal_rate_trend'][$x])}}%</font>)
            @else
                (-)
            @endif
            </td>
        </tr>
    @endfor
    </tbody>
</table>