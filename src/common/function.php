<?php
function checkExtension()
{
	if (!extension_loaded('swoole')) {
		return "[扩展依赖]缺少swoole扩展";
	}
	if (version_compare(SWOOLE_VERSION, '4.0.0', '<')) {
		return "[版本错误]Swoole版本必须大于4.0.0\n";
	}
	if (!extension_loaded('yaf')) {
		return "[扩展依赖]缺少yaf扩展";
	}
		if (version_compare(Yaf\VERSION, '3.0.6', '<')) {
		return "[版本错误]yaf版本必须大于3.0.6\n";
	}
	if (!extension_loaded('yac')) {
		return "[扩展依赖]缺少yac扩展";
	}
	if (version_compare(YAC_VERSION, '2.0.2', '<')) {
		return "[版本错误]yac版本必须大于2.0.2\n";
	}

	if (!extension_loaded('SeasLog')) {
		return "[扩展依赖]缺少SeasLog扩展";
	}
	if (version_compare(seaslog_get_version(), '1.7.6', '<')) {
		return "[版本错误]SeasLog版本必须大于1.7.6\n";
	}
	if (extension_loaded('xhprof')) {
		return "[扩展错误]不允许加载xhprof扩展，请去除";
	}
	if (extension_loaded('xdebug')) {
		return "[扩展错误]不允许加载xdebug扩展，请去除";
	}
	if (version_compare(PHP_VERSION, '7.0.0', '<')) {
		return "[版本错误]PHP版本必须大于7.0.0\n";
	}
	if (version_compare(SWOOLE_VERSION, '2.0.1', '<')) {
		return "[版本错误]Swoole版本必须大于4.0.1\n";
	}

	/*if (!class_exists('swoole_redis')) {
		return "[编译错误]swoole编译缺少--enable-async-redis,具体参见文档http://docs.sder.xin/%E7%8E%AF%E5%A2%83%E8%A6%81%E6%B1%82.html";
	}*/
	if (!extension_loaded('redis')) {
		return "[扩展依赖]缺少redis扩展";
	}
	if (!extension_loaded('pdo')) {
		return "[扩展依赖]缺少pdo扩展";
	}
	return true;
}


function getConfigPath(){
	$path = AT .'/conf';
	if(!is_dir($path)){
		mkdir($path,0755);
	}
	return $path;
}


function displayExceptionHandler(){
	echo 'set_exception_handler';
}

/**
 * 错误信息
 * @return string
 */
function handleFatal(){
	$error = error_get_last();
	if ( isset( $error['type'] ) ) {
		switch ( $error['type'] ) {
			case E_ERROR :
			case E_PARSE :
			case E_CORE_ERROR :
			case E_COMPILE_ERROR :
				$message = $error['message'];
				$file    = $error['file'];
				$line    = $error['line'];
				$log     = "$message ($file:$line)\nStack trace:\n";
				$trace   = debug_backtrace();
				foreach ( $trace as $i => $t ) {
					if ( ! isset( $t['file'] ) ) {
						$t['file'] = 'unknown';
					}
					if ( ! isset( $t['line'] ) ) {
						$t['line'] = 0;
					}
					if ( ! isset( $t['function'] ) ) {
						$t['function'] = 'unknown';
					}
					$log .= "#$i {$t['file']}({$t['line']}): ";
					if ( isset( $t['object'] ) and is_object( $t['object'] ) ) {
						$log .= get_class( $t['object'] ) . '->';
					}
					$log .= "{$t['function']}()\n";
				}
				if ( isset( $_SERVER['REQUEST_URI'] ) ) {
					$log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
				}
				return $log;
			default:
				break;
		}
	}
}



function get_instance(){

}


function ArrToGetParam($arr){
	return is_array( $arr ) ? http_build_query( $arr ) : '';
}

/**
 * 获取请求ID
 * @return mixed|null
 */
function getRequestId(){
	return getArrVal('HTTP_REQUEST_ID',$_SERVER);
}

/**
 * 随机字符串
 * @param      $length
 * @param bool $not_number
 *
 * @return null|string
 */
function getRandChar($length, $not_number = false){
	$str    = null;
	$strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";

	$not_number && $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
	$length || $length = 4;
	$max = strlen( $strPol ) - 1;
	for ( $i = 0; $i < $length; $i ++ ) {
		$str .= $strPol[ rand( 0, $max ) ];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
	}

	return $str;
}

/**
 * 获取数组值
 * @param      $key
 * @param      $arr
 * @param null $default
 * @param bool $not_empty
 *
 * @return mixed|null
 */
function getArrVal($key, $arr, $default = null, $not_empty = false){
	if ( ! is_array( $arr ) ) {
		return null;
	}
	if ( is_array( $key ) ) {
		return null;
	}
	$key = (string) $key;
	//var_dump( $key );
	$index = strpos( $key, '.' );
	$last  = null;
	if ( $index == false ) {
		$arg = $key;
	} else {
		$arg  = substr( $key, 0, $index );
		$last = substr( $key, $index + 1, strlen( $key ) );
	}
	if ( isset( $arr[ $arg ] ) ) {
		if ( $last && is_array( $arr[ $arg ] ) ) {
			$val = getArrVal( $last, $arr[ $arg ], $default, $not_empty );
		} else {
			$val = $arr[ $arg ];
		}
		if ( is_string( $val ) && strlen( $val ) == 0 && $not_empty ) {
			return $default;
		}

		return $val;
	}

	return $default;
}

