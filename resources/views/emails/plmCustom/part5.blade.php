@if(!empty($late_bugcount_data))

    @include('common.title', ['data' => ['size' => 2, 'value' => 'Bug超期&未填写概况']])

    @include('common.table', ['data' => $late_bugcount_data['table'], 'is_preview' => $is_preview])

@endif