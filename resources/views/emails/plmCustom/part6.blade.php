@if(!empty($unresolve_history_data))

    @include('common.title', ['data' => ['size' => 2, 'value' => '待解决Bug（趋势变化）']])

    @include('common.table', ['data' => $unresolve_history_data['table'], 'is_preview' => $is_preview])

@endif
