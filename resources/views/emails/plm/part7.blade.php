@if(!empty($adminGroupBugCount))
    <h2>待解决Bug（按分管小组分布）</h2>
    <table>
    <tr style="background-color: #da9694">
        <th width="28%">分管负责小组</th>
        <th width="12%">1-致命</th>
        <th width="12%">2-严重</th>
        <th width="12%">3-普通</th>
        <th width="12%">4-较低</th>
        <th width="12%">5-建议</th>
        <th width="12%" style="background-color: #00b0f0">待解决Bug数</th>
    </tr>
        @foreach($adminGroupBugCount as $key=>$item)
            <tr>
                <td>{{ $key }}</td>
                <td>{{ $item['1-致命'] }}</td>
                <td>{{ $item['2-严重'] }}</td>
                <td>{{ $item['3-普通'] }}</td>
                <td>{{ $item['4-较低'] }}</td>
                <td>{{ $item['5-建议'] }}</td>
                <td>{{ $item['unresolved'] }}</td>
            </tr>
        @endforeach
    </table>
    @if(!empty($chartList['adminGroupBugCount']))
        <table style="border: none;">
            <tr style="border: none;">
                <td style="border: none;">
                    @if($is_preview)
                        <img src="{{ $chartList['adminGroupBugCount'] }}" alt="image">
                    @else
                        <img src="{{ $message->embedData($chartList['adminGroupBugCount'], 'image') }}">
                    @endif
                </td>
            </tr>
        </table>
    @endif
@endif