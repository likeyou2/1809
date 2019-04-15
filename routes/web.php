<?php

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

Route::get('/', function () {
    return view('welcome');
});

//微信配置
Route::get('valid','WeChat\WeController@valid');

Route::post('valid','WeChat\WeController@wxPostEvent');

//access_token
Route::get('/weixin/access_token','WeChat\WeController@getAccessToken');

//微信自定义菜单
Route::get("menu","WeChat\WeController@menu");
