@if(!empty($unresolvedReviewerBugCount))
    <h2>待解决bug（按当前审阅者分布）</h2>
    <table>
        <tr style="background-color: #da9694">
            <th width="28%">审阅人</th>
            <th>新建</th>
            <th>未分配</th>
            <th>审核</th>
            <th>Assign</th>
            <th>Resolve</th>
            <th style="background-color: #00b0f0">待解决Bug数</th>
        </tr>
        @if(!empty($unresolvedReviewerBugCount['keys']))
            @foreach($unresolvedReviewerBugCount['keys'] as $key)
                <tr>
                    <td>{{ $key }}</td>
                    @foreach($unresolvedReviewerBugCount['status'] as $statu)
                        <td>{{ $unresolvedReviewerBugCount[$key][$statu] }}</td>
                    @endforeach
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="{{sizeof($unresolvedReviewerBugCount['status']) + 1}}">暂无数据</td>
            </tr>
        @endif
    </table>
    @if($chartList['unresolvedResultReviewer'])
        <table style="border: none;">
        <tr style="border: none;">
            <td style="border: none;">
                @if($is_preview)
                    <img src="{{ $chartList['unresolvedResultReviewer'] }}" alt="image">
                @else
                    <img src="{{ $message->embedData($chartList['unresolvedResultReviewer'], 'image') }}" alt="image">
                @endif
            </td>
        </tr>
    </table>
    @endif
@endif