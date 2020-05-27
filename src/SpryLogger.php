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
    private static $initiated = false;
    private static $apiFile = '';
    private static $archive = false;
    private static $logCli = true;
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

        if (isset($options['log_cli'])) {
            self::$logCli = $options['log_cli'];
        }

        if (self::$initiated || (!self::$logCli && Spry::isCli())) {
            return;
        }

        self::$initiated = true;

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
        Spry::addFilter('response', 'Spry\\SpryProvider\\SpryLogger::responseFilter');

        self::setupPhpLogs();
    }

    /**
     * Log a generic Message
     *
     * @param string|array $msg
     * @param string       $type
     *
     * @access public
     *
     * @return bool
     */
    public static function log($msg, $type = 'message')
    {
        if (!empty(self::$apiFile)) {
            $file = self::$apiFile;

            if (is_array($msg)) {
                $msg = print_r($msg, true);
            }

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

            $response = file_put_contents($file, $log, FILE_APPEND);

            Spry::runHook('spryApiLog', [
                'date' => date('Y-m-d H:i:s'),
                'ip' => self::getIp(),
                'requestId' => Spry::getRequestId(),
                'path' => Spry::getPath(),
                'message' => $msg,
                'type' => $type,
            ]);

            return !empty($response);
        }
    }



    /**
     * Log a generic Message
     *
     * @param string|array $msg
     *
     * @access public
     *
     * @return bool
     */
    public static function message($msg)
    {
        if (is_array($msg)) {
            $msg = print_r($msg, true);
        }

        $prefix = 'Spry: ';
        if (isset(self::$prefix['message'])) {
            $prefix = self::$prefix['message'];
        }

        return self::log($prefix.$msg, 'message');
    }



    /**
     * Log a generic Warning
     *
     * @param string|array $msg
     *
     * @access public
     *
     * @return bool
     */
    public static function warning($msg)
    {
        if (is_array($msg)) {
            $msg = print_r($msg, true);
        }

        $prefix = 'Spry Warning: ';
        if (isset(self::$prefix['warning'])) {
            $prefix = self::$prefix['warning'];
        }

        return self::log($prefix.$msg, 'warning');
    }



    /**
     * Log a generic Error
     *
     * @param string|array $msg
     *
     * @access public
     *
     * @return bool
     */
    public static function error($msg)
    {
        if (is_array($msg)) {
            $msg = print_r($msg, true);
        }

        $prefix = 'Spry ERROR: ';
        if (isset(self::$prefix['error'])) {
            $prefix = self::$prefix['error'];
        }

        return self::log($prefix.$msg, 'error');
    }



    /**
     * Log a Hard Stop Error
     *
     * @param mixed $response
     *
     * @access public
     *
     * @return void
     */
    public static function stop($response)
    {
        $messages = (!empty($response->messages) && is_array($response->messages) ? implode(', ', $response->messages) : '');
        $msg = 'Response Code ('.$response->code.') - '.$messages;

        $prefix = 'Spry STOPPED: ';
        if (isset(self::$prefix['stop'])) {
            $prefix = self::$prefix['stop'];
        }

        $backtrace = "\n";
        $traceStarted = false;
        $dbts = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($dbts as $dbt) {
            if (!empty($dbt['file']) && !empty($dbt['function']) && ($traceStarted || 'stop' === $dbt['function'])) {
                $backtrace .= ' - - Trace: '.$dbt['file'].' [Line: '.(!empty($dbt['line']) ? $dbt['line'] : '?').'] - Function: '.$dbt['function']."\n";
                $traceStarted = true;
            }
        }
        $privateData = '';
        if (!empty($response->privateData)) {
            $privateData = "\n[START PRIVATE DATA]\n".print_r($response->privateData, true)."\n[END PRIVATE DATA]\n";
        }

        self::log($prefix.$msg.$backtrace.$privateData, 'error');
    }

    /**
     * Log a Response
     *
     * @param object $response
     *
     * @access public
     *
     * @return object
     */
    public static function responseFilter($response)
    {
        $messages = (!empty($response->messages) && is_array($response->messages) ? ' - '.implode(', ', (array) $response->messages) : '');
        $msg = 'Response Code ('.$response->code.')'.$messages;

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

        self::log($prefix.(empty($params) ? 'Empty' : str_replace('Array', '', print_r($params, true))));
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
            $dbts = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
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

            Spry::runHook('spryPhpLog', [
                'date' => date('Y-m-d H:i:s'),
                'ip' => self::getIp(),
                'requestId' => Spry::getRequestId(),
                'path' => Spry::getPath(),
                'message' => $log,
                'errno' => $errno,
                'errstr' => $errstr,
                'errfile' => $errfile,
                'errline' => $errline,
            ]);
        }
    }

    /**
     * Checks the API on Shutdown for Fatal Errors.
     *
     * @access public
     *
     * @return void
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
        if (empty($_SERVER['REMOTE_ADDR']) && (Spry::isCli() || Spry::isCron() || Spry::isBackgroundProcess())) {
            return '127.0.0.1';
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'No IP';
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
