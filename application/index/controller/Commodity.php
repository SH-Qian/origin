<?php

namespace app\index\controller;

use addons\wechat\model\WechatCaptcha;
use app\common\controller\Frontend;
use app\common\library\Ems;
use app\common\library\Sms;
use app\common\model\Attachment;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Validate;
use think\Db;

/**
 * 商品列表
 */
class commodity extends Frontend
{
    protected $layout = '';
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 商品列表
     */
    public function index($id = '')
    {

        $category = get_menu();
        $cart = get_cart();

        if (empty($id)) {
            $position = '促销商品';
            $list = DB::name('commodity')->where('state', '>', 0)->where('category_ids', '>', 0)->select();
        } else {
            // 位置
            $position = DB::name('category')->where('id', '=', $id)->value('name');
            $list = DB::name('commodity')->where('state', '>', 0)->where('category_ids', '=', $id)->select();
        }

        $this->view->assign('list', $list);
        $this->view->assign('category', $category);
        $this->view->assign('cart', $cart);
        $this->view->assign('position', $position);
        $this->view->assign('title', '商品列表');

        return $this->view->fetch();
    }

    /**
     * 商品详情
     */
    public function detail($id = '')
    {
        $category = get_menu();
        $cart = get_cart();

        if (empty($id)) {
            $this->error('页面错误！', '/');
        } else {
            $data = DB::name('commodity')->where('state', '>', 0)->where('id', '=', $id)->find();
            $data['images'] = explode(',', $data['images']);
            // print_r($data);die;
            // 位置
            $position = DB::name('category')->where('id', '=', $data['category_ids'])->value('name');
        }

        $this->view->assign('data', $data);
        $this->view->assign('position', $position);
        $this->view->assign('title', '商品详情');
        $this->view->assign('category', $category);
        $this->view->assign('cart', $cart);

        return $this->view->fetch();
    }

    /**
     * 购物车
     */
    public function cart($art='')
    {
        $cart = get_cart();
       
        // 判断是否登录
        if (is_null(Session::get('uid'))) {
            $this->error('请先登录。', '/index/user/login');
        }
        $uid = Session::get('uid');

        if ($art=='check') {

            // 购物车数据
            $cartdata=DB::name('commodity')->alias('c')->leftJoin('cart a','a.cid = c.id')->field('a.id,a.cid,a.uid,a.num,a.status,c.title,c.price,c.city,c.image,c.stock')->where(['a.uid'=>$uid,'a.status'=> 0,'a.check'=> 1])->select();

            // 地址数据
            $address=DB::name('address')->field('uid,recipient,mobile,status,city,address,mail')->where(['uid'=>$uid,'status'=>1])->find();

            foreach ($cartdata as $k => $v) {
                $cartdata[$k]['total']=$v['num']*$v['price'];
            }
            $total=array_sum(array_column($cartdata,'total'));
            
            $category = get_menu();
            
            $this->view->assign('position', '购物车');
            $this->view->assign('category', $category);
            $this->view->assign('cartdata', $cartdata);
            $this->view->assign('cart', $cart);
            $this->view->assign('address', $address);
            $this->view->assign('total', $total);
            $this->view->assign('title', '核对订单');
            return $this->view->fetch('check');
            
        }elseif($art=='submit'){
            
            $category = get_menu();
            
            $this->view->assign('position', '购物车');
            $this->view->assign('category', $category);
            $this->view->assign('cart', $cart);
            
            $this->view->assign('title', '下单成功');
            return $this->view->fetch('success');

        }else{
            // 购物车数据
            $cartdata=DB::name('commodity')->alias('c')->leftJoin('cart a','a.cid = c.id')->field('a.id,a.cid,a.uid,a.num,a.status,c.title,c.price,c.city,c.image,c.stock')->where(['a.uid'=>$uid,'a.status'=> 0])->select();

            foreach ($cartdata as $k => $v) {
                $cartdata[$k]['total']=$v['num']*$v['price'];
            }
            $total=array_sum(array_column($cartdata,'total'));
            
            $category = get_menu();
            
            $this->view->assign('position', '购物车');
            $this->view->assign('category', $category);
            $this->view->assign('cartdata', $cartdata);
            $this->view->assign('cart', $cart);
            $this->view->assign('total', $total);
            $this->view->assign('title', '订单详情');
            return $this->view->fetch();

        }
        
    }

