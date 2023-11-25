<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Session;
use think\Db;
use think\Cache;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    // 首页
    public function index()
    {
        $category =get_menu();
        $cart=get_cart();
        // $test='asd';

        // $cart = Cache::store('redis')->set('cart',$cart,3600);
        // $category = Cache::store('redis')->set('category',$category,3600);
        // $test = Cache::store('redis')->get('cart');
        // $test2 = Cache::store('redis')->get('category');
        // var_dump($test);
        // var_dump($test2);die;

        // 首页商品5个
        $banner=DB::name('commodity')->field('id,image')->where(['flag'=>'index'])->order('updatetime','desc')->paginate(5);
        // 推荐商品5个
        $recommend=DB::name('commodity')->field('id,title,title,price,image,unit')->where(['flag'=>'recommend'])->order('updatetime','desc')->paginate(5);
        // 促销商品5个
        $hot=DB::name('commodity')->field('id,title,title,price,image,unit')->where(['flag'=>'hot'])->order('updatetime','desc')->paginate(5);
        
        // 杯子系列
        $data['cups']=DB::name('category')->field('id,name')->where(['pid'=>'107','description'=>'cup'])->where('cid','>',0)->paginate(5);
        $data['cup']=DB::name('commodity')->field('id,title,title,price,image,unit')->where('category_ids','like','%14%')->order('updatetime','desc')->paginate(5);
        
        // 餐具系列
        $data['tablewares']=DB::name('category')->field('id,name')->where(['pid'=>'107'])->where('cid','in',[34,48])->order('updatetime','desc')->paginate(5);
        $data['tableware']=DB::name('commodity')->field('id,title,title,price,image,unit')->where('category_ids','like','%48%')->order('updatetime','desc')->paginate(5);
        // 纸浆系列
        $data['pulps']=DB::name('category')->field('id,name')->where(['pid'=>'107'])->where('cid','in',[34,42,53,59,14])->order('updatetime','desc')->paginate(5);
        $data['pulp']=DB::name('commodity')->field('id,title,title,price,image,unit')->where('category_ids','like','%34%')->order('updatetime','desc')->paginate(5);

        $position='';

        $this->view->assign('position', $position);
        $this->view->assign('data', $data);
        $this->view->assign('category', $category);
        $this->view->assign('cart', $cart);
        $this->view->assign('banner', $banner);
        $this->view->assign('recommend', $recommend);
        $this->view->assign('hot', $hot);
        $this->view->assign('title', "SH商城");

        return $this->view->fetch();
    }

    // 联系我们
    public function contact()
    {      
        $this->view->assign('title', "联系我们");

        return $this->view->fetch();
    }

}
