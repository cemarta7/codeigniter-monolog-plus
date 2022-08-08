<?php
namespace CIMonologPlus;

/*
 * CodeIgniter Monolog Plus
 *
 * Version 1.4.3
 * (c) Josh Highland <JoshHighland@venntov.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\SyslogUdpHandler;

/**
 *  replaces CI's Logger class, use Monolog instead
 *
 *  see https://github.com/stevethomas/codeigniter-monolog & https://github.com/Seldaek/monolog
 *
 */
class CIMonolog
{
    // CI log levels
    protected $_levels = array(
                'OFF' => '0',
                'ERROR' => '1',
                'DEBUG' => '2',
                'INFO' => '3',
                'ALL' => '4'
    );

    // config placeholder
    protected $config = array();

    protected function resolveConfigFile($filename)
    {
        // copied functionality from system/core/Common.php, as the whole CI infrastructure is not available yet

        if (!defined('ENVIRONMENT') or !file_exists(APPPATH . 'config/' . ENVIRONMENT . '/' . $filename)) {
            return APPPATH . 'config/' . $filename;
        }

        return APPPATH . 'config/' . ENVIRONMENT . '/' . $filename;
    }

    /**
     * prepare logging environment with configuration variables
     */
    public function __construct()
    {
        // Step 0: register a failsafe logger - if this thing can't initialize, it will use this instead
        // and this also gives me the ability to log that it broke
        // If the standard CI log directory isn't writable, this will fail also.
        // If the preflight checks don't pass, you'll get the failsafe handler - otherwise we'll spin up a new Monolog instance and jettison this one

        $failsafe = new Logger('CIMonologFailsafe');

        $fsLogLevel = defined('CIMONOLOGDEBUG') ? Logger::DEBUG : Logger::INFO;

        // a change! 2020-16-6 jkachel: so guess what fails if the log folder isn't writable and you need the logs elsewhere anyway yep it's this
        // so now we'll get the default log path out of the CI config and use that, with a fallback to the APPPATH one if it's not set

        try {
            require($this->resolveConfigFile('config.php'));
        } catch (\Exception $e) {
            trigger_error("CI Monolog Plus: Can't open the base config file. There is probably something wrong with your application's configuration.", E_USER_ERROR);
        }

        if (trim($config['log_path']) == '' || !file_exists($config['log_path'])) {
            $failsafePath = APPPATH . '/logs/log-failsafe.php';
        } else {
            $failsafePath = $config['log_path'] . '/log-failsafe.php';
        }

        $failsafe->pushHandler(new \Monolog\Handler\StreamHandler($failsafePath), $fsLogLevel);

        // Step 1: grab configuration and do a few preflight checks

        // Fetch the config file
        // note this has changed too; it now uses the config file resolver
        try {
            require($this->resolveConfigFile('monolog.php'));
        } catch (\Exception $e) {
            $failsafe->log(Logger::ERROR, 'Configuration file doesn\'t exist! ' . $e->getMessage());
            $this->log = $failsafe;
            return;
        }

        // Check the config array to make sure it's valid
        if (!$this->array_keys_exist(['handlers', 'priority'], $cimp_config)) {
            $failsafe->log(Logger::ERROR, 'Configuration file doesn\'t set handlers or priority properly');
            $this->log = $failsafe;
            return;
        }

        if (count($cimp_config['priority']) == 0) {
            $failsafe->log(Logger::ERROR, 'Nothing set for priority. This is probably not what you want.');
            $this->log = $failsafe;
            return;
        }

        if (count(array_keys($cimp_config['handlers'])) == 0) {
            $failsafe->log(Logger::ERROR, 'Handlers aren\'t set up right. This is probably not what you want.');
            $this->log = $failsafe;
            return;
        }

        // make $config from config/monolog.php accessible to $this->write_log()
        $this->config = $cimp_config;

        // Step 2: spin up the Monolog instance and get going

        $this->log = new Logger($cimp_config['channel']);
        
        // detect and register all PHP errors in this log hence forth
        ErrorHandler::register($this->log);

        if ($this->config['introspection_processor']) {
            // add controller and line number info to each log message
            $this->log->pushProcessor(new \Monolog\Processor\IntrospectionProcessor());
        }

        $handlersAdded = 0;

        foreach (array_reverse($cimp_config['priority']) as $log_def) {
            if (is_array($log_def)) {
                $keys = array_keys($log_def);
                $handler = $keys[0]; // TODO: either polyfill this or refactor when PHP 7.2 goes out of support, since 7.3 has an array_key_first
                $cbname = $log_def[$handler];

                $failsafe->log(Logger::INFO, "processing priority for an assoc: handler is {$handler} and confblock name is {$cbname}");

                if (!array_key_exists($handler, $cimp_config['handlers'])) {
                    $failsafe->log(Logger::DEBUG, 'handler not found so skipping entirely');
                    break;
                }

                if (!array_key_exists($cbname, $cimp_config['handlers'][$handler])) {
                    $failsafe->log(Logger::DEBUG, 'confblock name not found, defaulting to \'default\'');
                    $cbname = 'default';
                }

                // rerun the above to make sure there is one

                if (!array_key_exists($cbname, $cimp_config['handlers'][$handler])) {
                    $failsafe->log(Logger::DEBUG, 'confblock name really not found, skipping');
                    break;
                }

                // throw it into the addLogHandler method - it checks for enabled on its own anyway

                $this->addLogHandler($handler, $cimp_config['handlers'][$handler][$cbname], $failsafe);
                $handlersAdded++;
            } elseif (gettype($log_def) == 'string') {
                $failsafe->log(Logger::DEBUG, 'processing priority for default for this string: ' . $log_def);

                if (array_key_exists($log_def, $cimp_config['handlers']) && array_key_exists('default', $cimp_config['handlers'][$log_def])) {
                    $this->addLogHandler($log_def, $cimp_config['handlers'][$log_def]['default'], $failsafe);
                    $handlersAdded++;
                } else {
                    $failsafe->log(Logger::DEBUG, 'string handler didn\'t have a default, skipped');
                }
            } else {
                $failsafe->log(Logger::ERROR, 'Tried to configure a logger and did not know what was passed: ' . print_r($log_def, true));
            }
        }

        if ($handlersAdded == 0) {
            $failsafe->log(Logger::ERROR, 'Couldn\'t set up a logger based on configured priority, so defaulting to the failsafe one.');
            $this->log = $failsafe;
            return;
        }

        $this->write_log('DEBUG', 'Monolog replacement logger initialized, ' . $handlersAdded . 'configured');
    }

