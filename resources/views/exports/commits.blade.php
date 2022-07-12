<table>
    <thead>
    <tr>
        <th>服务器IP</th>
        <th>SVN仓库流名称</th>
        <th>提交前评审号</th>
        <th>人员</th>
        <th>角色</th>
        <th>提交动作</th>
        <th>时间</th>
        <th>详情</th>
        <th>url地址</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as $item)
        @foreach($item['detail'] as $cell)
            @if($loop->first)
                <tr>
                    <td rowspan="{{sizeof($item['detail'])}}">{{ $item['server_ip'] }}</td>
                    <td rowspan="{{sizeof($item['detail'])}}">{{ $item['workflow'] }}</td>
                    <td>{{ $cell->review_id }}</td>
                    <td>{{ $cell->author }}</td>
                    <td>{{ in_array($cell->action, config('api.phabricator_submitter_action')) ? 'submitter' : 'reviewer'}}</td>
                    <td>{{ $cell->action }}</td>
                    <td>{{ $cell->action_time }}</td>
                    <td>{{ $cell->action === 'create' ? '' : $cell->comment }}</td>
                    <td>{{ $cell->url }}</td>
                </tr>
            @else
                <tr>
                    <td>{{ $cell->review_id }}</td>
                    <td>{{ $cell->author }}</td>
                    <td>{{ in_array($cell->action, config('api.phabricator_submitter_action')) ? 'submitter' : 'reviewer'}}</td>
                    <td>{{ $cell->action }}</td>
                    <td>{{ $cell->action_time }}</td>
                    <td>{{ $cell->action === 'create' ? '' : $cell->comment }}</td>
                    <td>{{ $cell->url }}</td>
                </tr>
            @endif
        @endforeach
    @endforeach
    </tbody>
</table>