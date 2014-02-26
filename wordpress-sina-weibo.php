<?php
/*
Plugin Name: Wordpress Sina weibo
Plugin URI: http://hzlzh.io/wordpress-sina-weibo/
Description: 显示新浪微博发言的插件，OAuth认证授权，安全可靠。采用了缓存机制，自定义刷新时间，不占用站点加载速度。可以在[外观]--[小工具]中调用，或者在任意位置使用 <code>&lt;?php display_Sina('username=you-ID&number=5'); ?&gt;</code> 调用。
Version: 1.1.2
Author: hzlzh
Author URI: http://hzlzh.io/

*/

//如果有遇到问题，请到 http://hzlzh.io/wordpress-sina-weibo/ 得到技术支持！
if ( ! defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );//获得plugins网页路径
if ( ! defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_URL. '/plugins' );//获得plugins直接路径

set_include_path( dirname( dirname( __FILE__ ) ) . '/wordpress-sina-weibo/lib/' );
require_once 'OpenSDK/Sina/Weibo2.php';
include 'OpenSDK/Sina/sinaappkey.php';

OpenSDK_Sina_Weibo2::init( $appkey, $appsecret );
//打开session
session_start();
$WSW_settings1 = get_option( 'WSW_settings' );
if ( $WSW_settings1 ) {
	OpenSDK_Sina_Weibo2::setParam ( OpenSDK_Sina_Weibo2::ACCESS_TOKEN, $WSW_settings1['access_token'] );
	OpenSDK_Sina_Weibo2::setParam ( OpenSDK_Sina_Weibo2::REFRESH_TOKEN, $WSW_settings1['oauth_token_secret'] );
}else {
	//echo 'dnoe';
}

$exit = false;
if ( isset( $_GET['exit'] ) ) {
	delete_option( 'WSW_settings' );
	OpenSDK_Sina_Weibo2::setParam( OpenSDK_Sina_Weibo2::ACCESS_TOKEN, null );
	OpenSDK_Sina_Weibo2::setParam( OpenSDK_Sina_Weibo2::REFRESH_TOKEN, null );
	//echo '<a href="?go_oauth">点击去授权</a>';
}else if ( 
		OpenSDK_Sina_Weibo2::getParam ( OpenSDK_Sina_Weibo2::ACCESS_TOKEN )
	) {
		$uinfo = OpenSDK_Sina_Weibo2::call('statuses/user_timeline',array('uid'=>OpenSDK_Sina_Weibo2::getParam (OpenSDK_Sina_Weibo2::OAUTH_USER_ID)));
	}
else if ( isset($_GET['code']) ) {
		//从Callback返回时
		if ( OpenSDK_Sina_Weibo2::getAccessToken('code',array('code'=>$_GET['code'],'redirect_uri'=>'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?page=WSW-options')) ) {
			$uinfo = OpenSDK_Sina_Weibo2::call('statuses/user_timeline',array('uid'=>OpenSDK_Sina_Weibo2::getParam (OpenSDK_Sina_Weibo2::OAUTH_USER_ID)));
			$WSW_settings = array( 'WSW_settings' );
			$WSW_settings['oauth_token'] = $_GET['oauth_token'];
			$WSW_settings['oauth_verifier'] = $_GET['oauth_verifier'];
			$WSW_settings['access_token'] = OpenSDK_Sina_Weibo2::getParam( OpenSDK_Sina_Weibo2::ACCESS_TOKEN );
			$WSW_settings['oauth_token_secret'] = OpenSDK_Sina_Weibo2::getParam( OpenSDK_Sina_Weibo2::REFRESH_TOKEN );
			$WSW_settings['num'] = 5;
			update_option( 'WSW_settings', $WSW_settings );
			var_dump($uinfo);
		}
		$exit = true;
	}
else if ( isset( $_GET['go_oauth'] ) ) {
		$callback = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?page=WSW-options';
    	$url = OpenSDK_Sina_Weibo2::getAuthorizeURL($callback, 'code', 'state');
    	header('Location: ' . $url);
	}else if ( $exit || isset( $_GET['exit'] ) ) {
		delete_option( 'WSW_settings' );
	}

