<table>
    <thead>
        <tr>
            @foreach($fields as $field)
                <th style="font-weight: bold">{{ $field }}</th>
            @endforeach
        </tr>
    </thead>
    @foreach($data as $item)
        <tr>
            @foreach($fields as $key=>$field)
                <th>{{ $item[$key] }}</th>
            @endforeach
        </tr>
    @endforeach
</table>