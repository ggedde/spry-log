<?php

namespace Spry\SpryProvider;

use Spry\Spry;

/**
 *
 *  Generic Log Class to catch API Logs and PHP Error Logs
 *
 */

class SpryLog
{

	/**
	 * Log a generic Message
	 *
 	 * @param string $msg
 	 *
 	 * @access 'public'
 	 * @return bool
	 */

	public static function log($msg)
	{
		if(!empty(Spry::config()->log_api_file))
		{
			$file = Spry::config()->log_api_file;

			if(isset(Spry::config()->log_format))
			{
				$log = str_replace(
					[
						'%date_time%',
						'%ip%',
						'%request_id%',
						'%path%',
						'%msg%'
					],
					[
						date('Y-m-d H:i:s'),
						self::getIp(),
						Spry::get_request_id(),
						Spry::get_path(),
						$msg
					],
					Spry::config()->log_format
				);
			}
			else
			{
				$log = date('Y-m-d H:i:s').' '.self::getIp().' '.Spry::get_path().' - '.$msg;
			}


			$log = "\n".$log;

			if(!is_dir(dirname($file)))
			{
				@mkdir(dirname($file));
			}

			self::archive_file($file);

			return file_put_contents($file, $log, FILE_APPEND);
		}
	}


	/**
	 * Log a generic Message
	 *
 	 * @param string $msg
 	 *
 	 * @access 'public'
 	 * @return bool
	 */

	public static function message($msg)
	{
		$prefix = 'Spry: ';
		if(isset(Spry::config()->log_prefix['message']))
		{
			$prefix = Spry::config()->log_prefix['message'];
		}

		return self::log($prefix.$msg);
	}


	/**
	 * Log a generic Warning
	 *
 	 * @param string $msg
 	 *
 	 * @access 'public'
 	 * @return bool
	 */

	public static function warning($msg)
	{
		$prefix = 'Spry Warning: ';
		if(isset(Spry::config()->log_prefix['warning']))
		{
			$prefix = Spry::config()->log_prefix['warning'];
		}

		return self::log($prefix.$msg);
	}


	/**
	 * Log a generic Error
	 *
 	 * @param string $msg
 	 *
 	 * @access 'public'
 	 * @return bool
	 */

	public static function error($msg)
	{
		$prefix = 'Spry ERROR: ';
		if(isset(Spry::config()->log_prefix['error']))
		{
			$prefix = Spry::config()->log_prefix['error'];
		}

		return self::log($prefix.$msg);
	}


	/**
	 * Log a Hard Stop Error
	 *
 	 * @param string $msg
 	 *
 	 * @access 'public'
 	 * @return bool
	 */

	public static function stop($params)
	{
		$messages = (!empty($params['messages']) && is_array($params['messages']) ? implode(', ', $params['messages']) : '');
		$msg = 'Response Code ('.$params['code'].') - '.$messages;

		$prefix = 'Spry STOPPED: ';
		if(isset(Spry::config()->log_prefix['stop']))
		{
			$prefix = Spry::config()->log_prefix['stop'];
		}

		self::log($prefix.$msg);

		if(!empty($params['private_data']))
		{
			self::log($prefix." [START PRIVATE DATA]\n".print_r($params['private_data'], true)."\n[END PRIVATE DATA]\n");
		}
	}



	/**
	 * Log a Response
	 *
 	 * @param string $msg
 	 *
 	 * @access 'public'
 	 * @return bool
	 */

	public static function build_response_filter($response)
	{
		$messages = (!empty($response['messages']) && is_array($response['messages']) ? ' - ' . implode(', ', $response['messages']) : '');
		$msg = 'Response Code ('.$response['code'].')'.$messages;

		$prefix = 'Spry Response: ';
		if(isset(Spry::config()->log_prefix['response']))
		{
			$prefix = Spry::config()->log_prefix['response'];
		}

		self::log($prefix.$msg);

		return $response;
	}



	/**
	 * Log a Initiating Request
 	 *
 	 * @access 'public'
 	 * @return bool
	 */

	public static function initial_request()
	{
		$secure = [
			'password',
			'pass',
			'access_key',
			'access_token',
			'token',
			'key',
			'secret',
			'login',
			'api_key',
			'hash',
		];
		$params = Spry::params();

		foreach ($params as $param_key => $param_value)
		{
			if(in_array(strtolower($param_key), $secure))
			{
				$params[$param_key] = 'xxxxxx...';
			}
		}

		$prefix = 'Spry Request: ';
		if(isset(Spry::config()->log_prefix['request']))
		{
			$prefix = Spry::config()->log_prefix['request'];
		}

		self::log($prefix.str_replace('Array', '', print_r($params, true)));
	}



	/**
	 * Log a User Request
 	 *
 	 * @access 'public'
 	 * @return void
	 */

	public static function user_request()
	{
		$data = [
			'account_id' => Spry::auth()->account_id,
			'user_id' => Spry::auth()->user_id,
			'request' => Spry::get_path(),
			'permitted' => (Spry::auth()->has_permission() ? 1 : 0)
		];

		Spry::db()->insert('logs', $data);
	}