    /**
     * ajax购物车操作
     */
    public function ajax_commodity_cart()
    {
        // 判断是否登录
        if (is_null(Session::get('uid'))) {
            $this->error('请先登录。', '/index/user/login');
        }
        $uid = Session::get('uid');
        $art = $this->request->post('art');
        
        if ($this->request->isPost()) {
            switch ($art) {
                case 'add':// 添加到购物车
                    $cid = $this->request->post('cid');
                    $num = $this->request->post('num');
                    
                    if (empty($uid)||empty($cid)||empty($num)) {
                        $this->error('参数出错！');
                    }

                    // 判断是否有相同条件
                    $old_data=DB::name('cart')->where(['uid'=>$uid,'cid'=>$cid,'status'=>0])->find();
                    
                    if (empty($old_data)) {
                        // 没有就添加
                        $update=DB::name('cart')->insertGetId(['uid'=>$uid,'cid'=>$cid,'num'=>$num,'updatetime'=>time()]);
                    }else{
                        // 有就增加相应数量
                        $update=DB::name('cart')->where(['uid'=>$uid,'cid'=>$cid,'cid'=>$cid])->update(['num'=>$num+$old_data['num']]);
                    }

                    if (empty($update)) {
                        $data['code']='0';
                        $data['msg']='加入购物车失败！';
                    }else{
                        $data['code']='1';
                        $data['msg']='加入购物车成功！';
                    }

                    return $data;

                case 'update':// 修改购物车

                    $data['id'] = explode(',',trim($this->request->post('id'),','));
                    $data['num'] = explode(',',trim($this->request->post('num'),','));
                    
                    foreach ($data['id'] as $k => $v) {
                        
                        // 判断是否有相同条件
                        $old_data=DB::name('cart')->where(['uid'=>$uid,'id'=>$v])->find();
                        if (!empty($old_data)){
                            // 有就修改相应数量
                            $update=DB::name('cart')->where(['uid'=>$uid,'id'=>$v])->update(['num'=>$data['num'][$k],'check'=>1]);
                        }
                    }


                    $data['code']='1';
                    $data['msg']='修改成功！';

                    return $data;

                case 'submit':

                    $data['id'] = explode(',',trim($this->request->post('id'),','));
                    $data['num'] = explode(',',trim($this->request->post('num'),','));

                    $addID ='';
                    foreach ($data['id'] as $k => $v) {
                        
                        // 判断是否有相同条件
                        $old_data=DB::name('cart')->where(['uid'=>$uid,'id'=>$v])->find();
                        
                        if (!empty($old_data)){
                            // 有就修改相应数量
                            $update=DB::name('cart')->where(['uid'=>$uid,'id'=>$v])->update(['num'=>$data['num'][$k],'status'=>1]);
                            $addID = $addID?$addID:'';

                            if (empty($addID)) {
                                // 主订单表add
                                $userdata=DB::name('address')->where(['uid'=>$uid,'status'=>1])->find();
                                $userdata=$userdata['recipient'].','.$userdata['mobile'].','.$userdata['city'].','.$userdata['address'];

                                // 订单编号
                                $ordercode=$uid.time();
                                $ordercode=password_hash($ordercode,PASSWORD_DEFAULT);
                                $company='SH商城';
                                // print_r($userdata);die;
                                // 添加到主订单表
                                $addID=DB::name('orders')->insertGetId(['order_code'=>$ordercode,'uid'=>$uid,'company'=>$company,'status'=>0,'userdata'=>$userdata,'createtime'=>time()]);
                            }
                            
                            $cart=DB::name('commodity')->alias('c')->leftJoin('cart a','a.cid = c.id')->field('a.cid,a.num,c.title,c.price,c.image,c.parameter')->where(['a.id'=>$v])->find();
                            

                            // 添加到子订单
                            $add=DB::name('order_details')->insertGetId(['oid'=>$addID,'cid'=>$cart['cid'],'num'=>$cart['num'],'price'=>$cart['price'],'title'=>$cart['title'],'parameter'=>$cart['parameter'],'image'=>$cart['image']]);

                        }
                    }

                    $data['code']='1';
                    $data['msg']='修改成功！';

                    return $data;

                default:
                    $data['code']='0';
                    $data['msg']='art参数错误！';

                    return $data;

            }
        }
            
    }
}