//展示函数
function display_Sina( $args = '' ) {
	$default = array(
		'username'=>'hzlzh',
		'number'=>'5',
		'time'=>'3600' );
	$r = wp_parse_args( $args, $default );
	extract( $r );

	$uinfo = OpenSDK_Sina_Weibo2::call('statuses/user_timeline',array('count' => $number,'uid'=>OpenSDK_Sina_Weibo2::getParam (OpenSDK_Sina_Weibo2::OAUTH_USER_ID)));

	$decodedArray =$uinfo;
	echo '<ul class="tweets">';
	foreach ( $decodedArray['statuses'] as $value ) {
		echo '<li>'.str_replace( '&#160;', ' ', $value['text'] ).'</li>';
	}
	echo '</ul>';
}

//扩展类 WP_Widget
class Sinaweibo extends WP_Widget
{
	//定义后台面板展示文字
	function Sinaweibo() {
		$widget_des = array( 'classname'=>'wordpress-Sina-weibo', 'description'=>'在博客显示新浪微博的发言' );
		$this->WP_Widget( false, '新浪微博', $widget_des );
	}

	//定义widget后台选项
	function form( $instance ) {
		$uinfo = OpenSDK_Sina_Weibo2::call('statuses/user_timeline',array('uid'=>OpenSDK_Sina_Weibo2::getParam (OpenSDK_Sina_Weibo2::OAUTH_USER_ID)));
		$decodedArray = $uinfo;
		$instance = wp_parse_args( (array)$instance, array(
				'title'=>'新浪微博',
				'username'=>'hzlzh',
				'number'=>5,
				'time'=>'3600' ) );
		$title = htmlspecialchars( $instance['title'] );
		$username = htmlspecialchars( $instance['username'] );
		$number = htmlspecialchars( $instance['number'] );
		$time = htmlspecialchars( $instance['time'] );
		if ( isset( $_GET['exit'] ) ) {
			echo '<p><a class="button-primary widget-control-save" href="?page=WSW-options&go_oauth">点击OAuth授权</a></p>';}
		else if ( isset( $_GET['oauth_token'] ) && isset( $_GET['oauth_verifier'] ) ) {
			echo '<p>[状态]： <a style="cursor: default;" class="button-primary widget-control-save">已成功授权</a></p>'.
			'<br /> <p>[已授权帐号]： <img width="50" src="'.$decodedArray['avatar_hd'].'" alt=""/> <span>@'.$decodedArray['screen_name'].'</span></p>';}
		else if ( OpenSDK_Sina_Weibo2::getParam ( OpenSDK_Sina_Weibo2::ACCESS_TOKEN ) ) {
			echo '<p>[状态]： <a style="cursor: default;" class="button-primary widget-control-save">已成功授权</a></p>'.
			'<br /> <p>[已授权帐号]： <img width="50" src="'.$decodedArray['avatar_hd'].'" alt=""/> <span>@'.$decodedArray['screen_name'].'</span> <a href="?page=WSW-options&exit">注销?</a></p>';}
		else{
			echo '<p><a class="button-primary widget-control-save" href="?go_oauth">点击OAuth授权</a></p>';}
		
		echo '<p style="color:#FF3333;">任何反馈@<a target="_blank" href="http://weibo.com/hzlzh">hzlzh</a> 反馈</p><p><label for="'.$this->get_field_name( 'title' ).'">侧边栏标题:<input style="width:200px;" id="'.$this->get_field_id( 'title' ).'" name="'.$this->get_field_name( 'title' ).'" type="text" value="'.$title.'" /></label></p>
		<p><label for="'.$this->get_field_name( 'username' ).'">用户名:  <i>(字母+数字)</i><input style="width:200px;" id="'.$this->get_field_id( 'username' ).'" name="'.$this->get_field_name( 'username' ).'" type="text" value="'.$username.'" /></label></p>
		<p><label for="'.$this->get_field_name( 'number' ).'">显示数量: <i>(1-100条)</i><input style="width:200px" id="'.$this->get_field_id( 'number' ).'" name="'.$this->get_field_name( 'number' ).'" type="text" value="'.$number.'" /></label></p>
		<p><label for="'.$this->get_field_name( 'time' ).'">缓存时间:<input style="width:200px" id="'.$this->get_field_id( 'time' ).'" name="'.$this->get_field_name( 'time' ).'" type="text" value="'.$time.'" />秒</label></p>';
	}

