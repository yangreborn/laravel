<table>
    <thead>
        <tr>
            <th>项目流名</th>
            <th>提交人</th>
            <th>提交总数</th>
            <th>提交前评审数</th>
            <th>提交后评审数</th>
            <th>未评审数</th>
            <th>被驳回数</th>
            <th>提交前评审率</th>
            <th>提交后评审率</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $committer_review_rate_key=>$committer_review_rate_item)
            @foreach($committer_review_rate_item as $committer_review_rate_value)
                @if($loop->first)
                    <tr>
                        <td rowspan="{{$loop->count}}">{{$committer_review_rate_key}}</td>
                        <td>{{$committer_review_rate_value['author']}}</td>
                        <td>{{$committer_review_rate_value['commits']}}</td>
                        <td>{{$committer_review_rate_value['diffs']}}</td>
                        <td>{{$committer_review_rate_value['audits']}}</td>
                        <td>{{$committer_review_rate_value['not_reviews']}}</td>
                        <td>{{$committer_review_rate_value['rejects']}}</td>
                        <td>{{$committer_review_rate_value['diffRate'] == 'N/A' ? 'N/A' : $committer_review_rate_value['diffRate'].'%'}}</td>
                        <td>{{$committer_review_rate_value['auditRate'] == 'N/A' ? 'N/A' : $committer_review_rate_value['auditRate'].'%'}}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{$committer_review_rate_value['author']}}</td>
                        <td>{{$committer_review_rate_value['commits']}}</td>
                        <td>{{$committer_review_rate_value['diffs']}}</td>
                        <td>{{$committer_review_rate_value['audits']}}</td>
                        <td>{{$committer_review_rate_value['not_reviews']}}</td>
                        <td>{{$committer_review_rate_value['rejects']}}</td>
                        <td>{{$committer_review_rate_value['diffRate'] == 'N/A' ? 'N/A' : $committer_review_rate_value['diffRate'].'%'}}</td>
                        <td>{{$committer_review_rate_value['auditRate'] == 'N/A' ? 'N/A' : $committer_review_rate_value['auditRate'].'%'}}</td>
                    </tr>
                @endif
            @endforeach
        @endforeach
    </tbody>
</table>