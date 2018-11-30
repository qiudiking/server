<?php
/**
 * Created by PhpStorm.
 * User: htpc
 * Date: 2018/10/22
 * Time: 14:30
 */

namespace AtServer;


use AtServer\Log;
use Symfony\Component\Console\Style\SymfonyStyle;

class WebSocket extends HttpServer
{

	/**
	 * @param \swoole_websocket_server $server
	 * @param                          $request
	 */
	public function onOpen( \swoole_websocket_server $server, \Swoole\Http\Request $request )
	{
		\AtServer\Log::log( $request );
	}

	/**
	 * @param \swoole_websocket_server $server
	 * @param                          $frame
	 */
	public function onMessage(\swoole_websocket_server $server, \Swoole\WebSocket\Frame $frame)
	{
		\AtServer\Log::log( $frame );
		//$server->disconnect( $frame->fd, 1000,'不想给你连了' );
	}


	public function onClose( $server, $fd, $reactorId)
	{
		\AtServer\Log::log('连接已断开' );
		\AtServer\Log::log( $fd );
		\AtServer\Log::log( $reactorId );
	}

	/**
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
		$this->server->on('message',array($this, 'onMessage'));
		$this->server->start();
	}

}