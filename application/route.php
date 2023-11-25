<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;
// 商品
Route::rule('/commodity/list/:id','index/commodity/index');
Route::rule('/commodity/list','index/commodity/index');
Route::rule('/commodity/detail/:id','index/commodity/detail');
Route::rule('/commodity/cart/:art','index/commodity/cart');
Route::rule('/commodity/cart','index/commodity/cart');
// 购物车ajax
Route::rule('/ajax_commodity_cart','index/commodity/ajax_commodity_cart');
// 登录
Route::rule('/login','index/user/login');
// 订单
Route::rule('/order/:id','index/user/order');
// 订单ajax
Route::rule('/ajax_order_del','index/user/ajax_order_del');

return [
    //别名配置,别名只能是映射到控制器且访问时必须加上请求的方法
    // '__alias__'   => [
    // ],
    //变量规则
    // '__pattern__' => [
    // ],
//        域名绑定到模块
//        '__domain__'  => [
//            'admin' => 'admin',
//            'api'   => 'api',
//        ],

];
