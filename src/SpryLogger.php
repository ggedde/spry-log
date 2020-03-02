<?php

/**
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

namespace Spry\SpryProvider;

use Spry\Spry;

/**
 *
 *  Generic Log Class to catch API Logs and PHP Error Logs
 *
 */

class SpryLogger
{
    private static $apiFile = '';
    private static $archive = false;
    private static $format = '%date_time% %ip% %request_id% %path% - %msg%';
    private static $maxLines = 5000;
    private static $maxArchives = 10;
    private static $phpFile = '';
    private static $phpFormat = "%date_time% %errstr% %errfile% [Line: %errline%]\n%backtrace%";
    private static $prefix = [
        'message' => 'Spry: ',
        'warning' => 'Spry Warning: ',
        'error' => 'Spry ERROR: ',
        'stop' => 'Spry STOPPED: ',
        'response' => 'Spry Response: ',
        'request' => 'Spry Request: ',
    ];

    /**
     * Constructor for Logger
     */
    public static function initiate()
    {
        if (!empty(Spry::config()->logger)) {
            $options = Spry::config()->logger;
        }
        if (isset($options['format'])) {
            self::$format = $options['format'];
        }
        if (isset($options['php_format'])) {
            self::$phpFormat = $options['php_format'];
        }
        if (isset($options['php_file'])) {
            self::$phpFile = $options['php_file'];
        }
        if (isset($options['api_file'])) {
            self::$apiFile = $options['api_file'];
        }
        if (isset($options['prefix'])) {
            self::$prefix = $options['prefix'];
        }
        if (isset($options['max_lines'])) {
            self::$maxLines = intval($options['max_lines']);
        }
        if (isset($options['max_archives'])) {
            self::$maxArchives = intval($options['max_archives']);
        }
        if (isset($options['archive'])) {
            self::$archive = $options['archive'];
        }

        Spry::addHook('setParams', 'Spry\\SpryProvider\\SpryLogger::initialRequest');
        Spry::addHook('stop', 'Spry\\SpryProvider\\SpryLogger::stop');
        Spry::addFilter('buildResponse', 'Spry\\SpryProvider\\SpryLogger::buildResponseFilter');

        self::setupPhpLogs();
    }

