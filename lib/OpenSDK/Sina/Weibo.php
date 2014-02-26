<?php

require_once 'OpenSDK/OAuth/Client.php';
require_once 'OpenSDK/OAuth/Interface.php';

/**
 * Sina 微博开放平台（http://open.weibo.com） SDK
 *
 * 依赖：
 * 1、PECL json >= 1.2.0    (no need now)
 * 2、PHP >= 5.2.0 because json_decode (no need now)
 * 3、$_SESSION
 * 4、PECL hash >= 1.1 (no need now)
 *
 * only need PHP >= 5.0
 *
 * 如何使用：
 * 1、将OpenSDK文件夹放入include_path
 * 2、require_once 'OpenSDK/Sina/Weibo.php';
 * 3、OpenSDK_Sina_Weibo::init($appkey,$appsecret);
 * 4、OpenSDK_Sina_Weibo::getRequestToken($callback); 获得request token
 * 5、OpenSDK_Sina_Weibo::getAuthorizeURL($token); 获得跳转授权URL
 * 6、OpenSDK_Sina_Weibo::getAccessToken($oauth_verifier) 获得access token
 * 7、OpenSDK_Sina_Weibo::call();调用API接口
 *
 * 建议：
 * 1、PHP5.2 以下版本，可以使用Pear库中的 Service_JSON 来兼容json_decode
 * 2、使用 session_set_save_handler 来重写SESSION。调用API接口前需要主动session_start
 * 3、OpenSDK的文件和类名的命名规则符合Pear 和 Zend 规则
 *    如果你的代码也符合这样的标准 可以方便的加入到__autoload规则中
 *
 * @author icehu@vip.qq.com
 */

class OpenSDK_Sina_Weibo extends OpenSDK_OAuth_Interface
{

    /**
     * app key
     * @var string
     */
    protected static $_appkey = '';
    /**
     * app secret
     * @var string
     */
    protected static $_appsecret = '';

    /**
     * 初始化
     * @param string $appkey
     * @param string $appsecret
     */
    public static function init($appkey,$appsecret)
    {
        self::$_appkey = $appkey;
        self::$_appsecret = $appsecret;
    }
    
    /**
     * OAuth 对象
     * @var OpenSDK_OAuth_Client
     */
    private static $oauth = null;

    private static $accessTokenURL = 'http://api.t.sina.com.cn/oauth/access_token';

    private static $authorizeURL = 'http://api.t.sina.com.cn/oauth/authorize';

    private static $requestTokenURL = 'http://api.t.sina.com.cn/oauth/request_token';

    /**
     * OAuth 版本
     * @var string
     */
    protected static $version = '1.0a';

    /**
     * 存储oauth_token的session key
     */
    const OAUTH_TOKEN = 'sina_oauth_token';
    /**
     * 存储oauth_token_secret的session key
     */
    const OAUTH_TOKEN_SECRET = 'sina_oauth_token_secret';
    /**
     * 存储access_token的session key
     */
    const ACCESS_TOKEN = 'sina_access_token';


    /**
     * 获取requestToken
     *
     * 返回的数组包括：
     * oauth_token：返回的request_token
     * oauth_token_secret：返回的request_secret
     * oauth_callback_confirmed：回调确认
     *
     * @param string $callback 回调地址
     * @return array
     */
    public static function getRequestToken($callback='null')
    {
        self::getOAuth()->setTokenSecret('');
        $response = self::request( self::$requestTokenURL, 'GET' , array(
            'oauth_callback' => $callback,
        ));
        parse_str($response , $rt);
        if($rt['oauth_token'] && $rt['oauth_token_secret'])
        {
            self::getOAuth()->setTokenSecret($rt['oauth_token_secret']);
            self::setParam(self::OAUTH_TOKEN, $rt['oauth_token']);
            self::setParam(self::OAUTH_TOKEN_SECRET, $rt['oauth_token_secret']);
            return $rt;
        }
        else
        {
            return false;
        }
    }

    /**
     *
     * 获得授权URL
     *
     * @param string|array $token
     * @return string
     */
    public static function getAuthorizeURL($token)
    {
        if(is_array($token))
        {
            $token = $token['oauth_token'];
        }
        return self::$authorizeURL . '?oauth_token=' . $token;
    }

    /**
     * 存储sina screen_name的session key
     */
    const OAUTH_SCREEN_NAME = 'sina_screen_name';
    /**
     * 存储sina user_id的session key
     */
    const OAUTH_USER_ID = 'sina_user_id';

