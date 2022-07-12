@if(!empty($importance_bugcount_data))

    @include('common.title', ['data' => ['size' => 2, 'value' => '待解决Bug（按严重性分布）']])

    @include('common.table', ['data' => $importance_bugcount_data['table'], 'is_preview' => $is_preview])

@endif