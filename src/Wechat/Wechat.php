<?php namespace Overtrue\Wechat;

use Exception;
use Overtrue\Wechat\Utils\Bag;
use Overtrue\Wechat\Utils\Http;
use Overtrue\Wechat\Traits\Loggable;
use Overtrue\Wechat\Traits\Instanceable;

class Wechat {

    /**
     * API列表
     *
     * @var array
     */
    protected $apis = array(
            'token.get'           => 'https://api.weixin.qq.com/cgi-bin/token',

            'auth.url'            => 'https://open.weixin.qq.com/connect/oauth2/authorize',

            'file.upload'         => 'http://file.api.weixin.qq.com/cgi-bin/media/upload',
            'file.get'            => 'http://file.api.weixin.qq.com/cgi-bin/media/upload',

            'menu.create'         => 'https://api.weixin.qq.com/cgi-bin/menu/create',
            'menu.get'            => 'https://api.weixin.qq.com/cgi-bin/menu/get',
            'menu.delete'         => 'https://api.weixin.qq.com/cgi-bin/menu/delete',

            'message.send'        => 'https://api.weixin.qq.com/cgi-bin/message/custom/send',

            'group.create'        => 'https://api.weixin.qq.com/cgi-bin/groups/create',
            'group.update'        => 'https://api.weixin.qq.com/cgi-bin/groups/update',
            'group.get'           => 'https://api.weixin.qq.com/cgi-bin/groups/get',
            'group.member.update' => 'https://api.weixin.qq.com/cgi-bin/groups/members/update',

            'user.group'          => 'https://api.weixin.qq.com/cgi-bin/groups/getid',
            'user.get'            => 'https://api.weixin.qq.com/cgi-bin/user/info',
            'user.list'           => 'https://api.weixin.qq.com/cgi-bin/user/get',
            'user.remark'         => 'https://api.weixin.qq.com/cgi-bin/user/info/updateremark',
            'user.oauth.get'      => 'https://api.weixin.qq.com/sns/userinfo',

            'qrcode.create'       => 'https://mp.weixin.qq.com/cgi-bin/qrcode/create',
            'qrcode.show'         => 'https://mp.weixin.qq.com/cgi-bin/showqrcode',

            'template.set'        => '/cgi-bin/template/api_set_industry',
        );

    /**
     * 选项
     *
     * @var Overtrue\Wechat\Utils\Bag
     */
    protected $options;

    /**
     * 服务端
     *
     * @var Overtrue\Wechat\Server
     */
    protected $server;

    /**
     * 客户端
     *
     * @var Overtrue\Wechat\Client
     */
    protected $client;

    /**
     * 错误处理器
     *
     * @var callable
     */
    protected $errorHandler;

    /**
     * 缓存写入器
     *
     * @var callable
     */
    protected $cacheWriter;

    /**
     * 缓存读取器
     *
     * @var callable
     */
    protected $cacheReader;

    /**
     * access_token
     *
     * @var string
     */
    protected $accessToken;

    /**
     * 自动添加access_token
     *
     * @var string
     */
    static protected $autoRequestToken = true;

    /**
     * Wechat实例
     *
     * @var Overtrue\Wechat
     */
    protected static $instance = null;

    private function __construct() {}
    private function __clone() {}


    /**
     * 创建实例
     *
     * @param array $$options
     *
     * @return mixed
     */
    static public function make($options)
    {
        !is_null(static::$instance) || static::$instance = new static;

        if (empty($options['app_id'])
            || empty($options['secret'])
            || empty($options['token'])) {
            throw new Exception("配置至少包含三项'app_id'、'secret'、'token'且不能为空！");
        }

        static::$instance->options = new Bag($options);

        set_exception_handler(function($e){
            if (static::$instance->errorHandler) {
                return call_user_func_array(static::$instance->errorHandler, array($e));
            }

            throw $e;
        });

        return static::$instance;
    }

    /**
     * 错误处理器
     *
     * @param callback $handler
     *
     * @return void
     */
    public function error($handler)
    {
        is_callable($handler) && $this->errorHandler = $handler;
    }

    /**
     * 获取服务器端实例
     *
     * @return Overtrue\Wechat\Server
     */
    public function getServer()
    {
        if (is_null($this->server)) {
            $this->server = new Server($this->options);
        }

        return $this->server;
    }

    /**
     * 获取客户器端实例
     *
     * @return Overtrue\Wechat\Client
     */
    public function getClient()
    {
        if (is_null($this->client)) {
            $this->client = new Client($this->options);
        }

        return $this->client;
    }

