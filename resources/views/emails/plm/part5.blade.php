@if(!empty($lateBugCount))
    <h2>Bug超期&amp;未填写概况</h2>
    <table>
        <tr style="background-color: #da9694">
            <th width="28%">负责小组</th>
            <th width="12%">超期2周bug数</th>
            <th width="12%">未填写承诺解决时间bug数</th>
            <th width="12%">合计</th>
        </tr>
        @if(!empty($lateBugCount))
            @foreach($lateBugCount as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['overdue_num'] }}</td>
                    <td>{{ $item['unavailable_num'] }}</td>
                    <td>{{ $item['total'] }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="4">暂无数据</td>
            </tr>
        @endif
    </table>
    @if(!empty($chartList['lateBugCount']))
        <table style="border: none;">
            <tr style="border: none;">
                <td style="border: none;">
                    @if($is_preview)
                        <img src="{{ $chartList['lateBugCount'] }}" alt="image">
                    @else
                        <img src="{{ $message->embedData($chartList['lateBugCount'], 'image') }}">
                    @endif
                </td>
            </tr>
        </table>
    @endif
@endif