<?php
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('phab/uploadtest', 'Api\PhabricatorController@testAvatarUpload')->name('pha-upload|phab文件上传');
Route::post('/upload', 'Api\PhabricatorController@UploadFiles');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mailable', function () {
    if (config('app.env') !== 'production') {
        $email = new App\Mail\tapdNotification(['receiver' => 'zhouyang_sybq@kedacom.com']);
        return $email;
    }
    return view('welcome');
});