    /**
     * 设置缓存写入器
     *
     * @param callable $handler
     *
     * @return void
     */
    public function cacheWriter($handler)
    {
        is_callable($handler) && $this->cacheWriter = $handler;
    }

    /**
     * 设置缓存读取器
     *
     * @param callable $handler
     *
     * @return void
     */
    public function cacheReader($handler)
    {
        is_callable($handler) && $this->cacheReader = $handler;
    }

    /**
     * 发起一个HTTP/HTTPS的请求
     * @param string $method 请求类型   GET | POST
     * @param string $url    接口的URL
     * @param array  $params 接口参数
     * @param array  $files  图片信息
     *
     * @return array
     */
    static public function request($method, $url, array $params = array(), array $files = array())
    {
        $response = Http::request($method, $url, $params, array(), $files);

        if (empty($response)) {
            throw new Exception("请求失败，无返回值.");
        }

        $contents = json_decode($response, true);

        if(!empty($contents['errcode'])){
            throw new Exception("[{$contents['errcode']}] ".$contents['errmsg'], $contents['errcode']);
        }

        return $contents;
    }

    /**
     * 自动添加access_token参数
     *
     * @param boolean $status
     *
     * @return void
     */
    public function autoRequestToken($status)
    {
        self::$autoRequestToken = (bool) $status;
    }

    /**
     * 写入/读取缓存
     *
     * @param string  $key
     * @param mixed   $value
     * @param integer $lifetime
     *
     * @return mixed
     */
    protected function cache($key, $value = null, $lifetime = 7200)
    {
        if ($value) {
            $handler = $this->cacheWriter ? : array($this, 'fileCacheWriter');
        } else {
            $handler = $this->cacheReader ? : array($this, 'fileCacheReader');
        }

        return call_user_func_array($handler, array($key, $value, $lifetime));
    }

    /**
     * 获取access_token
     *
     * @return string
     */
    protected function getAccessToken()
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $key = 'overtrue.wechat.access_token';

        if ($cached = $this->cache($key)) {
            return $cached;
        }

        // 关闭自动加access_token参数
        $this->autoRequestToken(false);

        $url = static::makeUrl('token.get', array(
                                            'appid'      => $this->options->app_id,
                                            'secret'     => $this->options->secret,
                                            'grant_type' => 'client_credential',
                                           ));
        // 开启自动加access_token参数
        $this->autoRequestToken(true);

        $token = static::request('GET', $url);

        $this->cache($key, $token['access_token'], $token['expires_in']);

        return $token['access_token'];
    }

    /**
     * 生成url
     *
     * @param string $name    api名称
     * @param array  $queries 查询
     *
     * @return string
     */
    static public function makeUrl($name, $queries = array())
    {
        if (self::$autoRequestToken) {
            $queries['access_token'] = self::$instance->getAccessToken();
        }

        return self::$instance->apis[$name] . (empty($queries) ? '' : ('?' . http_build_query($queries)));
    }

    /**
     * 默认的缓存写入器
     *
     * @param string  $key
     * @param mixed   $value
     * @param integer $lifetime
     *
     * @return void
     */
    protected function fileCacheWriter($key, $value, $lifetime = 7200)
    {
        $data = array(
                'token'      => $value,
                'expired_at' => time() + $lifetime - 2, //XXX: 减去2秒更可靠的说
                );

        if (!file_put_contents($this->getCacheFile($key), serialize($data))) {
            throw new Exception("Access toekn 缓存失败！");
        }
    }

    /**
     * 默认的缓存读取器
     *
     * @param string   $key
     *
     * @return void
     */
    protected function fileCacheReader($key)
    {
        $file = $this->getCacheFile($key);

        if (file_exists($file) && $token = unserialize(file_get_contents($file))) {
            return $token['expired_at'] > time() ? $token['token'] : null;
        }

        return null;
    }

    /**
     * 获取缓存文件名
     *
     * @param string $key
     *
     * @return string
     */
    protected function getCacheFile($key)
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . md5($this->options->app_id . $key);
    }

    /**
     * 处理魔术调用
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    static public function __callStatic($method, $args)
    {
        $method = strtoupper($method);

        if($method == 'GET' || $method == 'POST'){
            array_unshift($args, $method);

            return call_user_func_array(array(__CLASS__, 'request'), $args);
        }
    }

    /**
     * 处理魔术调用
     *
     * @param string $property
     *
     * @return mixed
     */
    public function __get($property)
    {
        if (!property_exists($this, $property)) {
            return null;
        }

        if ($property == 'server' || $property == 'client') {
            $property = "get" . ucfirst($property);

            return $this->{$property}();
        }
    }
}