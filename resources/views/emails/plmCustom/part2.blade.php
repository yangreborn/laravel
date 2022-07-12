@if(!empty($unresolve_product_bugcount_data))

    @include('common.title', ['data' => ['size' => 2, 'value' => '待解决Bug（按产品分布）']])

    @include('common.table', ['data' => $unresolve_product_bugcount_data['table'], 'is_preview' => $is_preview])

@endif