@include('emails.header')

@include('common.title', ['data' => ['size' => 1, 'value' => 'Plm Bug统计']])

@include('common.summary')

@foreach($content_to_show as $part)
    @include('emails.plmCustom.'.$part)
@endforeach

@include('emails.footer')