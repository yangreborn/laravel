@include('common.title', ['data' => ['size' => 2, 'value' => 'Bug总况']])

@include('common.table', ['data' => $bugcount_data['table']])

@include('common.image', ['images' => $bugcount_data['images']])