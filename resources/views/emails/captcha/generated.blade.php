@component('mail::message')

您的验证码是：**{{$captcha}}**

*注：验证码将在15分钟内有效*

Thanks,<br>
{{ config('app.name') }}
@endcomponent
