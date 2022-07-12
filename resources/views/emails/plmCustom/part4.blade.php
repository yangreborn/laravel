@if(!empty($importance_test_bugcount_data))

    @include('common.title', ['data' => ['size' => 2, 'value' => '新增Bug（按测试人员分布）']])

    @include('common.table', ['data' => $importance_test_bugcount_data['table'], 'is_preview' => $is_preview])

@endif