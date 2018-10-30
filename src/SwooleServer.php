<?php
/**
 * Created by PhpStorm.
 * User: htpc
 * Date: 2018/8/10
 * Time: 14:53
 */

namespace AtServer;




use Noodlehaus\Config;
use AtServer\ClientParams;
use AtServer\Pack;
use AtServer\Log;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yaf\Exception;

class SwooleServer {

	const SWOOLE_SERVER_TPC = 'TCP';
	const SWOOLE_SERVER_HTTP = 'HTTP';
	const SWOOLE_SERVER_WS   = 'WS';

	public $serverName;

	public $config;

	public $user;

	public $portManager;

	public $server;

	public static $serverInstance;

	/**
	 * SwooleServer constructor.
	 *
	 * @throws \Noodlehaus\Exception\EmptyDirectoryException
	 */
	public function __construct()
	{
		$this->setConfig();
		$this->user = $this->config->get('server.set.user', '');
		set_error_handler([$this, 'displayErrorHandler'], E_ALL | E_STRICT);
		set_exception_handler('displayExceptionHandler');
		register_shutdown_function( array($this,'handleFatal') );
	}

	/**
	 *php代码执行过程中发生错误
	 */
	public  function handleFatal(){
		$error = handleFatal();
		if(isset($_SERVER['FD'])){
			Log::error($error);
			$result = ClientParams::instance();
			$result->setExceptionMessage($this->serverName.'server error',500);
			$sendData = Pack::sendEncode(Pack::encode(serialize($result)));
			$this->server->send(getArrVal('FD',$_SERVER),$sendData);
		}
	}


	/**
	 * @throws \Noodlehaus\Exception\EmptyDirectoryException
	 */
	protected function setConfig()
	{
		$this->config = new Config(getConfigPath());
	}

	public function displayErrorHandler($errno, $errstr, $errfile, $errline)
	{
			Log::error($errno.$errstr. $errfile. $errline);
	}


	/**
	 * 设置进程名
	 * @param string $postfix
	 *
	 * @return string
	 */
	public function set_process_name($postfix = '')
	{
		$process_name = isset($this->config['server'][$this->serverName]['process_name'])?$this->config['server'][$this->serverName]['process_name']:$this->serverName;
		$port = '[port:'.$this->config['ports'][$this->serverName]['socket_port'].']';
		return  $process_name.$postfix.$port;
	}

	/**
	 * 获取进程信息
	 * @return array|bool
	 */
	public function get_process_info()
	{
		exec( 'ps -ef', $res );
		$processName = $this->set_process_name( 'Main' );
		foreach ( $res as $val ) {
			if ( $val && $processName && strpos( $val, $processName ) !== false ) {
				$arrInfo = explode( ' ',
					preg_replace( '/\s+/', ' ', trim( $val ) ) );
				$name    = end( $arrInfo );
				if ( is_array( $arrInfo ) ) {
					if ( $processName == $name ) {
						$pid = $arrInfo[1];
						return [ 'pid' => $pid, 'name' => $name ];
					}
				}
			}
		}
		return false;
	}




	public function onConnect( $server,  $fd,  $reactorId)
	{
		echo "链接成功\n";
		//Log::log('链接成功');
	}

	public function onReceive(\swoole_server $server,  $fd,  $reactor_id,  $data){
		$_SERVER['FD'] = $fd;
		$data = unserialize(Pack::decode(Pack::decodeData($data)));
		list( $class, $method ) = explode( '::', $data->method );
		$result = ClientParams::instance();
		try{
			if($class && $method ){
				if(class_exists($class)){
				    $instance = new $class;
				    if(method_exists($instance,'init')){
					    call_user_func(array($instance,'init'));
				    }
				    if(method_exists($instance,$method)){
					    $result->result = call_user_func_array( array( $instance, $method ), $data->callParams );
				    }else{
				    	throw new \Exception('方法不存在::'.$class.'->'.$method,7772);
				    }
				    unset($instance);
				}else{
					throw new \Exception('类不存在::'.$class,7771);
				}
			}else{
				throw	new \Exception('class无效或方法无效',7770);
			}
		}catch(\Exception $e){
			$result->setExceptionMessage($e->getMessage(),$e->getCode());
		}
		$result->request_id = $data->request_id;
		$result->isResponse = $data->isResponse;
		$sendData = Pack::sendEncode(Pack::encode(serialize($result)));
		$server->send($fd,$sendData);
	}