	/**
	 * Sets the error handler for all PHP errors in the App.
 	 *
 	 * @param int $errno
 	 * @param string $errstr
 	 * @param string $errfile
 	 * @param string $errline
 	 *
 	 * @access 'public'
 	 * @return void
 	 * @final
	 */

	public static function php_log_handler($errno, $errstr, $errfile, $errline)
	{
		if(!empty($errstr) && !empty(Spry::config()->log_php_file))
		{
			$file = Spry::config()->log_php_file;

			if(strpos($errstr, '[SQL Error]') !== false)
			{
				$errno = 'SQL Error';
			}

			switch ($errno)
			{
			    case E_ERROR:
			    case E_USER_ERROR:
			    case E_CORE_ERROR:
			    case E_COMPILE_ERROR:
			    case E_RECOVERABLE_ERROR:
			        $errstr = 'PHP Fatal Error: '.$errstr;
			        break;

			    case 'SQL Error':
			        $errstr = 'SQL Error: '.str_replace('[SQL Error] ', '', $errstr);
			        break;

			    case E_WARNING:
			    case E_USER_WARNING:
			    case E_CORE_WARNING:
			    case E_COMPILE_WARNING:
			        $errstr = 'PHP Warning: '.$errstr;
			        break;

			    case E_NOTICE:
			    case E_USER_NOTICE:
			    case '8':
			        $errstr = 'PHP Notice: '.$errstr;
			        break;

			    case E_PARSE:
			        $errstr = 'PHP Parse Error: '.$errstr;
			        break;

			    case E_STRICT:
			        $errstr = 'PHP Strict: '.$errstr;
			        break;

			    default:
			    	$errstr = 'PHP Unknown Error: '.$errstr;
			        break;
	    	}

		    $backtrace = '';
		    $dbts = debug_backtrace();
			foreach ($dbts as $dbt)
			{
				if(!empty($dbt['file']))
				{
					$backtrace.= ' - - Trace: '.$dbt['file'].' [Line: '.(!empty($dbt['line']) ? $dbt['line'] : '?').'] - Function: '.$dbt['function']."\n";
				}
			}

			if(isset(Spry::config()->log_php_format))
			{
				$log = str_replace(
					[
						'%date_time%',
						'%ip%',
						'%request_id%',
						'%errno%',
						'%errstr%',
						'%errfile%',
						'%errline%',
						'%backtrace%'
					],
					[
						date('Y-m-d H:i:s'),
						self::getIp(),
						Spry::get_request_id(),
						$errno,
						$errstr,
						$errfile,
						$errline,
						$backtrace,
					],
					Spry::config()->log_php_format
				);
			}
			else
			{
				$log = date('Y-m-d H:i:s').' '.$errstr.' '.$errfile.' [Line: '.(!empty($errline) ? $errline : '?')."]\n".$backtrace;
			}

			if(!is_dir(dirname($file)))
			{
				@mkdir(dirname($file));
			}

			self::archive_file($file);

			file_put_contents($file, $log, FILE_APPEND);
		}
	}



	/**
	 * Checks the API on Shutdown for Fatal Errors.
 	 *
 	 * @access 'public'
 	 * @return void
 	 * @final
	 */

	public static function php_shutdown_function()
	{
		$error = error_get_last();
	    if(!empty($error['type']) && !empty($error['message']))
	    {
	        self::php_log_handler($error['type'], $error['message'], $error['file'], $error['line']);
	    }
	}



	/**
	 * Sets up the PHP Log Handlers.
 	 *
 	 * @access 'public'
 	 * @return void
	 */

	public static function setup_php_logs()
	{
		if(!empty(Spry::config()->log_php_file))
		{
	    	set_error_handler([__CLASS__, 'php_log_handler']);
	    	register_shutdown_function([__CLASS__, 'php_shutdown_function']);
	    }
	}



	/**
	 * Gets the Ip Address and detects if is CLI or Background Process.
 	 *
 	 * @access 'private'
 	 * @return string
	 */

	private static function getIp()
	{
		if(empty($_SERVER['REMOTE_ADDR']) && Spry::is_cli())
		{
	    	return '127.0.0.1';
	    }

		return $_SERVER['REMOTE_ADDR'];
	}



	/**
	 * Archives or Cleanups the file.
	 *
	 * @access 'private'
	 * @return void
	 */

	private static function archive_file($file)
	{
		if(empty(Spry::config()->log_max_lines) || !file_exists($file))
		{
			return;
		}

		$contents = file_get_contents($file);
		$lines = explode("\n", $contents);

		if(count($lines) <= Spry::config()->log_max_lines)
		{
			return;
		}

		if(!empty(Spry::config()->log_archive))
		{
			$archived_file = dirname($file).'/'.basename($file).'.'.date('Y-m-d_H-i-s').'.gz';

			if($fp = gzopen($archived_file, 'wb'))
			{
		        if(gzwrite($fp, $contents))
				{
					gzclose($fp);
					file_put_contents($file, '');
				}
				else
				{
					gzclose($fp);
				}
    		}
		}
		else
		{
			$new_content = implode("\n", array_slice($lines, -(Spry::config()->log_max_lines)));
			file_put_contents($file, $new_content);
		}

	}

}
