<?php
/**
 * Created by PhpStorm.
 * User: htpc
 * Date: 2018/10/22
 * Time: 14:30
 */

namespace AtServer;


use AtServer\Log;
use AtServer\Swoole\ConnceInfo;
use function PHPSTORM_META\type;
use Symfony\Component\Console\Style\SymfonyStyle;

class WebSocket extends HttpServer
{


	/**
	 * 客户端请求握手连接触发
	 * @param \swoole_http_request  $request
	 * @param \swoole_http_response $response
	 *
	 * @return bool
	 */
	public  function onHandshake(\swoole_http_request $request, \swoole_http_response $response) {
		$this->globalvalue($request);
		if($this->handshake($request,$response) === false){
			$response->end();
			return false;
		}
		$this->server->defer(function ()use ($request){
			$this->onOpen( $this->server, $request );
		});
		// websocket握手连接算法验证
		$secWebSocketKey = $request->header['sec-websocket-key'];
		$patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
		if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
			$response->end();
			return false;
		}

		echo $request->header['sec-websocket-key'];
		$key = base64_encode(sha1(
			$request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
			true
		));

		$headers = [
			'Upgrade' => 'websocket',
			'Connection' => 'Upgrade',
			'Sec-WebSocket-Accept' => $key,
			'Sec-WebSocket-Version' => '13',
		];

		// WebSocket connection to 'ws://127.0.0.1:9502/'
		// failed: Error during WebSocket handshake:
		// Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
		if (isset($request->header['sec-websocket-protocol'])) {
			$headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
		}

		foreach ($headers as $key => $val) {
			$response->header($key, $val);
		}

