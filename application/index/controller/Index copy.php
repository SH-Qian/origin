<?php
class RedisPool
{
    private static $connections = array(); //定义一个对象池
    private static $servers = array(); //定义redis配置文件 包含所有redis 服务器
    public static function addServer($conf) //定义添加redis配置方法
    {
        foreach ($conf as $alias => $data){
            self::$servers[$alias]=$data;
        }
    }
 
    public static function getRedis($alias,$select = 0)//两个参数要连接的服务器KEY,要选择的库
    {
        if(!array_key_exists($alias,self::$connections)){  //判断连接池中是否存在
            $redis = new \Redis();
            $redis->connect(self::$servers[$alias][0],self::$servers[$alias][1]);
            self::$connections[$alias]=$redis;
            if(isset(self::$servers[$alias][2]) && self::$servers[$alias][2]!=""){
                self::$connections[$alias]->auth(self::$servers[$alias][2]);
            }
        }
        self::$connections[$alias]->select($select);
        return self::$connections[$alias];
    }
}
 
function connect_to_redis()
{
    //使用redis连接池
    $conf = array(
        'RA' => array('47.95.33.138','6379')   //定义Redis配置
    );
    RedisPool::addServer($conf); //添加Redis配置
    $redis = RedisPool::getRedis('RA',1); //连接RA，使用默认0库
    return $redis;
}
$user_id = rand(100,999);
$redis = connect_to_redis();

$lua = <<<LUA
    local num = redis.call("get", 'goods_num')
    if tonumber(num)<=0 then
        return 0
    else
        redis.call("decr", 'goods_num')
        redis.call("lpush", 'users', KEYS[1])
    end
    return 1
LUA;

$l = <<<LUA
    redis.call("lpush", 'users', KEYS[1])
    return {KEYS[1],KEYS[2],ARGV[1],ARGV[2]}
LUA;
$s = $redis->eval($lua,array($user_id),1);
if ($s == 0){
    echo "秒杀已结束";
}else{
    echo "恭喜秒杀成功";
}
// ————————————————
// 版权声明：本文为CSDN博主「IT东东歌」的原创文章，遵循CC 4.0 BY-SA版权协议，转载请附上原文出处链接及本声明。
// 原文链接：https://blog.csdn.net/u014225032/article/details/125806917


$redis = new RedisService();
 
//购买数量不得大于限购数量
$productInfo = $redis->hashGet("productInfo:".$productId);
if($productInfo['limitBuy'] < $num)
{
    echo  json_encode(["code"=>30017,"msg"=>'每人限购'.$productInfo['limitBuy']."件"]);die();
}
//加分布式锁，原子化下单流程
$storageLockKey = "storage:".$productId;
$expireTime = $redis ->lock($storageLockKey,5,200);
//判断商品库存
$storageKey = $this->getStorageKey($productId);
$storage = $redis->get($storageKey);
if($storage <= 0 || $storage < $num)
{
   $redis->unlock($storageLockKey,$expireTime);
   echo  json_encode(["code"=>30018,"msg"=>'库存不足']);die();
}

//欲购买数量+已购买数量  不得超过限购数量
$limitBuyKey = "product:limitBuy:".$productId;
if($redis->getZsetScore($limitBuyKey,"user_".$userId)+$num > $productInfo['limitBuy'])
{
    $redis->unlock($storageLockKey,$expireTime);
    echo  json_encode(["code"=>30019,"msg"=>'每人限购'.$productInfo['limitBuy']."件"]);die();
}

$orderInfo = array(
    'buyer_id' => $userId,#用户ID
    'product_id' => $productId,#产品ID
    'num' => $num,  #购买数量
    'price'=>$productInfo['price'],
    'pay_type'=>1 //在线支付

);
//订单放进队列
$orderKey = "orderList";
$orderRe = $redis->push($orderKey,serialize($orderInfo));
//下单成功
if($orderRe)
{


    //获取原有集合元素个数
    $count = $redis->countZset($limitBuyKey);
    //记录购买人和购买数量
    $redis->alterZsetScore($limitBuyKey,"user_".$userId,$num);
    //如果是第一次插入元素，设置过期时间+3天，防止内存堆积
    if(!$count)
    {
        $recordExpire = strtotime($productInfo['endTime']) - strtotime($productInfo['beginTime']) +3*24*3600;
        $redis->expire($limitBuyKey,$recordExpire);
    }

    //商品减少库存
    $redis->alterNumber($storageKey,-$num);
    $redis->unlock($storageLockKey,$expireTime);
    echo  json_encode(["code"=>200,"msg"=>'下单成功']);die();
}
