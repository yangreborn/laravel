@if(!empty($summary))
    @include('common.title', ['data' => ['size' => 2, 'value' => '总结']])
    <div style="text-align: left;">{!! $summary !!}</div>
@endif