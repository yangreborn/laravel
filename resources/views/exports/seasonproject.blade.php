<table>
    <thead>
    <tr>
        <th>部门名</th>
        <th>项目名</th>
        <th>项目类别</th>
        <th>基本指标名</th>
        <th>指标达标值</th>
        <th>实际指标值</th>
        <th>是否达标</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as $item)
        @foreach($item as $index)
            <tr>
            @if(!empty($index['department']))
                <td rowspan="{{count($item)}}">{{$index['department']}}</td>
            @endif
            @if(!empty($index['project']))
                <td rowspan="{{$index['project']['num']}}">{{$index['project']['name']}}</td>
                <td rowspan="{{$index['project']['num']}}">{{$index['project']['class']}}</td>
            @endif
            <td>{{$index['index']}}</td>
            <td>{{$index['standard']}}</td>
            <td>{{$index['actual_value']}}</td>
            <td>{{$index['reach']}}</td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>