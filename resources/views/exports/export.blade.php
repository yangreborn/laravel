<table>
        <thead>
            <tr>
                @foreach($thead as $item)
                    <th>{{ $item  }}</th>
                @endforeach
            </tr>
        </thead>
        @foreach($data as $item)
            <tr>
                @foreach($thead as $field)
                    <td>{{ $item->{$field} }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>