    /**
     * 获得Access Token
     * @param string $oauth_verifier
     * @return array
     */
    public static function getAccessToken( $oauth_verifier = false )
    {
        $response = self::request( self::$accessTokenURL, 'GET' , array(
            'oauth_token' => self::getParam(self::OAUTH_TOKEN),
            'oauth_verifier' => $oauth_verifier,
        ));
        parse_str($response,$rt);
        if( $rt['oauth_token'] && $rt['oauth_token_secret'] )
        {
            self::getOAuth()->setTokenSecret($rt['oauth_token_secret']);
            self::setParam(self::ACCESS_TOKEN, $rt['oauth_token']);
            self::setParam(self::OAUTH_TOKEN_SECRET, $rt['oauth_token_secret']);
            self::setParam(self::OAUTH_SCREEN_NAME, $rt['screen_name']);
            self::setParam(self::OAUTH_USER_ID, $rt['user_id']);
            return $rt;
        }
        return false;
    }

    /**
     * 统一调用接口的方法
     * 照着官网的参数往里填就行了
     * 需要调用哪个就填哪个，如果方法调用得频繁，可以封装更方便的方法。
     *
     * 如果上传文件 $method = 'POST';
     * $multi 是一个二维数组
     *
     * array(
     *    '{fieldname}' => array(        //第一个文件
     *        'type' => 'mine 类型',
     *        'name' => 'filename',
     *        'data' => 'filedata 字节流',
     *    ),
     *    ...如果接受多个文件，可以再加
     * )
     *
     * @param string $command 官方说明中去掉 http://api.t.sina.com.cn/ 后面剩余的部分
     * @param array $params 官方说明中接受的参数列表，一个关联数组
     * @param string $method 官方说明中的 method GET/POST
     * @param false|array $multi 是否上传文件 false:普通post array: array ( '{fieldname}'=>'/path/to/file' ) 文件上传
     * @param bool $decode 是否对返回的字符串解码成数组
     * @param OpenSDK_Sina_Weibo::RETURN_JSON|OpenSDK_Sina_Weibo::RETURN_XML $format 调用格式
     */
    public static function call($command , $params=array() , $method = 'GET' , $multi=false , $decode=true , $format='json')
    {
        if($format == self::RETURN_XML)
            ;
        else
            $format == self::RETURN_JSON;
        //去掉空数据
        foreach($params as $key => $val)
        {
            if(strlen($val) == 0)
            {
                unset($params[$key]);
            }
        }
        $params['oauth_token'] = self::getParam(self::ACCESS_TOKEN);
        $response = self::request( 'http://api.t.sina.com.cn/'.ltrim($command,'/').'.'.$format , $method, $params, $multi);
        if($decode)
        {
            if( $format == self::RETURN_JSON )
            {
                return OpenSDK_Util::json_decode($response, true);
            }
            else
            {
                //todo parse xml2array later
                //其实没必要。用json即可
                return $response;
            }
        }
        else
        {
            return $response;
        }
    }

    /**
     * 重置Oauth对象
     * 在批量脚本中，如果同时操作多个用户，完成一个用户的操作后，需要重置
     */
    public static function clearOauth()
    {
        self::$oauth = null;
    }

    protected static $_debug = false;

    public static function debug($debug=false)
    {
        self::$_debug = $debug;
    }
    
    /**
     * 获得OAuth 对象
     * @return OpenSDK_OAuth_Client
     */
    protected static function getOAuth()
    {
        if( null === self::$oauth )
        {
            self::$oauth = new OpenSDK_OAuth_Client(self::$_appsecret,self::$_debug);
            $secret = self::getParam(self::OAUTH_TOKEN_SECRET);
            if($secret)
            {
                self::$oauth->setTokenSecret($secret);
            }
        }
        return self::$oauth;
    }

    /**
     *
     * OAuth协议请求接口
     *
     * @param string $url
     * @param string $method
     * @param array $params
     * @param array $multi
     * @return string
     * @ignore
     */
    protected static function request($url , $method , $params , $multi=false)
    {
        if(!self::$_appkey || !self::$_appsecret)
        {
            exit('app key or app secret not init');
        }
        $params['oauth_nonce'] = md5( mt_rand(1, 100000) . microtime(true) );
        $params['oauth_consumer_key'] = self::$_appkey;
        $params['oauth_signature_method'] = 'HMAC-SHA1';
        $params['oauth_version'] = self::$version;
        $params['oauth_timestamp'] = self::getTimestamp();
        $extheaders = array(
            'API-RemoteIP: ' . self::getRemoteIp(),
            'SaeRemoteIP: ' . self::getRemoteIp(),
        );
        return self::getOAuth()->request($url, $method, $params, $multi,$extheaders);
    }

}
