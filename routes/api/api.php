<?php

//use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
/******微信H5绑定和推送*******/
Route::any('/wechat', 'Wxinit\WeChatController@serve');
/******客服聊天*******/
Route::any('/chat', 'Wxinit\ChatController@customerList');



/******授权登录相关*******/
Route::any('/login/wx_login/{path?}/{self_id?}', 'Login\LoginController@wx_login');                     //微信H5登录使用
Route::any('/login/wxcallback', 'Login\LoginController@wxcallback');                                    //微信H5登录回调
Route::any('/login/mini_login', 'Login\LoginController@mini_login');                                    //小程序授权登录
Route::any('/login/tel_login', 'Login\LoginController@tel_login');                                      //手机号码授权登录
Route::any('/login/account_login', 'Login\LoginController@account_login');                                      //手机号码授权登录
Route::any('/anniu_show', 'Login\LoginController@anniu_show');                                          //小程序首页按钮控制
Route::any('/anniu', 'Login\LoginController@anniu');

/******支付板块模块*******/
Route::any('/pay/wx_pay', 'Pay\PayController@wx_pay');
Route::any('/pay/get_pay_info', 'Pay\PayController@get_pay_info');
/******支付回调信息处理*******/
Route::any('/notify/notify', 'Pay\NotifyController@notify');

/******邮件发送测试*******/
Route::any('/mail/mail', 'shop\MailController@mail');//邮件发送

/******只供分享抓取数据使用*******/
Route::group([
    'prefix' => 'share',"middleware"=>['frontCheck','userCheck'],'namespace'  => 'Share',
], function(){
    Route::any('/share', 'ShareController@share');
});


Route::group([
    "middleware"=>['frontCheck','userCheck'],
], function(){
    /******首页数据*******/
    Route::group([
        'prefix' => 'home','namespace'  => 'Home',
    ], function(){
        /*** 首页数据*/
        Route::any('/index', 'HomeController@index');                                 //首页数据
    });

});