	//更新函数
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( stripslashes( $new_instance['title'] ) );
		$instance['username'] = strip_tags( stripslashes( $new_instance['username'] ) );
		$instance['number'] = strip_tags( stripslashes( $new_instance['number'] ) );
		$instance['time'] = strip_tags( stripslashes( $new_instance['time'] ) );
		return $instance;
	}

	//显示函数
	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '&nbsp;' : $instance['title'] );
		$username = empty( $instance['username'] ) ? 'Weibo_ID' : $instance['username'];
		$number = empty( $instance['number'] ) ? 1 : $instance['number'];
		$time = empty( $instance['time'] ) ? 3600 : $instance['time'];

		echo $before_widget;
		echo $before_title . $title . $after_title;
		display_Sina( "username=$username&number=$number&time=$time" );
		echo $after_widget;
	}
}

//注册widget
function SinaweiboInit() {
	register_widget( 'Sinaweibo' );
}

add_action( 'widgets_init', 'SinaweiboInit' );

function SinaweiboPage() {
	//add_options_page('SinaweiboInit Options', 'Wordpress Sina weibo', 10, 'wordpress-Sina-weibo/options.php');
	add_options_page('新浪微博插件', '新浪微博插件', 'manage_options','WSW-options', 'Sinaweibo_options_page');
}
add_action('admin_menu', 'SinaweiboPage');

function Sinaweibo_options_page() {
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2>新浪微博插件设置</h2>
<br />
<p>
<?php
$uinfo = OpenSDK_Sina_Weibo2::call('users/show',array('uid'=>OpenSDK_Sina_Weibo2::getParam (OpenSDK_Sina_Weibo2::OAUTH_USER_ID)));
// var_dump($uinfo);
$decodedArray = $uinfo;
if ( isset( $_GET['exit'] ) ) {
			echo '<p><a class="button-primary widget-control-save" href="?page=WSW-options&go_oauth">点击OAuth授权</a></p>';}
		else if ( isset( $_GET['oauth_token'] ) && isset( $_GET['oauth_verifier'] ) ) {
			echo '<p>[状态]： <a style="cursor: default;" class="button-primary widget-control-save">已成功授权</a></p>'.
			'<br /> <p>[已授权帐号]： <img width="50" src="'.$decodedArray['avatar_hd'].'" alt=""/> <span>@'.$decodedArray['screen_name'].'</span></p>';}
		else if ( OpenSDK_Sina_Weibo2::getParam ( OpenSDK_Sina_Weibo2::ACCESS_TOKEN ) ) {
			echo '<p>[状态]： <a style="cursor: default;" class="button-primary widget-control-save">已成功授权</a></p>'.
			'<br /> <p>[已授权帐号]： <img width="50" src="'.$decodedArray['avatar_hd'].'" alt=""/> <span>@'.$decodedArray['screen_name'].'</span> <a href="?page=WSW-options&exit">注销?</a></p>';}
		else{
			echo '<p><a class="button-primary widget-control-save" href="?go_oauth">点击OAuth授权</a></p>';}
		
?>
<div class="update-nag" id="donate">
<div style="text-align: center;">
<span style="font-size: 20px;margin: 5px 0;display: block;">使用说明</span>
<br />
授权完成之后，在[外观] -> <a href="/wp-admin/widgets.php">[小挂件]</a>中使用，也可以使用下面代码在WordPress任意页面的任意位置调用：
<br />
<code>&lt;?php display_Sina('number=5'); ?&gt;</code> 
<br />
任何反馈 -> @<a target="_blank" href="http://twitter.com/hzlzh">hzlzh</a>
</div>
</div>
</p>
</div>
<?php
}
?>
