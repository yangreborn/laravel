<table>
    <thead>
    <tr><td colspan="9">{{  $data['workflow'] }}</td></tr>
    <tr>
        <th>提交号</th>
        <th>提交人员</th>
        <th>创建评审时间</th>
        <th>评审号</th>
        <th>评审人</th>
        <th>评审时间</th>
        <th>处理方式</th>
        <th>评语</th>
        <th>是否超期</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data['detail'] as $item)
        <tr>
            <td>{{ $item['svn_id'] }}</td>
            <td>{{ $item['submitter'] }}</td>
            <td>{{ $item['create_time'] }}</td>
            <td>{{ $item['review_id']  }}</td>
            <td>{{ $item['reviewer'] }}</td>
            <td>{{ $item['handle_time'] }}</td>
            <td>{{ $item['action'] }}</td>
            <td>{{ $item['comment'] }}</td>
            <td>{{ $item['has_delayed'] === '' ? '' : ($item['has_delayed'] === true ? '是' : '否') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>