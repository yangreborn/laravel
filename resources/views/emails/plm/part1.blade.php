@if(!empty($importanceBugCount))
    <h2>待解决Bug（按严重性分布）</h2>
    <table>
    <tr style="background-color: #da9694">
        <th width="28%">负责小组</th>
        <th width="7.2%">1-致命</th>
        <th width="7.2%">2-严重</th>
        <th width="7.2%">3-普通</th>
        <th width="7.2%">4-较低</th>
        <th width="7.2%">5-建议</th>
        <th width="7.2%" style="background-color: #00b0f0">待解决Bug数</th>
        <th width="7.2%" style="background-color: #00b0f0">待验证</th>
        <th width="7.2%" style="background-color: #00b0f0">延期</th>
        <th width="7.2%" style="background-color: #95b3d7">本次解决</th>
        <th width="7.2%" style="background-color: #95b3d7">本次增加</th>
    </tr>
        @if(!empty($importanceBugCount))
            @foreach($importanceBugCount as $key=>$item)
                <tr>
                    <td>{{ $key }}</td>
                    <td>{{ $item['1-致命'] }}</td>
                    <td>{{ $item['2-严重'] }}</td>
                    <td>{{ $item['3-普通'] }}</td>
                    <td>{{ $item['4-较低'] }}</td>
                    <td>{{ $item['5-建议'] }}</td>
                    <td>{{ $item['unresolved'] }}</td>
                    <td>{{ $item['validate'] }}</td>
                    <td>{{ $item['delay'] }}</td>
                    <td>{{ $item['current_resolved'] }}</td>
                    <td>{{ $item['current_new'] }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="11">暂无数据</td>
            </tr>
        @endif
    </table>
    @if(!empty($chartList['importanceBugCount']))
        <table style="border: none;">
        <tr style="border: none;">
            <td style="border: none;">
                @if($is_preview)
                    <img src="{{ $chartList['importanceBugCount'] }}" alt="image">
                @else
                    <img src="{{ $message->embedData($chartList['importanceBugCount'], 'image') }}">
                @endif
            </td>
        </tr>
    </table>
    @endif
@endif