    /**
     * Adds a log handler to the instance Monolog object. Any added after the constructor does its thing will take
     * priority over the other logging methods, as Monolog uses a stack for that.
     *
     * If you'd like to add more handlers to the supported ones, here's where you'd do that.
     *
     * If the confblock has "enabled" set to false, it will just skip it.
     *
     * @param string $handler - The handler to configure
     * @param array $confblock - Configuration settings for the handler
     * @param object|bool $failsafe - The failsafe log handler, or false if one's not been set up. This is passed by ref.
     * @return bool
     */

    public function addLogHandler($handler, $confblock, &$failsafe = false)
    {
        if (!$confblock['enabled']) {
            if ($failsafe !== false) {
                $failsafe->log(Logger::DEBUG, 'addLogHandler: handler for ' . $handler . ' is set to false, skipping');
            }

            return true;
        }

        $errHnd = $formatter = false;

        if ($failsafe !== false) {
            $failsafe->log(Logger::DEBUG, 'addLogHandler: adding handler for ' . $handler . ': ' . print_r($confblock, true));
        }

        // determine threshold
        // note that Monolog supports more than this; they're just not configured right now.
        // the ordering on this is weird too - debug and all are essentially the same.

        switch ($confblock['threshold']) {
            case 0:
                return; // 0 = off

            case 1:
                $threshold = Logger::ERROR;
                break;

            case 3:
                $threshold = Logger::INFO;
                break;

            default:
                $threshold = Logger::DEBUG;
                break;
        }

        switch ($handler) {
            case 'ci_file':
                $errHnd = new \Monolog\Handler\RotatingFileHandler($confblock['logfile'], 0, $threshold);
                $formatter = new \Monolog\Formatter\LineFormatter("%level_name% - %datetime% --> %message% %extra%\n", null, $confblock['multiline'] ? true : false);
                $errHnd->setFormatter($formatter);
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for CI_Log set up');
                }

                break;

            case 'syslogudp':
                $errHnd = new \Monolog\Handler\SyslogUdpHandler($confblock['host'], is_int($confblock['port']) ? $confblock['port'] : 514, LOG_USER, $threshold, $confblock['bubble'] === true, $confblock['ident']);
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for SyslogUDP set up');
                }
                break;

