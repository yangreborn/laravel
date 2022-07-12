<table>
        <thead>
            <tr>
                <th>PSR编号</th>
                <th>所属项目</th>
                <th>标题</th>
                <th>负责小组</th>
                <th>状态</th>
                <th>bug定位说明</th>
                <th>解决方案</th>
                <th>解决时间</th>
            </tr>
        </thead>
        @foreach($data as $item)
            <tr>
                <td>{{ $item->psr_number }}</td>
                <td>{{ $item->subject }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->group }}</td>
                <td>{{ $item->status }}</td>
                <td>{{ $item->bug_explain }}</td>
                <td>{{ $item->solution }}</td>
                <td>{{ $item->solve_time }}</td>
            </tr>
        @endforeach
    </table>