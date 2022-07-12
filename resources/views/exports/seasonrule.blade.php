<table>
    <thead>
    <tr>
        <th>指标名</th>
        <th>指标计算方式</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as $item)
        <tr>
            <td>{{ $item['name']}}</td>
            <td>{{ $item['method']}}</td>
        </tr>
    @endforeach
    </tbody>
</table>