            case 'file':
                $errHnd = new \Monolog\Handler\RotatingFileHandler($confblock['logfile'], 0, $threshold);
                $formatter = new  \Monolog\Formatter\LineFormatter(null, null, $confblock['multiline']);
                $errHnd->setFormatter($formatter);
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for File set up');
                }
                break;

            case 'new_relic':
                $errHnd = new \Monolog\Handler\NewRelicHandler(Logger::ERROR, true, $confblock['app_name']);
                break;

            case 'hipchat':
                $errHnd = new \Monolog\Handler\HipChatHandler(
                    $confblock['app_token'],
                    $confblock['app_room_id'],
                    $confblock['app_notification_name'],
                    $confblock['app_notify'],
                    $confblock['app_loglevel']
                );
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for New Relic set up');
                }
                break;

            case 'stderr':
                $errHnd = new \Monolog\Handler\StreamHandler('php://stderr', $threshold);
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for stderr set up');
                }
                break;

            case 'papertrail':
                $errHnd = new SyslogUdpHandler($confblock['host'], $confblock['port']);
                $formatter = new  \Monolog\Formatter\LineFormatter("%channel%.%level_name%: %message% %extra%", null, $confblock['multiline']);
                $errHnd->setFormatter($formatter);
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for Papertrail set up');
                }
                break;

            case 'gelf':
                $transport = new \Gelf\Transport\UdpTransport($confblock['host'], $confblock['port']);
                $publisher = new \Gelf\Publisher($transport);
                $formatter = new  \Monolog\Formatter\GelfMessageFormatter();
                $errHnd = new \Monolog\Handler\GelfHandler($publisher, $threshold);
                $errHnd->setFormatter($formatter);
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for Gelf set up');
                }
                break;

            case 'loggly':
                $errHnd = new \Monolog\Handler\LogglyHandler($confblock['token'] . '/tag/monolog', $threshold);
                $formatter = new  \Monolog\Formatter\LogglyFormatter();
                $errHnd->setFormatter($formatter);
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for Loggly set up');
                }
                break;

            case 'phpconsole':
                if (ENVIRONMENT !== 'development' && ENVIRONMENT !== 'testing') {
                    if ($failsafe !== false) {
                        $failsafe->log(Loggly::ERROR, 'CI Monolog: Environment is ' . ENVIRONMENT . ', not activating PHP Console error logging.');
                    }
                } else {
                    $errHnd = new \Monolog\Handler\PHPConsoleHandler(array(), null, $threshold);
                    if ($failsafe !== false) {
                        $failsafe->log(Logger::DEBUG, 'addLogHandler: log handler for PHP Console set up');
                    }
                }
                break;

            default:
                if ($failsafe !== false) {
                    $failsafe->log(Logger::DEBUG, 'addLogHandler: handler \'' . $handler . '\' not recognized');
                }

                break;
        }

        if ($errHnd !== false) {
            $this->log->pushHandler($errHnd);

            if ($failsafe !== false) {
                $failsafe->log(Logger::DEBUG, 'addLogHandler: handler for ' . $handler . ' at threshold ' . $threshold . ' actually added');
            }
        }

        return true;
    }

    /**
     * Write to defined logger. Is called from CodeIgniters native log_message()
     *
     * @param string $level
     * @param $msg
     * @return bool
     */
    public function write_log($level = 'error', $msg = '')
    {
        $level = strtoupper($level);

        // verify error level
        if (!isset($this->_levels[$level])) {
            $this->log->error('unknown error level: ' . $level);
            $level = 'ALL';
        }

        // filter out anything in $this->>config['exclusion_list']
        if (!empty($this->config['exclusion_list'])) {
            foreach ($this->config['exclusion_list'] as $findme) {
                $pos = strpos($msg, $findme);
                if ($pos !== false) {
                    // just exit now - we don't want to log this error
                    return true;
                }
            }
        }

        if ($this->config['debug']==='false'&&$level=='DEBUG') {
            return true;
        };

        switch ($level) {
            case 'ERROR':
                $this->log->error($msg);
                break;

            case 'DEBUG':
                $this->log->debug($msg);
                break;

            default:
                $this->log->info($msg);
                break;
        }
        return true;
    }

    private function array_keys_exist($keys, $array)
    {
        foreach ($keys as $milton) {
            if (!array_key_exists($milton, $array)) {
                return false;
            }
        }

        return true;
    }
} //end class