/**
 * 获取参数
 * @param $key
 * @param $default
 *
 * @return mixed
 */
function request_get($key ,$default = null){
	if(isset($_GET[$key])){
		$val = $_GET[$key];
	} else if(isset($_POST[$key])){
		$val = $_POST[$key];
	} else {
		$val = $default;
	}

	return $val;
}


/**
 * 批量创建目录
 *
 * @param string $dirName 目录名
 * @param int    $auth    权限
 *
 * @return bool
 */
 function create($dirName, $auth = 0755)
{
	$dirPath = dirPath($dirName);
	if ( is_dir($dirPath) ) {
		return true;
	}
	$dirs = explode('/', $dirPath);
	$dir  = '';
	foreach ( $dirs as $v ) {
		$dir .= $v . '/';
		if ( is_dir($dir) ) {
			continue;
		}
		mkdir($dir, $auth);
	}

	return is_dir($dirPath);
}


/**
 * @param string $dir_name 目录名
 *
 * @return mixed|string
 */
 function dirPath($dir_name)
{
	$dirname = str_ireplace("\\", "/", $dir_name);

	return substr($dirname, "-1") == "/" ? $dirname : $dirname . "/";
}


/**
 * 安全过滤,获取请求数据,get 或post 请求
 * @param      $key
 * @param null $default
 *
 * @return string
 */
 function getSafe($key,$default=null){
	$get_val = getArrVal( $key, $_GET, $default, true );
	strlen( $get_val ) === 0 && $get_val = getArrVal( $key, $_POST, $default, true );

	return text( $get_val );
}

/**
 * t函数用于过滤标签，输出没有html的干净的文本
 * @param string text 文本内容
 * @return string 处理后内容
 */
 function text($text){
	$text = nl2br($text);
	$text = real_strip_tags($text);
	$text = addslashes($text);
	$text = trim($text);
	return $text;
}

function real_strip_tags($str, $allowable_tags="") {
	$str = html_entity_decode($str,ENT_QUOTES,'UTF-8');
	return strip_tags($str, $allowable_tags);
}

 function tree($dirName = null, $exts = '', $son = 0, $list = array()) {
	if ( is_null($dirName) ) {
		$dirName = '.';
	}
	$dirPath = dirPath($dirName);
	static $id = 0;
	if ( is_array($exts) ) {
		$exts = implode("|", $exts);
	}

	foreach ( glob($dirPath . '*') as $v ) {
		$id++;
		if ( is_dir($v) || !$exts || preg_match("/\.($exts)/i", $v) ) {
			$list [$id] ['type']      = filetype($v);
			$list [$id] ['filename']  = basename($v);
			$path                     = str_replace("\\", "/", realpath($v)) . ( is_dir($v) ? '/' : '' );
			$list [$id] ['path']      = $path;
			isset($_SERVER['SCRIPT_FILENAME'])&&$list [$id] ['spath']     = ltrim(str_replace(dirname($_SERVER['SCRIPT_FILENAME']), '', $path), '/');
			$list [$id] ['filemtime'] = filemtime($v);
			$list [$id] ['fileatime'] = fileatime($v);
			$list [$id] ['size']      = is_file($v) ? filesize($v) : get_dir_size($v);
			$list [$id] ['iswrite']   = is_writeable($v) ? 1 : 0;
			$list [$id] ['isread']    = is_readable($v) ? 1 : 0;
		}
		if ( $son ) {
			if ( is_dir($v) ) {
				$list = tree($v, $exts, $son = 1, $list);
			}
		}
	}

	return $list;
}
 function get_dir_size($f)
{
	$s = 0;
	foreach ( glob($f . '/*') as $v ) {
		$s += is_file($v) ? filesize($v) : get_dir_size($v);
	}

	return $s;
}

/**
 * 根据$_SERVER判断是否为ajax请求
 * @return bool
 */
function isAjaxRequest()
{
	if ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) {
		return true;
	} elseif ( ! empty( $_SERVER['X-REQUESTED-WITH'] ) && $_SERVER['X-REQUESTED-WITH'] == 'XMLHttpRequest' ) {
		return true;
	} elseif ( getSafe('_is_ajax')=='1') {
		return true;
	} else {
		return false;
	}
}

/**
 * 设置cookie信息
 * @param        $name
 * @param null   $value
 * @param int    $expires
 * @param string $path
 * @param string $domain
 * @param bool   $secure
 * @param bool   $httponly
 */
function setCookieInfo($name, $value = null, $expires = 0, $path = '/', $domain = '', $secure = false, $httponly = false){
	if ( php_sapi_name() == 'cli' ) {
		$response = \AtServer\CoroutineContent::get('response');

		if($response instanceof swoole_http_response){
			$response->cookie( $name, $value, $expires, $path, $domain, $secure, $httponly );
		}
	}
}

/**
 * 获取cookie信息
 * @param $name
 *
 * @return mixed
 */
function getCookie( $name )
{
	return $_COOKIE[$name];
}