    /**
     * Log a generic Message
     *
     * @param string $msg
     *
     * @access public
     *
     * @return bool
     */
    public static function log($msg)
    {
        if (!empty(self::$apiFile)) {
            $file = self::$apiFile;

            if (isset(self::$format)) {
                $log = str_replace(
                    [
                        '%date_time%',
                        '%ip%',
                        '%request_id%',
                        '%path%',
                        '%msg%',
                    ],
                    [
                        date('Y-m-d H:i:s'),
                        self::getIp(),
                        Spry::getRequestId(),
                        Spry::getPath(),
                        $msg,
                    ],
                    self::$format
                );
            } else {
                $log = date('Y-m-d H:i:s').' '.self::getIp().' '.Spry::getPath().' - '.$msg;
            }

            $log = "\n".$log;

            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file));
            }

            self::archiveFile($file);

            return file_put_contents($file, $log, FILE_APPEND);
        }
    }



    /**
     * Log a generic Message
     *
     * @param string $msg
     *
     * @access public
     *
     * @return bool
     */
    public static function message($msg)
    {
        $prefix = 'Spry: ';
        if (isset(self::$prefix['message'])) {
            $prefix = self::$prefix['message'];
        }

        return self::log($prefix.$msg);
    }



    /**
     * Log a generic Warning
     *
     * @param string $msg
     *
     * @access public
     *
     * @return bool
     */
    public static function warning($msg)
    {
        $prefix = 'Spry Warning: ';
        if (isset(self::$prefix['warning'])) {
            $prefix = self::$prefix['warning'];
        }

        return self::log($prefix.$msg);
    }



    /**
     * Log a generic Error
     *
     * @param string $msg
     *
     * @access public
     *
     * @return bool
     */
    public static function error($msg)
    {
        $prefix = 'Spry ERROR: ';
        if (isset(self::$prefix['error'])) {
            $prefix = self::$prefix['error'];
        }

        return self::log($prefix.$msg);
    }



    /**
     * Log a Hard Stop Error
     *
     * @param mixed $params
     *
     * @access public
     *
     * @return bool
     */
    public static function stop($params)
    {
        $messages = (!empty($params['messages']) && is_array($params['messages']) ? implode(', ', $params['messages']) : '');
        $msg = 'Response Code ('.$params['code'].') - '.$messages;

        $prefix = 'Spry STOPPED: ';
        if (isset(self::$prefix['stop'])) {
            $prefix = self::$prefix['stop'];
        }

        self::log($prefix.$msg);

        if (!empty($params['private_data'])) {
            self::log($prefix." [START PRIVATE DATA]\n".print_r($params['private_data'], true)."\n[END PRIVATE DATA]\n");
        }
    }

    /**
     * Log a Response
     *
     * @param string $response
     *
     * @access public
     *
     * @return bool
     */
    public static function buildResponseFilter($response)
    {
        $messages = (!empty($response['messages']) && is_array($response['messages']) ? ' - '.implode(', ', (array) $response['messages']) : '');
        $msg = 'Response Code ('.$response['code'].')'.$messages;

        $prefix = 'Spry Response: ';
        if (isset(self::$prefix['response'])) {
            $prefix = self::$prefix['response'];
        }

        self::log($prefix.$msg);

        return $response;
    }

    /**
     * Log a Initiating Request
     *
     * @access public
     *
     * @return bool
     */
    public static function initialRequest()
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

        foreach ($params as $paramKey => $paramValue) {
            if (in_array(strtolower($paramKey), $secure)) {
                $params[$paramKey] = 'xxxxxx...';
            }
        }

        $prefix = 'Spry Request: ';
        if (isset(self::$prefix['request'])) {
            $prefix = self::$prefix['request'];
        }

        self::log($prefix.str_replace('Array', '', print_r($params, true)));
    }

    /**
     * Sets the error handler for all PHP errors in the App.
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     *
     * @access public
     *
     * @return void
     *
     * @final
     */
    public static function phpLogHandler($errno, $errstr, $errfile, $errline)
    {
        if (!empty($errstr) && !empty(self::$phpFile)) {
            $file = self::$phpFile;

            if (strpos($errstr, '[SQL Error]') !== false) {
                $errno = 'SQL Error';
            }

            switch ($errno) {
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
            foreach ($dbts as $dbt) {
                if (!empty($dbt['file'])) {
                    $backtrace .= ' - - Trace: '.$dbt['file'].' [Line: '.(!empty($dbt['line']) ? $dbt['line'] : '?').'] - Function: '.$dbt['function']."\n";
                }
            }

            if (isset(self::$phpFormat)) {
                $log = str_replace(
                    [
                        '%date_time%',
                        '%ip%',
                        '%request_id%',
                        '%errno%',
                        '%errstr%',
                        '%errfile%',
                        '%errline%',
                        '%backtrace%',
                    ],
                    [
                        date('Y-m-d H:i:s'),
                        self::getIp(),
                        Spry::getRequestId(),
                        $errno,
                        $errstr,
                        $errfile,
                        $errline,
                        $backtrace,
                    ],
                    self::$phpFormat
                );
            } else {
                $log = date('Y-m-d H:i:s').' '.$errstr.' '.$errfile.' [Line: '.(!empty($errline) ? $errline : '?')."]\n".$backtrace;
            }

            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file));
            }

            self::archiveFile($file);

            file_put_contents($file, $log, FILE_APPEND);
        }
    }

    /**
     * Checks the API on Shutdown for Fatal Errors.
     *
     * @access public
     *
     * @return void
     *
     * @final
     */
    public static function phpShutdownFunction()
    {
        $error = error_get_last();
        if (!empty($error['type']) && !empty($error['message'])) {
            self::phpLogHandler($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * Sets up the PHP Log Handlers.
     *
     * @access public
     *
     * @return void
     */
    public static function setupPhpLogs()
    {
        if (!empty(self::$phpFile)) {
            set_error_handler([__CLASS__, 'phpLogHandler']);
            register_shutdown_function([__CLASS__, 'phpShutdownFunction']);
        }
    }

    /**
     * Gets the Ip Address and detects if is CLI or Background Process.
     *
     * @access private
     *
     * @return string
     */
    private static function getIp()
    {
        if (empty($_SERVER['REMOTE_ADDR']) && Spry::isCli()) {
            return '127.0.0.1';
        }

        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Archives or Cleanups the file.
     *
     * @param string $file
     *
     * @access private
     *
     * @return void
     */
    private static function archiveFile($file)
    {
        if (empty(self::$maxLines) || !file_exists($file)) {
            return;
        }

        $contents = file_get_contents($file);
        $lines = explode("\n", $contents);

        if (count($lines) <= self::$maxLines) {
            return;
        }

        if (!empty(self::$archive)) {
            $archivedFile = dirname($file).'/'.basename($file).'.'.date('Y-m-d_H-i-s').'.gz';

            if ($fp = gzopen($archivedFile, 'wb')) {
                if (gzwrite($fp, $contents)) {
                    gzclose($fp);
                    file_put_contents($file, '');
                } else {
                    gzclose($fp);
                }
            }

            if (!empty(self::$maxArchives)) {
                $archives = glob(dirname($file).'/'.basename($file).'.*.gz');
                if (!empty($archives) && count($archives) > self::$maxArchives) {
                    $removeCount = count($archives) - self::$maxArchives;
                    if ($removeCount) {
                        for ($i = 0; $i < $removeCount; $i++) {
                            if (file_exists($archives[$i])) {
                                unlink($archives[$i]);
                            }
                        }
                    }
                }
            }
        } else {
            $newContent = implode("\n", array_slice($lines, -(self::$maxLines)));
            file_put_contents($file, $newContent);
        }
    }
}