	public function onClose( $server,  $fd,  $reactorId)
	{

	}

	public function onPacket( $server,  $data, array $client_info)
	{

	}

	public function onBufferFull( $server,  $fd)
	{

	}

	public function onBufferEmpty($server,  $fd)
	{

	}

	public function onStart($server)
	{
		$process_name = $this->set_process_name('Main');
		cli_set_process_title($process_name);
	}
	public function onShutdown($server){

	}

	public function onWorkerStart($server,$worker_id)
	{
		Log::log($this->serverName.'服务启动成功....');
		if(!$server->taskworker){
			$process_name = $this->set_process_name('Worker');
		}else{
			$process_name = $this->set_process_name('Task');
		}
		cli_set_process_title($process_name);
	}

	public function onWorkerStop($server,  $worker_id)
	{
		print_r('关闭');
	}

	public function onWorkerExit( $server,  $worker_id)
	{

	}

	public function onWorkerError( $server,  $worker_id,  $worker_pid,  $exit_code,  $signal)
	{

	}

	public function onTask( $server,  $task_id,  $src_worker_id,  $data)
	{

	}

	public function onFinish( $server,  $task_id,  $data)
	{

	}

	public function onManagerStart($server){
		$process_name = $this->set_process_name('Manager');
		cli_set_process_title($process_name);
	}

	public function onManagerStop($server){

	}

	public function onPipeMessage( $server,  $src_worker_id,  $message)
	{

	}

	/**
	 * 关闭服务
	 * @param \Symfony\Component\Console\Style\SymfonyStyle $oi
	 *
	 * @return bool
	 */
	public function stop(SymfonyStyle $oi)
	{
		$config = $this->config['ports'][$this->serverName];
		$processInfo = $this->get_process_info();
		if ( $processInfo ) {
			$name = $processInfo['name'];
			$pid  = $processInfo['pid'];
			if ( $pid ) {
				\swoole_process::kill( $pid );
				file_put_contents( AT.'/bin/pidfile/'.$this->serverName.$config['socket_port'].'.pid', $pid );
				$oi->success($this->serverName.'服务关闭成功 端口:'.$config['socket_port']);
			} else {
				$oi->error($this->serverName.'服务关闭失败 端口:'.$config['socket_port']);
			}
		} else {
			$oi->warning($this->serverName.'服务没有运行 端口:'.$config['socket_port']);
		}
	}

	/**
	 * 重启服务
	 * @param \Symfony\Component\Console\Style\SymfonyStyle $oi
	 */
	public function restart(SymfonyStyle $oi)
	{
		$this->stop($oi);
		sleep( 1 );
		$this->start($oi);
	}

	public function start( SymfonyStyle $oi)
	{
		$config = $this->config['ports'][$this->serverName];
		$set = $this->config['server'][$this->serverName];
		if($this->get_process_info()){
			$oi->warning($this->serverName.'服务已启动;端口:'.$config['socket_port']);
			return ;
		}
		$logPath = $this->config['log']['path'];
		Log::setPath($logPath);
		$this->server = new  \swoole_server($config['socket_host'],$config['socket_port'],SWOOLE_PROCESS,$config['socket_type']);
		self::$serverInstance = $this->server;
		$oi->success($this->serverName.'服务启动成功;端口:'.$config['socket_port']);
		Log::log($this->serverName.'服务启动');
		$this->server->set($set);
		$this->server->on('connect',array($this,'onConnect'));
		$this->server->on('receive',array($this,'onReceive'));
		$this->server->on('close',array($this,'onClose'));
		$this->server->on('packet',array($this,'onPacket'));
		$this->server->on('bufferFull',array($this,'onBufferFull'));
		$this->server->on('bufferEmpty',array($this,'onBufferEmpty'));
		$this->server->on('start',array($this,'onStart'));
		$this->server->on('shutdown',array($this,'onShutdown'));
		$this->server->on('workerStart',array($this,'onWorkerStart'));
		$this->server->on('workerStop',array($this,'onWorkerStop'));
		$this->server->on('workerExit',array($this,'onWorkerExit'));
		$this->server->on('workerError',array($this,'onWorkerError'));
		$this->server->on('task',array($this,'onTask'));
		$this->server->on('finish',array($this,'onFinish'));
		$this->server->on('managerStart',array($this,'onManagerStart'));
		$this->server->on('managerStop',array($this,'onManagerStop'));
		$this->server->on('pipeMessage',array($this,'onPipeMessage'));
		$this->server->start();
	}

}