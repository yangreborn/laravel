<table style="border: none;">
    @foreach($images as $image)
    <tr style="border: none;">
        @foreach($image as $image_detail)
        <td style="border: none; padding: 0; vertical-align: top;">
            @if($image_detail['is_preview'])
            <img src="{{ $image_detail['image'] }}" alt="image">
            @else
            <img src="{{ $message->embedData($image_detail['image'], 'image') }}" alt="image">
            @endif
        </td>
        @endforeach
    </tr>
    @endforeach
</table>