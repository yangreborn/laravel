@if(!empty($unresolved_reviewer_bugcount_data))

    @include('common.title', ['data' => ['size' => 2, 'value' => '待解决Bug（按当前审阅者分布）']])

    @include('common.table', ['data' => $unresolved_reviewer_bugcount_data['table'], 'is_preview' => $is_preview])

@endif