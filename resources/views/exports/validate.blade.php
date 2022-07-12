<table>
        <thead>
            <tr>
                <th>PSR编号</th>
                <th>所属项目</th>
                <th>标题</th>
                <th>创建者</th>
                <th>解决时间</th>
                <th>出现频率</th>
                <th>bug定位说明</th>
                <th>解决方案</th>
            </tr>
        </thead>
        @foreach($data as $item)
            <tr>
                <td>{{ $item->psr_number }}</td>
                <td>{{ $item->subject }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->creator }}</td>
                <td>{{ $item->solve_time }}</td>
                <td>{{ $item->fre_occurrence }}</td>
                <td>{{ $item->bug_explain }}</td>
                <td>{{ $item->solution }}</td>
            </tr>
        @endforeach
    </table>