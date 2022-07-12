@if(!empty($testImportanceBugCount))
    <h2>新增Bug（按测试人员分布）</h2>
    <table>
        <tr style="background-color: #da9694">
            <th width="28%">测试人员</th>
            <th width="12%">1-致命</th>
            <th width="12%">2-严重</th>
            <th width="12%">3-普通</th>
            <th width="12%">4-较低</th>
            <th width="12%">5-建议</th>
            <th width="12%">总计</th>
        </tr>
        @if(!empty($testImportanceBugCount))
            @foreach($testImportanceBugCount as $key=>$item)
                <tr>
                    <td>{{ $key }}</td>
                    <td>{{ $item['1-致命'] }}</td>
                    <td>{{ $item['2-严重'] }}</td>
                    <td>{{ $item['3-普通'] }}</td>
                    <td>{{ $item['4-较低'] }}</td>
                    <td>{{ $item['5-建议'] }}</td>
                    <td>{{ $item['总计'] }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="7">暂无数据</td>
            </tr>
        @endif
    </table>
    @if(!empty($chartList['testImportanceBugCount']))
        <table style="border: none;">
            <tr style="border: none;">
                <td style="border: none;">
                    @if($is_preview)
                        <img src="{{ $chartList['testImportanceBugCount'] }}" alt="image">
                    @else
                        <img src="{{ $message->embedData($chartList['testImportanceBugCount'], 'image') }}">
                    @endif
                </td>
            </tr>
        </table>
    @endif
@endif