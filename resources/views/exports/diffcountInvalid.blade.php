<table>
    <thead>
    <tr>
        <th>项目名</th>
        <th>构建Job名称</th>
        <th>提交版本号</th>
        <th>提交人</th>
        <th>提交时间</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as $key => $value)
        @if(!$value['details'])
            <tr>
                <td>{{ $value['projectName'] }}</td>
                <td>{{ $key }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @else
            @foreach($value['details'] as $id => $svalue)
                @if($loop->first)
                    <tr>
                        <td>{{ $value['projectName'] }}</td>
                        <td>{{ $key }}</td>
                        <td>{{ $svalue['commit_version'] }}</td>
                        <td>{{ $svalue['commit_person'] }}</td>
                        <td>{{ $svalue['commit_time'] }}</td>
                    </tr>
                @else
                    <tr>
                        <td></td>
                        <td></td>
                        <td>{{ $svalue['commit_version'] }}</td>
                        <td>{{ $svalue['commit_person'] }}</td>
                        <td>{{ $svalue['commit_time'] }}</td>
                    </tr>
                @endif
            @endforeach
        @endif
    @endforeach
    </tbody>
</table>