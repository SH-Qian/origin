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

// use think\Cache\Driver\Redis;

/**
 * 会员中心
 */
class User extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['login', 'register', 'third'];
    protected $noNeedRight = ['*'];
    protected $lockID; //记录加锁客户端的ID


    public function _initialize()
    {
        parent::_initialize();
        $auth = $this->auth;

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'), '/');
        }

        //监听注册登录退出的事件
        Hook::add('user_login_successed', function ($user) use ($auth) {
            $expire = input('post.keeplogin') ? 30 * 86400 : 0;
            Cookie::set('uid', $user->id, $expire);
            Cookie::set('token', $auth->getToken(), $expire);
            Session::set('uid', $user->id);
            Session::set('token', $auth->getToken());
        });
        Hook::add('user_register_successed', function ($user) use ($auth) {
            Cookie::set('uid', $user->id);
            Cookie::set('token', $auth->getToken());
            Session::set('uid', $user->id);
            Session::set('token', $auth->getToken());
        });
        Hook::add('user_delete_successed', function ($user) use ($auth) {
            Cookie::delete('uid');
            Cookie::delete('token');
            Session::delete('uid');
            Session::delete('token');
        });
        Hook::add('user_logout_successed', function ($user) use ($auth) {
            Cookie::delete('uid');
            Cookie::delete('token');
            Session::delete('uid');
            Session::delete('token');
        });
    }

    /**
     * 会员中心
     */
    public function index()
    {
        $this->view->assign('title', __('User center'));
        return $this->view->fetch();
    }

    /**
     * 我的订单
     */
    /* public function order($id='')
    {
        $cart=get_cart();

        if (!empty($id)) {
            
            $order_details = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->field('d.id,d.title,o.order_code,o.company,o.status,o.userdata,o.pay_type,o.createtime,d.price,d.image')->where(['d.id' => $id])->find();

            switch ($order_details['status']) {
                case '0':
                    $order_details['status_type']='待支付';
                    break;
                case '1':
                    $order_details['status_type']='已取消';
                    break;
                case '2':
                    $order_details['status_type']='待发货';
                    break;
                case '3':
                    $order_details['status_type']='已发货';
                    break;
                case '4':
                    $order_details['status_type']='售后中';
                    break;
                case '5':
                    $order_details['status_type']='已完成';
                    break;
                case '6':
                    $order_details['status_type']='退款成功';
                    break;
                }

            $category = get_menu();
            $this->view->assign('title', __('我的订单'));
            $this->view->assign('position', '我的订单');
            $this->view->assign('category', $category);
            $this->view->assign('cart', $cart);
            $this->view->assign('order_details', $order_details);

            return $this->view->fetch('order_details');
            
        }else{

            // 用户
            $uid = Session::get('uid');
            $username = DB::name('user')->where(['id' => $uid])->value('username');
            // 订单
            $ord_data = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->field('d.id,o.company,o.status,o.userdata,o.pay_type,o.createtime,d.price,d.image')->where(['o.uid' => $uid])->paginate(10);

            $pager = $ord_data->render();
            $ord_data = $ord_data->all();

            // 状态 是否支付：0-未支付、1-已取消、2-已支付,待发货、3-已发货、4-退款/售后、5-已收货,已完成、6-退款成功、7-已删除
            foreach ($ord_data as $k => $v) {
                $ord_data[$k]['name']=explode(',',$v['userdata'])[0];
                switch ($v['status']) {
                    case '0':
                        $ord_data[$k]['status_type']='待支付';
                        break;
                    case '1':
                        $ord_data[$k]['status_type']='已取消';
                        break;
                    case '2':
                        $ord_data[$k]['status_type']='待发货';
                        break;
                    case '3':
                        $ord_data[$k]['status_type']='已发货';
                        break;
                    case '4':
                        $ord_data[$k]['status_type']='售后中';
                        break;
                    case '5':
                        $ord_data[$k]['status_type']='已完成';
                        break;
                    case '6':
                        $ord_data[$k]['status_type']='退款成功';
                        break;
                    default:
                        $ord_data[$k]['status_type']='已删除';
                        break;
                }

            }

            $number['dfh'] = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->where(['o.uid' => $uid,'o.status' => 2])->count();
            $number['dsh'] = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->where(['o.uid' => $uid,'o.status' => 3])->count();
            $number['dzf'] = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->where(['o.uid' => $uid,'o.status' => 0])->count();
            
            $category = get_menu();
            $this->view->assign('title', __('我的订单'));
            $this->view->assign('position', '我的订单');
            $this->view->assign('category', $category);
            $this->view->assign('cart', $cart);
            $this->view->assign('username', $username);
            $this->view->assign('ord_data', $ord_data);
            $this->view->assign('pager', $pager);
            $this->view->assign('number', $number);

            return $this->view->fetch();
        }

    } */
    public function order($id = '')
    {
        $cart = get_cart();
        $name = "";
        $result =$this->lock($name);
        // 加锁成功，执行业务逻辑
        try {
            if ($result) {

                if ($cart) {
                    echo '1111';
                }

                // 业务逻辑完成，解锁
                $lock = $this->unlock('test');
            }
            
        } catch (\Throwable $th) {
            //throw $th;
        }       
            


        if (!empty($id)) {

            $order_details = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->field('d.id,d.title,o.order_code,o.company,o.status,o.userdata,o.pay_type,o.createtime,d.price,d.image')->where(['d.id' => $id])->find();

            switch ($order_details['status']) {
                case '0':
                    $order_details['status_type'] = '待支付';
                    break;
                case '1':
                    $order_details['status_type'] = '已取消';
                    break;
                case '2':
                    $order_details['status_type'] = '待发货';
                    break;
                case '3':
                    $order_details['status_type'] = '已发货';
                    break;
                case '4':
                    $order_details['status_type'] = '售后中';
                    break;
                case '5':
                    $order_details['status_type'] = '已完成';
                    break;
                case '6':
                    $order_details['status_type'] = '退款成功';
                    break;
            }

            $category = get_menu();
            $this->view->assign('title', __('我的订单'));
            $this->view->assign('position', '我的订单');
            $this->view->assign('category', $category);
            $this->view->assign('cart', $cart);
            $this->view->assign('order_details', $order_details);

            return $this->view->fetch('order_details');

        } else {

            // 用户
            $uid = Session::get('uid');
            $username = DB::name('user')->where(['id' => $uid])->value('username');

            // 订单
            $ord_data = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->field('d.id,o.company,o.status,o.userdata,o.pay_type,o.createtime,d.price,d.image')->where(['o.uid' => $uid])->paginate(10);

            $pager = $ord_data->render();
            $ord_data = $ord_data->all();

            // 状态 是否支付：0-未支付、1-已取消、2-已支付,待发货、3-已发货、4-退款/售后、5-已收货,已完成、6-退款成功、7-已删除
            foreach ($ord_data as $k => $v) {
                $ord_data[$k]['name'] = explode(',', $v['userdata'])[0];
                switch ($v['status']) {
                    case '0':
                        $ord_data[$k]['status_type'] = '待支付';
                        break;
                    case '1':
                        $ord_data[$k]['status_type'] = '已取消';
                        break;
                    case '2':
                        $ord_data[$k]['status_type'] = '待发货';
                        break;
                    case '3':
                        $ord_data[$k]['status_type'] = '已发货';
                        break;
                    case '4':
                        $ord_data[$k]['status_type'] = '售后中';
                        break;
                    case '5':
                        $ord_data[$k]['status_type'] = '已完成';
                        break;
                    case '6':
                        $ord_data[$k]['status_type'] = '退款成功';
                        break;
                    default:
                        $ord_data[$k]['status_type'] = '已删除';
                        break;
                }

            }

            $number['dfh'] = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->where(['o.uid' => $uid, 'o.status' => 2])->count();
            $number['dsh'] = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->where(['o.uid' => $uid, 'o.status' => 3])->count();
            $number['dzf'] = DB::name('orders')->alias('o')->leftJoin('order_details d', 'd.oid = o.id')->where(['o.uid' => $uid, 'o.status' => 0])->count();

            $category = get_menu();
            $this->view->assign('title', __('我的订单'));
            $this->view->assign('position', '我的订单');
            $this->view->assign('category', $category);
            $this->view->assign('cart', $cart);
            $this->view->assign('username', $username);
            $this->view->assign('ord_data', $ord_data);
            $this->view->assign('pager', $pager);
            $this->view->assign('number', $number);

            return $this->view->fetch();
        }

    }

    /**
     * ajax订单操作
     */
    public function ajax_order_del()
    {
        // 判断是否登录
        if (is_null(Session::get('uid'))) {
            $this->error('请先登录。', '/index/user/login');
        }
        $uid = Session::get('uid');
        $art = $this->request->post('art');

        if ($art == 'del') {
            $id = trim($this->request->post('id'));

            // 判断是否有相同条件
            $c_oid = DB::name('order_details')->where(['id' => $id])->value('oid');
            $old_data = DB::name('order')->where(['id' => $c_oid, 'status' => 0])->value('id');

            if (!empty($old_data) && !empty($c_oid)) {
                // 有就修改相应数量
                $update = DB::name('order')->where(['uid' => $uid, 'id' => $c_oid])->update(['status' => 1]);
            }

            $data['code'] = '1';
            $data['msg'] = '取消成功！';

            return $data;
        }

        $data['code'] = '0';
        $data['msg'] = '取消失败！';

        return $data;

    }

    /**
     * redis+lua 秒杀实现/库存删减
     */
    private function redis_lua($goodsId, $userId)
    {
        $key = "goods:{$goodsId}"; // 商品的唯一标识符，作为锁的key
        $timeout = 10; // 超时时间，避免死锁

        // 加锁
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379); // 连接Redis
        $lock = $redis->set($key, $userId, ['NX', 'EX' => $timeout]);

        // 检查是否成功加锁
        if (!$lock) {
            echo "Failed to acquire lock";
            return;
        }

        // 进行秒杀操作
        $goods = $redis->hgetall($key);
        if (empty($goods) || $goods['stock'] <= 0) {
            echo "Goods sold out";
        } else {
            $goods['stock'] -= 1;
            $redis->hmset($key, $goods);
            echo "Buy goods successfully";
        }

        // 释放锁
        $redis->del($key);

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



    /**
     * 注册会员
     */
    public function register()
    {
        $url = $this->request->request('url', '', 'trim');
        if ($this->auth->id) {
            $this->success(__('You\'ve logged in, do not login again'), $url ? $url : url('user/index'));
        }
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $email = $this->request->post('email');
            $mobile = $this->request->post('mobile', '');
            $captcha = $this->request->post('captcha');
            $token = $this->request->post('__token__');
            $rule = [
                'username' => 'require|length:3,30',
                'password' => 'require|length:6,30',
                'email' => 'require|email',
                'mobile' => 'regex:/^1\d{10}$/',
                '__token__' => 'require|token',
            ];

            $msg = [
                'username.require' => 'Username can not be empty',
                'username.length' => 'Username must be 3 to 30 characters',
                'password.require' => 'Password can not be empty',
                'password.length' => 'Password must be 6 to 30 characters',
                'email' => 'Email is incorrect',
                'mobile' => 'Mobile is incorrect',
            ];
            $data = [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'mobile' => $mobile,
                '__token__' => $token,
            ];
            //验证码
            $captchaResult = true;
            $captchaType = config("fastadmin.user_register_captcha");
            if ($captchaType) {
                if ($captchaType == 'mobile') {
                    $captchaResult = Sms::check($mobile, $captcha, 'register');
                } elseif ($captchaType == 'email') {
                    $captchaResult = Ems::check($email, $captcha, 'register');
                } elseif ($captchaType == 'wechat') {
                    $captchaResult = WechatCaptcha::check($captcha, 'register');
                } elseif ($captchaType == 'text') {
                    $captchaResult = \think\Validate::is($captcha, 'captcha');
                }
            }
            if (!$captchaResult) {
                $this->error(__('Captcha is incorrect'));
            }
            $validate = new Validate($rule, $msg);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
            }
            if ($this->auth->register($username, $password, $email, $mobile)) {
                $this->success(__('Sign up successful'), $url ? $url : url('user/index'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }
        //判断来源
        $referer = $this->request->server('HTTP_REFERER');
        if (
            !$url && (strtolower(parse_url($referer, PHP_URL_HOST)) == strtolower($this->request->host()))
            && !preg_match("/(user\/login|user\/register|user\/logout)/i", $referer)
        ) {
            $url = $referer;
        }
        $this->view->assign('captchaType', config('fastadmin.user_register_captcha'));
        $this->view->assign('url', $url);
        $this->view->assign('title', __('Register'));
        return $this->view->fetch();
    }

    /**
     * 会员登录
     */
    public function login()
    {
        $url = $this->request->request('url', '', 'trim');
        if ($this->auth->id) {
            $this->success(__('You\'ve logged in, do not login again'), $url ? $url : url('user/index'));
        }
        if ($this->request->isPost()) {
            $account = $this->request->post('account');
            $password = $this->request->post('password');
            $keeplogin = (int) $this->request->post('keeplogin');
            $token = $this->request->post('__token__');
            $rule = [
                'account' => 'require|length:3,50',
                'password' => 'require|length:6,30',
                '__token__' => 'require|token',
            ];

            $msg = [
                'account.require' => 'Account can not be empty',
                'account.length' => 'Account must be 3 to 50 characters',
                'password.require' => 'Password can not be empty',
                'password.length' => 'Password must be 6 to 30 characters',
            ];
            $data = [
                'account' => $account,
                'password' => $password,
                '__token__' => $token,
            ];
            $validate = new Validate($rule, $msg);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
                return false;
            }
            if ($this->auth->login($account, $password)) {
                $this->success(__('Logged in successful'), $url ? $url : url('user/index'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }
        //判断来源
        $referer = $this->request->server('HTTP_REFERER');
        if (
            !$url && (strtolower(parse_url($referer, PHP_URL_HOST)) == strtolower($this->request->host()))
            && !preg_match("/(user\/login|user\/register|user\/logout)/i", $referer)
        ) {
            $url = $referer;
        }
        $this->view->assign('url', $url);
        $this->view->assign('title', __('Login'));
        return $this->view->fetch();
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        if ($this->request->isPost()) {
            $this->token();
            //退出本站
            $this->auth->logout();
            $this->success(__('Logout successful'), url('user/index'));
        }
        $html = "<form id='logout_submit' name='logout_submit' action='' method='post'>" . token() . "<input type='submit' value='ok' style='display:none;'></form>";
        $html .= "<script>document.forms['logout_submit'].submit();</script>";

        return $html;
    }

    /**
     * 个人信息
     */
    public function profile()
    {
        $this->view->assign('title', __('Profile'));
        return $this->view->fetch();
    }

    /**
     * 修改密码
     */
    public function changepwd()
    {
        if ($this->request->isPost()) {
            $oldpassword = $this->request->post("oldpassword");
            $newpassword = $this->request->post("newpassword");
            $renewpassword = $this->request->post("renewpassword");
            $token = $this->request->post('__token__');
            $rule = [
                'oldpassword' => 'require|regex:\S{6,30}',
                'newpassword' => 'require|regex:\S{6,30}',
                'renewpassword' => 'require|regex:\S{6,30}|confirm:newpassword',
                '__token__' => 'token',
            ];

            $msg = [
                'renewpassword.confirm' => __('Password and confirm password don\'t match')
            ];
            $data = [
                'oldpassword' => $oldpassword,
                'newpassword' => $newpassword,
                'renewpassword' => $renewpassword,
                '__token__' => $token,
            ];
            $field = [
                'oldpassword' => __('Old password'),
                'newpassword' => __('New password'),
                'renewpassword' => __('Renew password')
            ];
            $validate = new Validate($rule, $msg, $field);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), null, ['token' => $this->request->token()]);
                return false;
            }

            $ret = $this->auth->changepwd($newpassword, $oldpassword);
            if ($ret) {
                $this->success(__('Reset password successful'), url('user/login'));
            } else {
                $this->error($this->auth->getError(), null, ['token' => $this->request->token()]);
            }
        }
        $this->view->assign('title', __('Change password'));
        return $this->view->fetch();
    }

    public function attachment()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $mimetypeQuery = [];
            $where = [];
            $filter = $this->request->request('filter');
            $filterArr = (array) json_decode($filter, true);
            if (isset($filterArr['mimetype']) && preg_match("/(\/|\,|\*)/", $filterArr['mimetype'])) {
                $this->request->get(['filter' => json_encode(array_diff_key($filterArr, ['mimetype' => '']))]);
                $mimetypeQuery = function ($query) use ($filterArr) {
                    $mimetypeArr = array_filter(explode(',', $filterArr['mimetype']));
                    foreach ($mimetypeArr as $index => $item) {
                        $query->whereOr('mimetype', 'like', '%' . str_replace("/*", "/", $item) . '%');
                    }
                };
            } elseif (isset($filterArr['mimetype'])) {
                $where['mimetype'] = ['like', '%' . $filterArr['mimetype'] . '%'];
            }

            if (isset($filterArr['filename'])) {
                $where['filename'] = ['like', '%' . $filterArr['filename'] . '%'];
            }

            if (isset($filterArr['createtime'])) {
                $timeArr = explode(' - ', $filterArr['createtime']);
                $where['createtime'] = ['between', [strtotime($timeArr[0]), strtotime($timeArr[1])]];
            }
            $search = $this->request->get('search');
            if ($search) {
                $where['filename'] = ['like', '%' . $search . '%'];
            }

            $model = new Attachment();
            $offset = $this->request->get("offset", 0);
            $limit = $this->request->get("limit", 0);
            $total = $model
                ->where($where)
                ->where($mimetypeQuery)
                ->where('user_id', $this->auth->id)
                ->order("id", "DESC")
                ->count();

            $list = $model
                ->where($where)
                ->where($mimetypeQuery)
                ->where('user_id', $this->auth->id)
                ->order("id", "DESC")
                ->limit($offset, $limit)
                ->select();
            $cdnurl = preg_replace("/\/(\w+)\.php$/i", '', $this->request->root());
            foreach ($list as $k => &$v) {
                $v['fullurl'] = ($v['storage'] == 'local' ? $cdnurl : $this->view->config['upload']['cdnurl']) . $v['url'];
            }
            unset($v);
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        $mimetype = $this->request->get('mimetype', '');
        $mimetype = substr($mimetype, -1) === '/' ? $mimetype . '*' : $mimetype;
        $this->view->assign('mimetype', $mimetype);
        $this->view->assign("mimetypeList", \app\common\model\Attachment::getMimetypeList());
        return $this->view->fetch();
    }
}
