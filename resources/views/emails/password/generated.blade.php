@component('mail::message')
# 度量平台密码重置

您的密码是：
{{ $password }}，<br>
请妥善保管或及时修改！
@component('mail::button', ['url' => config('app.url')])
登陆
@endcomponent

Thanks.<br>
{{ config('app.name') }}
@endcomponent
