<?php
/**
 * Created by PhpStorm.
 * User: htpc
 * Date: 2018/8/10
 * Time: 14:55
 */

namespace AtServer\CoreBase;


class PortManager {
	const SOCK_TCP = SWOOLE_SOCK_TCP;
	const SOCK_UDP = SWOOLE_SOCK_UDP;
	const SOCK_TCP6 = SWOOLE_SOCK_TCP6;
	const SOCK_UDP6 = SWOOLE_SOCK_UDP6;
	const UNIX_DGRAM = SWOOLE_UNIX_DGRAM;
	const UNIX_STREAM = SWOOLE_UNIX_STREAM;
	const SWOOLE_SSL = SWOOLE_SSL;
	const SOCK_HTTP = 10;
	const SOCK_WS = 11;
	const WEBSOCKET_OPCODE_TEXT = WEBSOCKET_OPCODE_TEXT;
	const WEBSOCKET_OPCODE_BINARY = WEBSOCKET_OPCODE_BINARY;

	protected $packs = [];
	protected $routes = [];
	protected $middlewares = [];
	protected $portConfig;
	public $websocket_enable = false;
	public $http_enable = false;
	public $tcp_enable = false;

	public function __construct()
	{

	}
}