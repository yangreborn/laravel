<table>
    <thead>
    <tr>
        <th>项目名</th>
        <th>构建Job名称</th>
        <th>提交版本号</th>
        <th>提交人</th>
        <th>提交时间</th>
        <th>提交文件</th>
        <th>增加行数</th>
        <th>修改行数</th>
        <th>删除行数</th>
        <th>变动注释行数</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as $key => $value)
        @foreach($value['details'] as $id => $svalue)
            @foreach($svalue['commit_files'] as $sskey => $info)
                @if($loop->first)
                    <tr>
                        <td>{{ $value['projectName'] }}</td>
                        <td>{{ $key }}</td>
                        <td>{{ $svalue['commit_version'] }}</td>
                        <td>{{ $svalue['commit_person'] }}</td>
                        <td>{{ $svalue['commit_time'] }}</td>
                        <td>{{ $info['file'] }}</td>
                        <td>{{ $info['addLine']  }}</td>
                        <td>{{ $info['modLine']  }}</td>
                        <td>{{ $info['delLine']  }}</td>
                        <td>{{ $info['cmtLine']  }}</td>
                    </tr>
                @else
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>{{ $info['file'] }}</td>
                        <td>{{ $info['addLine']  }}</td>
                        <td>{{ $info['modLine']  }}</td>
                        <td>{{ $info['delLine']  }}</td>
                        <td>{{ $info['cmtLine']  }}</td>
                    </tr>
                @endif
            @endforeach
        @endforeach
    @endforeach
    </tbody>
</table>