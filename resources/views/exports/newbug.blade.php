<table>
        <thead>
            <tr>
                <th>PSR编号</th>
                <th>所属项目</th>
                <th>标题</th>
                <th>负责小组</th>
                <th>状态</th>
                <th>出现频率</th>
                <th>创建者</th>
                <th>创建时间</th>
            </tr>
        </thead>
        @foreach($data as $item)
            <tr>
                <td>{{ $item->psr_number }}</td>
                <td>{{ $item->subject }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->group }}</td>
                <td>{{ $item->status }}</td>
                <td>{{ $item->fre_occurrence }}</td>
                <td>{{ $item->creator }}</td>
                <td>{{ $item->create_time }}</td>
            </tr>
        @endforeach
    </table>