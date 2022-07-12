@if(!empty($unresolvedProductBugCount))
    <h2>待解决Bug（按产品分布）</h2>
    <table>
        <tr  style="background-color: #da9694;">
            <th width="28%">负责小组</th>
            @foreach($unresolved_bug_products as $product_title => $value)
                <th>{{ $product_title }}</th>
            @endforeach
            <th style="background-color: #00b0f0">待解决</th>
            <th style="background-color: #00b0f0">待验证</th>
            <th style="background-color: #95b3d7">本次解决</th>
            <th style="background-color: #95b3d7">本次新增</th>

        </tr>
        @if(!empty($unresolvedProductBugCount))
            @foreach($unresolvedProductBugCount as $key=>$value)
                <tr>
                    <td>{{ $key }}</td>
                    @foreach($value as $value_data)
                        <td>{{ $value_data }}</td>
                    @endforeach
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="{{sizeof($unresolved_bug_products) + 4}}">暂无数据</td>
            </tr>
        @endif
    </table>
    @if(!empty($chartList['unresolvedResultProduct']))
        <table style="border: none;">
        <tr style="border: none;">
            <td style="border: none;">
                @if($is_preview)
                    <img src="{{ $chartList['unresolvedResultProduct'] }}" alt="image">
                @else
                    <img src="{{ $message->embedData($chartList['unresolvedResultProduct'], 'image') }}">
                @endif
            </td>
        </tr>
    </table>
    @endif
@endif
