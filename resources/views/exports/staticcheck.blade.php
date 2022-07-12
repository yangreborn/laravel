<table>
    <thead>
    <tr>
        <th>项目名</th>
        <th>一级部门</th>
        <th>二级部门</th>
        <th>项目经理</th>
        <th>Tscancode</th>
        <th>Pclint</th>
        <th>Findbugs</th>
        <th>Eslint</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as $item)
        <tr>
            <td>{{ $item['project'] }}</td>
            <td>{{ $item['parent'] }}</td>
            <td>{{ $item['department'] }}</td>
            <td>{{ $item['supervisor'] }}</td>
            <td>{{ $item['tscancode_data']  }}</td>
            <td>{{ $item['pclint_data']  }}</td>
            <td>{{ $item['findbugs_data']  }}</td>
            <td>{{ $item['eslint_data']  }}</td>
        </tr>
    @endforeach
    </tbody>
</table>