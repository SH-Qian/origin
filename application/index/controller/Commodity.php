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
use think\Cache;

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
        $uid = Session::get('uid');
        // 判断是否登录
        if (is_null($uid)) {
            $this->error('请先登录。', '/index/user/login');
        }

        // 判断库存剩余是否充足
        

        // 判断是否post
        if (!$this->request->isPost()) {
            $art = $this->request->post('art');
            $art = 'submit';
            // var_dump($art);die;

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
                            $update=DB::name('cart')->where(['uid'=>$uid,'id'=>$v])->update(['num'=>$v->num,'check'=>1]);
                        }
                    }


                    $data['code']='1';
                    $data['msg']='修改成功！';

                    return $data;

                case 'submit':

                    $data['id'] = explode(',',trim($this->request->post('id'),','));
                    $data['num'] = explode(',',trim($this->request->post('num'),','));

                    $data['id'] = explode(',',trim($data['id'],','));
                    $data['num'] = explode(',',trim($data['num'],','));

                    // 判断库存状态,写入用户信息
                    $store = $this->store(json_encode($data));

                    if ($store) {
                        // 加锁
                        $lock = $this->lock("commodity");
                        // 加入QuequeList
                        if ($lock) {
                                                        
                            $result = Cache::store("redis")->list("QuequeList","rpush",$store);

                            $res = false;
                            if ($result) {
                                // 写入订单，减库存
                                $res = $this->start();
                            }

                            // 解锁
                            $this->unlock("commodity");
                        }

                        if ($res) {
                            $data['code']='1';
                            $data['msg']='下单成功！';
                        }else{
                            $data['code']='0';
                            $data['msg']='下单失败QuequeList为空！';
                        }

                        return json($data);
                        
                    }else{
                        $data['code']='0';
                        $data['msg']='库存不足！';

                        return json($data);
                    }

                default:
                    $data['code']='0';
                    $data['msg']='art参数错误！';

                    return json($data);

            }
        }
            
    }

    /**
     * 判断库存是否充足
     */
    private function store($data)
    {
        $data = json_decode($data);

        $uid = Session::get('uid');
        foreach ($data->id as $k => $v) {
            // 判断是否有相同条件
            $old_data=DB::name('cart')->alias("c")->field("co.id,co.code,c.id as cid")->leftJoin("commodity co","c.cid = co.id")->where(['c.uid'=>$uid,'c.id'=>$v])->find();

            $num = Cache::store("redis")->hash("StoreNum","hget","code:".$old_data['code']);
            if ($data->num[$k] > $num || $num == 0 ) return false;

            $result['value'][$k]['code'] = $old_data['code'];
            $result['value'][$k]['num'] = $data->num[$k];
            $result['value'][$k]['cid'] = $old_data['cid'];
            $result['value'][$k]['coid'] = $old_data['id'];
        }
        $result['userid'] = $uid;
        
        return json_encode($result);
    }

    /**
     * 订单入库，减库存
     */
    private function start()
    {
        $len = Cache::store("redis")->list("QuequeList","llen");

        if ($len>0){

            while (true) {
                
                $len = Cache::store("redis")->list("QuequeList","llen");
                
                // list
                if ($len>0) {
                    $data = Cache::store("redis")->list("QuequeList","lpop");

                    // 取出受保护的数据
                    $data=(array)$data;
                    $data=json_decode($data["\0*\0data"]);
                    // print_r($value);die;
                    // break; 

                    $addID ='';
                    foreach ($data->value as $v) {
                        
                        // 修改购物车数据
                        $update=DB::name('cart')->where(['uid'=>$data->userid,'id'=>$v->cid])->update(['num'=>$v->num,'status'=>1]);
                        // 减库存 StoreNum
                        $StoreNum = Cache::store("redis")->hash("StoreNum","hincrby",'code:'.$v->code,-$v->num);

                        $addID = $addID?$addID:'';

                        if (empty($addID)) {
                            // 主订单表add
                            $userdata=DB::name('address')->where(['uid'=>$data->userid,'status'=>1])->find();
                            $userdata=$userdata['recipient'].','.$userdata['mobile'].','.$userdata['city'].','.$userdata['address'];

                            // 订单编号
                            // $ordercode=$data->userid.time();
                            // $ordercode=password_hash($ordercode,PASSWORD_DEFAULT);
                            $ordercode=build_only_no();
                            $company='SH商城';

                            // 添加到主订单表
                            $addID=DB::name('orders')->insertGetId(['order_code'=>$ordercode,'uid'=>$data->userid,'company'=>$company,'status'=>0,'userdata'=>$userdata,'createtime'=>time()]);
                        }
                        
                        $cart=DB::name('commodity')->field('stock,inventory,freeze,title,price,image,parameter')->where(['id'=>$v->coid])->find();
                        
                        // 添加到子订单
                        $add=DB::name('order_details')->insertGetId(['oid'=>$addID,'cid'=>$v->coid,'num'=>$v->num,'price'=>$cart['price'],'title'=>$cart['title'],'parameter'=>$cart['parameter'],'image'=>$cart['image']]);

                        // 修改 mysql 生成 冻结库存 ，修改剩余库存
                        if ($StoreNum >=0 && $add) {
                            
                            if ($cart['inventory']==0) $cart['inventory'] = $cart['stock'];
                            // 剩余库存 更新
                            $inventory = $cart['inventory'] - $v->num;
                            // 冻结库存 更新
                            $freeze = $cart['freeze'] + $v->num;

                            // 修改库存数据
                            $update=DB::name('commodity')->where(['id'=>$v->coid])->update(['inventory'=>$inventory,'freeze'=>$freeze]);

                        }
                        
                    }

                    // 之前订单code
                    /* if (!empty($old_data)){
                        // 有就修改相应数量
                        $update=DB::name('cart')->where(['uid'=>$data->userid,'id'=>$v])->update(['num'=>$v->num,'status'=>1]);
                        $addID = $addID?$addID:'';

                        if (empty($addID)) {
                            // 主订单表add
                            $userdata=DB::name('address')->where(['uid'=>$data->userid,'status'=>1])->find();
                            $userdata=$userdata['recipient'].','.$userdata['mobile'].','.$userdata['city'].','.$userdata['address'];

                            // 订单编号
                            $ordercode=$data->userid.time();
                            $ordercode=password_hash($ordercode,PASSWORD_DEFAULT);
                            $ordercode=build_only_no();
                            $company='SH商城';
                            print_r($userdata);die;
                            // 添加到主订单表
                            $addID=DB::name('orders')->insertGetId(['order_code'=>$ordercode,'uid'=>$data->userid,'company'=>$company,'status'=>0,'userdata'=>$userdata,'createtime'=>time()]);
                        }
                        
                        $cart=DB::name('commodity')->alias('c')->leftJoin('cart a','a.cid = c.id')->field('a.cid,a.num,c.title,c.price,c.image,c.parameter')->where(['a.id'=>$v->code])->find();
                        
                        // 添加到子订单
                        $add=DB::name('order_details')->insertGetId(['oid'=>$addID,'cid'=>$cart['cid'],'num'=>$cart['num'],'price'=>$cart['price'],'title'=>$cart['title'],'parameter'=>$cart['parameter'],'image'=>$cart['image']]);
                    } */
                    
                }else{
                    // queuelist没有内容就跳出
                    break;
                }
                
            }

            return true;
        }else{

            return false;
        }
        
    }

    /**
     * redis 用户排队list
     */
    private function queue($redisKey,$value)
    {

        $script = <<<LUA
        local key = KEYS[1]
        local value = ARGV[1]
        local res = redis.call('rpush',key,value)

        if res then
            return true
        else
            return false
        end
        LUA;
        
        $result = Cache::store('redis')->eval($script, $redisKey, $value);

        return $result;
    }

    /**
     * redis 加锁
     * @param  string  $name           锁的标识名
     * @param  integer $timeout        循环获取锁的等待超时时间，在此时间内会一直尝试获取锁直到超时，为0表示失败后直接返回不等待
     * @param  integer $expire         当前锁的最大生存时间(秒)，必须大于0，如果超过生存时间锁仍未被释放，则系统会自动强制释放
     * @param  integer $waitIntervalUs 获取锁失败后挂起再试的时间间隔(微秒)
     * @return boolean                 
     */
    private function lock($name, $expire = 15, $timeout = 1, $waitIntervalUs = 100000)
    {
        if (is_null($name)) return false;
        // 获取当前时间
        $now = time();
        
        // 获取锁失败时等待超时时刻
        $timeoutAT = $now + $timeout;
        // 获取锁最大生存时刻
        $expireAT = $now + $expire;
        //锁名
        $redisKey = "Lock:{$name}"; 

        while (true) {
            // 将rediskey的最大生存时刻存到redis里，过了这个时刻该锁会被自动释放

            $script = <<<LUA
            local key = KEYS[1];
            local value = ARGV[2];
            local expire = ARGV[3];
            local setnx = redis.call('SETNX',key,value);
            if (setnx==1) then
                local expire = redis.call('EXPIRE',key,expire);
                return expire
            else
                return false
            end
            LUA;
            // 未用-随机生成字符串
            // $expireAT=session_create_id();

            $result = Cache::store('redis')->eval($script,[$redisKey,null],[$expireAT, $expire]);

            if ($result) { //记录加锁客户端的ID
                $this->lockID[$name] = $expireAT;
                return true;
            }
            // 如果锁存在
            // 以秒为单位，返回 key 的剩余生存时间。
            $ttl = Cache::store('redis')->ttl($redisKey);

            /*****循环请求锁部分*****/
            //如果没设置锁失败的等待时间 或者 已超过最大等待时间了，那就退出
            if ($timeout<=0 || $timeoutAT < microtime(true)) break;
            //隔 $waitIntervalUs 后继续 请求
            usleep($waitIntervalUs);
            
        }

        return false;
    }

    /**
     * redis+lua 解锁
     */
    private function unlock($name)
    {
        if (isset($this->lockID[$name])) {
            

            $script = <<<LUA
            local key = KEYS[1]
            local value = ARGV[1]
            if(redis.call('get',key)==value)
            then
            return redis.call('del',key)
            end
            LUA;
            //锁名
            $redisKey = "Lock:{$name}"; 

            $result = Cache::store('redis')->eval($script, $redisKey, $this->lockID[$name]);

            return $result;
        }

        return false;
    }

}
