<table width="100%">
    <thead>
    @foreach($data['theads'] as $thead)
        <tr>
            @foreach($thead as $child)
                <th style="@if(!empty($child['bg_color']))background-color: {{$child['bg_color']}};@endif @if($child['width'])width: {{$child['width']}}px;@endif"
                    @if($child['width'])
                    width="{{$child['width']}}"
                    @endif
                    @if($child['rowspan'])
                    rowspan="{{$child['rowspan']}}"
                    @endif
                    @if($child['colspan'])
                    colspan="{{$child['colspan']}}"
                    @endif
                >{{ $child['value']  }}</th>
            @endforeach
        </tr>
    @endforeach
    </thead>
    <tbody>
    @foreach($data['tbodys'] as $tbody)
        <tr>
            @foreach($tbody as $child)
                @if(key_exists('type', $child))
                        @switch($child['type'])
                            @case('image')
                            @if(!is_null($child['value']))
                            <td style="@if(!empty($child['bg_color']))background-color: {{$child['bg_color']}};@endif @if(!empty($child['color']))color: {{$child['color']}};@endif"
                                @if($child['rowspan'])
                                rowspan="{{$child['rowspan']}}"
                                @endif
                                @if($child['colspan'])
                                colspan="{{$child['colspan']}}"
                                @endif
                            ><img src="{{ $is_preview ? $child['value'] : $message->embedData($child['value'], 'image') }}" alt="image"></td>
                            @else
                            <td style="@if(!empty($child['bg_color']))background-color: {{$child['bg_color']}};@endif @if(!empty($child['color']))color: {{$child['color']}};@endif"
                                @if($child['rowspan'])
                                rowspan="{{$child['rowspan']}}"
                                @endif
                                @if($child['colspan'])
                                colspan="{{$child['colspan']}}"
                                @endif
                            >--</td>
                            @endif
                            @break
                            @default
                            <td style="@if(!empty($child['bg_color']))background-color: {{$child['bg_color']}};@endif @if(!empty($child['color']))color: {{$child['color']}};@endif"
                                @if($child['rowspan'])
                                rowspan="{{$child['rowspan']}}"
                                @endif
                                @if($child['colspan'])
                                colspan="{{$child['colspan']}}"
                                @endif
                            >{{ $child['value'] }}</td>
                        @endswitch
                    @else
                        <td style="@if(!empty($child['bg_color']))background-color: {{$child['bg_color']}};@endif @if(!empty($child['color']))color: {{$child['color']}};@endif"
                            @if($child['rowspan'])
                            rowspan="{{$child['rowspan']}}"
                            @endif
                            @if($child['colspan'])
                            colspan="{{$child['colspan']}}"
                            @endif
                        >{{ $child['value'] }}</td>
                    @endif
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>