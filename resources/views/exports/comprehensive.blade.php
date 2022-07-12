<table>
    <thead>
    <tr>
        <th>项目名</th>
        <th>流名</th>
        <th>评审人</th>
        <th>收到评审数</th>
        <th>评审处理数</th>
        <th>及时处理数</th>
        <th>驳回数</th>
        <th>有效处理数</th>
        <th>评审处理率</th>
        <th>评审及时处理率</th>
        <th>评审有效处理率</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as $name=>$item)
        $len = count($item) ?? 1;
        <tr><td>{{$name}}</td></tr>
            @foreach($item as $repo=>$value)
            @if($value)
                continue
            @endif
                @foreach($value as $reviewer)
                    <tr><td></td>
                    <td>{{$repo}}</td>
                    <td>{{$reviewer['reviewer']}}</td>
                    <td>{{$reviewer['receives']}}</td>
                    <td>{{$reviewer['deals']}}</td>
                    <td>{{$reviewer['in_time']}}</td>
                    <td>{{$reviewer['rejects']}}</td>
                    <td>{{$reviewer['valid']}}</td>
                    <td>{{$reviewer['reviewDealrate']}}</td>
                    <td>{{$reviewer['reviewIntimerate']}}</td>
                    <td>{{$reviewer['reviewValidrate']}}</td>
                    </tr>
                @endforeach
            
            @endforeach  
    @endforeach
    </tbody>
</table>