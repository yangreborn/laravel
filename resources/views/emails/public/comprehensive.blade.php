<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body style="font-size: 0;">
<table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" width="100%">
    @foreach($images as $image)
    <tr>
        <td style="text-align: center;">
            <img vspace="0" hspace="0" border="0" src="{{ $message->embedData($image, 'image') }}" alt="image">
        </td>
    </tr>
    @endforeach
</table>
</body>
</html>