		$response->status(101);
		$response->end();
		echo "connected!" . PHP_EOL;
		return true;
	}


	/**
	 * 连接完成后触发
	 * @param \swoole_websocket_server $server
	 * @param                          $request
	 */
	public function onOpen( \swoole_websocket_server $server, \Swoole\Http\Request $request )
	{
		try{
			$class = ConnceInfo::get($request->fd,'request_uri');
			$instance = new $class();
			if(method_exists($instance,'open')){
				$result = call_user_func_array(array($instance,'open'),array( $server,$request ));
			}else{
				throw new \Exception('没有open方法',1000);
			}
			if($result && is_array($result) ){
				$this->sendMessage( $server, $result);
			}
		}catch(Exception $e){
			$server->disconnect( $request->fd, $e->getCode(), $e->getMessage() );
		}
	}


	/**
	 * 客户端发送信息触发
	 * @param \swoole_websocket_server $server
	 * @param                          $frame
	 */
	public function onMessage(\swoole_websocket_server $server, \Swoole\WebSocket\Frame $frame)
	{
		try{
			$connceInfo = ConnceInfo::get($frame->fd);
			$this->globalvalue($connceInfo);
			$class = $connceInfo['request_uri'];
			$instance = new $class();
			if(method_exists($instance,'message')){
				$result = call_user_func_array(array($instance,'message'),array($server,$frame));
				if($result  === true ){
					$data = json_decode($frame->data);
					if(is_null($data)){
					    throw new \Exception('发送的数据必须为json数据');
					}
					if(!$data->message_router){
					  throw new \Exception('message路由不能为空');
					}
					$router = $this->messageRouter($class,$data->message_router);
				    if(isset($router['Controoler'])){
				    	if(!isset($router['Action'])){
						    throw new \Exception('Action不能为空');
				    	}
						$controller = $router['Controoler'];
						if($controller == $class){
							throw new \Exception('message控制器不能connce控制器相同');
						}
						if(class_exists($controller)){
						    $messageController = new $controller;
						    $action = $router['Action'];
						    if(method_exists($messageController,$action)){
							    $result = call_user_func_array(array($messageController,$action),array($server,$frame));
						    }else{
						    	throw new \Exception('Action不存在');
						    }
						}else{
							throw new \Exception('Controoler不存在');
						}
				    } else{
					    throw new \Exception('Controoler不能为空');
					}
				}
			}else{
				throw new \Exception('没有message方法',1000);
			}
			if($result && is_array($result) ){
				$this->sendMessage( $server, $result);
			}
		}catch(\Exception $e){
			\AtServer\Log::error( $e->getMessage() );
		}
	}


	/**
	 * 连接关闭后触发
	 * @param $server
	 * @param $fd
	 * @param $reactorId
	 *
	 * @throws \Exception
	 */
	public function onClose( $server, $fd, $reactorId)
	{
		if( $server->connection_info($fd)['websocket_status'] != 0 ){
			$connceInfo = ConnceInfo::get($fd);
			$this->globalvalue($connceInfo);
			$class = $connceInfo['request_uri'];
			if($class && class_exists($class)) {
				$instance = new $class();
				if ( method_exists( $instance, 'close' ) ) {
					$result = call_user_func_array( array( $instance, 'close' ), array($server, $fd,$reactorId) );
				} else {
					throw new \Exception( '没有close方法', 1000 );
				}
				if ( $result && is_array( $result ) ) {
					$this->sendMessage( $server, $result );
				}
			}
			ConnceInfo::del( $fd );
		}
	}



	/**
	 *
	 * @param \Symfony\Component\Console\Style\SymfonyStyle $oi
	 */
	public  function start(SymfonyStyle $oi)
	{
		$config = $this->config['ports'][$this->serverName];
		$set = $this->config['server'][$this->serverName];
		self::$instance = $this;
		if($this->get_process_info()){
			$oi->warning($this->serverName.'服务已启动;端口:'.$config['socket_port']);
			return ;
		}
		$logPath = $this->config['log']['path'];
		Log::setPath($logPath);
		if(defined("SWOOLE_TABLE") && is_array(SWOOLE_TABLE)){
			foreach (SWOOLE_TABLE as $item){
				class_exists($item) && $item::create();
			}
		}
		$this->server = new  \swoole_websocket_server($config['socket_host'],$config['socket_port']);
		self::$serverInstance = $this->server;
		$oi->success($this->serverName.'服务启动成功;端口:'.$config['socket_port']);
		Log::log($this->serverName.'服务启动');
		$this->server->set($set);
		$this->server->on( 'connect', array( $this, 'onConnect' ) );
		$this->server->on( 'workerStart', array( $this, 'onWorkerStart' ) );
		$this->server->on( 'Shutdown', array( $this, 'onShutdown' ) );
		$this->server->on( 'workerStop', array( $this, 'onWorkerStop' ) );
		$this->server->on( 'start', array( $this, 'onStart' ) );
		$this->server->on( 'workerError', array( $this, 'onWorkerError' ) );
		$this->server->on( 'ManagerStart', array( $this, 'onManagerStart' ) );
		$this->server->on( 'task', array( $this, 'onTask' ) );
		$this->server->on( 'finish', array( $this, 'onFinish' ) );
		$this->server->on( 'close', array( $this, 'onClose' ) );
		$this->server->on( 'request', array( $this, 'onRequest' ) );
		$this->server->on( 'pipeMessage', array( $this, 'onPipeMessage' ) );
		$this->server->on('packet',array($this,'onPacket'));
		$this->server->on('bufferFull',array($this,'onBufferFull'));
		$this->server->on('bufferEmpty',array($this,'onBufferEmpty'));
		$this->server->on('workerExit',array($this,'onWorkerExit'));
		$this->server->on('managerStart',array($this,'onManagerStart'));
		$this->server->on('managerStop',array($this,'onManagerStop'));
		$this->server->on('open',array($this, 'onOpen'));
		$this->server->on('handshake',array($this, 'onHandshake'));
		$this->server->on('message',array($this, 'onMessage'));
		$this->server->start();
	}

	/**
	 * @param \swoole_http_request  $request
	 * @param \swoole_http_response $response
	 *
	 * @return bool
	 */
	public function handshake(\swoole_http_request $request, \swoole_http_response $response)
	{
		try{
			$request_uri = $request->server['request_uri'];
			$class = $this->router( $request_uri );

			$class = '\Library\Ws'.$class;
			if(class_exists($class)){
				$get =$request->get?json_encode($request->get): '';
				$cookie = $request->cookie?json_encode($request->cookie) :'';
				$clientDdata = [
					'header'=>json_encode($request->header),
					'server'=>json_encode($request->server),
					'get' =>$get,
					'cookie'=>$cookie,
					'request_uri' =>$class,
				];
				$res = ConnceInfo::set($request->fd,$clientDdata);
				if(!$res){
					throw new \Exception('连接已超过限制',1000);
				}
				$instance = new $class();
				if(method_exists($instance,'handshake')){
					call_user_func_array(array($instance,'handshake'),array($request,$response));
				}else{
					throw new \Exception('没有handshake方法',1000);
				}
			}else{
				throw new \Exception('没有对应的处理逻辑',1000);
			}
		}catch(\Exception $e){
			\AtServer\Log::error( $e->getMessage() );
			return false;
		}
		return true;
	}


	/**
	 * 连接路由解析
	 * @param $request_uri
	 *
	 * @return string
	 */
	public function router($request_uri)
	{
		if($request_uri && $request_uri != '/'){
			$class = '\\';
			$pathArr = explode('/',trim($request_uri,'/'));
			if(isset($pathArr[0])){
				if(!strripos($pathArr[0],'?') === false){
					$class .= substr($pathArr[0],0,strripos($pathArr[0],'?'));
				}else{
					$class .= $pathArr[0];
				}
			}
			unset($pathArr[0]);
			if(isset($pathArr[1])){
				if(!strripos($pathArr[1],'?') ===false){
					$class .= '\\'.substr($pathArr[1],0,strripos($pathArr[1],'?'));
				}else{
					$class .= '\\'. $pathArr[1];
				}
				unset($pathArr[1]);
			}else{
				$class .= "\\Index";
			}
		}else{
			$class = '\\Index\\Index';
		}
		return $class;
	}


	/**
	 * 接受消息路由解析
	 * @param string $request_uri
	 * @param string $message_path
	 */
	public function messageRouter(string $request_uri, string $message_path )
	{
		$router = [];
		$router['Controoler'] = substr($request_uri,0, strrpos($request_uri,'\\') );
		if($message_path){
			$path = explode('/',trim($message_path,'/'));
			if($path){
				if(isset($path[0])){
					$router['Controoler'] .= '\\'. $path[0];
				}
				if(isset($path[1])){
					$router['Action'] = $path[1];
				}
			}
		}
		return $router ;
	}


	/**
	 * 发送信息
	 */
	public function sendMessage( \swoole_websocket_server $server, array $Objdata )
	{
		foreach ($Objdata as $itme){
			if($itme instanceof Result){
				$fds = $itme->getFd();
				if($fds && is_array($fds)){
					foreach ($fds as $val){
						$info = $server->connection_info($val);
						if( $info['websocket_status'] === WEBSOCKET_STATUS_FRAME && $server->exist($val) ){
							$server->push($val,(string)$itme) || Log::error('发送失败FD::'.$val);
						}else{
							\AtServer\Log::error('发送失败FD::'.$val);
						}
					}
				}else if($fds){
					$info = $server->connection_info($fds);
					if( $info['websocket_status'] === WEBSOCKET_STATUS_FRAME && $server->exist($ds) ){
						$server->push($fds,(string)$itme) || Log::error('发送失败FD::'.$val);
					}else{
						\AtServer\Log::error('发送失败FD::'.$val);
					}
			    }
			}
		}
	}

	/**
	 * 清理全局变量
	 * @param $server
	 */
	public function globalvalue($server)
	{
		
		if($server instanceof \swoole_http_request){
			$_SERVER      = isset( $server->server ) ? $server->server : array();
			$header       = isset( $server->header ) ? $server->header : array();
			$_GET         = isset( $server->get ) ? $server->get : array();
			$_POST        = isset( $server->post ) ? $server->post : array();
			$_COOKIE      = isset( $server->cookie ) ? $server->cookie : array();
			$_FILES       = isset( $server->files ) ? $server->files : array();
			//清理环境
			//将请求的一些环境参数放入全局变量桶中
			$_SESSION     = array();
		}else if($server && is_array($server)){
			$_SERVER      = $server['server']? json_decode($server['server'],1) : array();
			$header       = $server['header']? json_decode($server['header'],1) : array();
			$_GET         = $server['get']? json_decode($server['get'],1) : array();
			$_COOKIE      = $server['cookie']? json_decode($server['cookie'],1) : array();
			$_POST        = array();
			$_FILES       = array();
			$_SESSION     = array();
		}else{
			$_SERVER      = array();
			$header       = array();
			$_GET         = array();
			$_COOKIE      = array();
			$_POST        = array();
			$_FILES       = array();
			$_SESSION     = array();
		}
		foreach ( $_SERVER as $key => $value ) {
			unset( $_SERVER[ $key ] );
			$_SERVER[ strtoupper( $key ) ] = $value;
		}
		foreach ( $header as $key => $value ) {
			unset( $_SERVER[ $key ] );
			$_SERVER[ strtoupper( $key ) ] = $value;
		}

	}
}