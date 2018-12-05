<?php

/**
 * Created by PhpStorm.
 * User: admin
 * Date: 16/8/4
 * Time: 下午10:04
 */
function application_load_library_server($class) {
	$class=str_replace( '\\', '/', $class );
	$file = MANAGE_PATH .'/' . $class . '.php';
	\AtServer\Log::log($file);
	if(is_file($file)){
		require_once $file;
		return true;
	}
	return false;
}

spl_autoload_register( 'application_load_library_server' );