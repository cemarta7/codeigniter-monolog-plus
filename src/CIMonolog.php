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
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogglyFormatter;
use Monolog\Handler\LogglyHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\NewRelicHandler;
use Monolog\Handler\HipChatHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Handler\PHPConsoleHandler;
use Monolog\Processor\IntrospectionProcessor;

// Make sure to install graylog2/gelf-php - composer require graylog2/gelf-php
use Gelf\Transport\UdpTransport;
use Gelf\Publisher;


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
		$failsafe->pushHandler(new \Monolog\Handler\StreamHandler(APPPATH . '/logs/log-failsafe.php'), Logger::DEBUG);

		// Step 1: grab configuration and do a few preflight checks

		// copied functionality from system/core/Common.php, as the whole CI infrastructure is not available yet
		if (!defined('ENVIRONMENT') OR !file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/monolog.php'))
		{
		    $file_path = APPPATH . 'config/monolog.php';
		}

		// Fetch the config file
		try {
			require($file_path);
		} catch(\Exception $e) {
			$failsafe->log(Logger::ERROR, 'Configuration file doesn\'t exist! ' . $e->getMessage());
		    $this->log = $failsafe;
		    return;
		}

		// Check the config array to make sure it's valid
		if(!$this->array_keys_exist(['handlers', 'priority'], $cimp_config)) {
			$failsafe->log(Logger::ERROR, 'Configuration file doesn\'t set handlers or priority properly');
			$this->log = $failsafe;
			return;
		}

		if(count($cimp_config['priority']) == 0) {
			$failsafe->log(Logger::ERROR, 'Nothing set for priority. This is probably not what you want.');
			$this->log = $failsafe;
			return;
		}

		if(count(array_keys($cimp_config['handlers'])) == 0) {
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

		if ($this->config['introspection_processor'])
		{
			// add controller and line number info to each log message
			$this->log->pushProcessor(new \Monolog\Processor\IntrospectionProcessor());
		}

		$handlersAdded = 0;

		foreach(array_reverse($cimp_config['priority']) as $log_def) {
			if(is_array($log_def)) {
				$keys = array_keys($log_def);
				$handler = $keys[0]; // TODO: either polyfill this or refactor when PHP 7.2 goes out of support, since 7.3 has an array_key_first
				$cbname = $log_def[$handler];

				$failsafe->log(Logger::INFO, "processing priority for an assoc: handler is {$handler} and confblock name is {$cbname}");

				if(!array_key_exists($handler, $cimp_config['handlers'])) {
				    $failsafe->log(Logger::DEBUG, 'handler not found so skipping entirely');
				    break;
                }

				if(!array_key_exists($cbname, $cimp_config['handlers'][$handler])) {
				    $failsafe->log(Logger::DEBUG, 'confblock name not found, defaulting to \'default\'');
				    $cbname = 'default';
                }

				// rerun the above to make sure there is one

                if(!array_key_exists($cbname, $cimp_config['handlers'][$handler])) {
                    $failsafe->log(Logger::DEBUG, 'confblock name really not found, skipping');
                    break;
                }

                // throw it into the addLogHandler method - it checks for enabled on its own anyway

                $this->addLogHandler($handler, $cimp_config['handlers'][$handler][$cbname], $failsafe);
                $handlersAdded++;
			} elseif(gettype($log_def) == 'string') {
			    $failsafe->log(Logger::DEBUG, 'processing priority for default for this string: ' . $log_def);

				if(array_key_exists($log_def, $cimp_config['handlers']) && array_key_exists('default', $cimp_config['handlers'][$log_def])) {
                    $this->addLogHandler($log_def, $cimp_config['handlers'][$log_def]['default'], $failsafe);
                    $handlersAdded++;
				} else {
				    $failsafe->log(Logger::DEBUG, 'string handler didn\'t have a default, skipped');
                }
			} else {
				$failsafe->log(Logger::ERROR, 'Tried to configure a logger and did not know what was passed: ' . print_r($log_def, true));
			}
		}

		if($handlersAdded == 0) {
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

	public function addLogHandler($handler, $confblock, &$failsafe = false) {
		if(!$confblock['enabled']) {
		    if($failsafe !== false) {
		        $failsafe->log(Logger::INFO, 'addLogHandler: handler for ' . $handler . ' is set to false, skipping');
            }

			return true;
		}

		$errHnd = $formatter = false;

        if($failsafe !== false) {
            $failsafe->log(Logger::INFO, 'addLogHandler: adding handler for ' . $handler . ': ' . print_r($confblock, true));
        }

        switch($handler) {
            case 'ci_file':
                $errHnd = new \Monolog\Handler\RotatingFileHandler($confblock['logfile']);
                $formatter = new \Monolog\Formatter\LineFormatter("%level_name% - %datetime% --> %message% %extra%\n", null, $confblock['multiline'] ? true : false);
                $errHnd->setFormatter($formatter);
                if($failsafe !== false) {
                    $failsafe->log(Logger::INFO, 'addLogHandler: log handler for CI_Log set up');
                }

                break;

            case 'syslogudp':
                $errHnd = new \Monolog\Handler\SyslogUdpHandler($confblock['host'], is_int($confblock['port']) ? $confblock['port'] : 514, LOG_USER, $confblock['threshold'], $confblock['bubble'] === true, $confblock['ident']);
                if($failsafe !== false) {
                    $failsafe->log(Logger::INFO, 'addLogHandler: log handler for SyslogUDP set up');
                }
                break;

            default:
                if($failsafe !== false) {
                    $failsafe->log(Logger::INFO, 'addLogHandler: handler \'' . $handler . '\' not recognized');
                }

                break;
		}

		if($errHnd !== false) {
            if($failsafe !== false) {
                $failsafe->log(Logger::INFO, 'addLogHandler: handler for ' . $handler . ' actually added');
            }

            $this->log->pushHandler($errHnd);
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
	public function write_log($level = 'error', $msg)
	{
		$level = strtoupper($level);

		// verify error level
		if (!isset($this->_levels[$level]))
		{
			$this->log->error('unknown error level: ' . $level);
			$level = 'ALL';
		}

		// filter out anything in $this->config['exclusion_list']
		if (!empty($this->config['exclusion_list']))
		{
			foreach ($this->config['exclusion_list'] as $findme)
			{
				$pos = strpos($msg, $findme);
				if ($pos !== false)
				{
					// just exit now - we don't want to log this error
					return true;
				}
			}
		}

//		if ($this->_levels[$level] <= $this->config['threshold'])
//		{
			switch ($level)
			{
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
//		}
		return true;
	}

	private function array_keys_exist($keys, $array) {
		foreach($keys as $milton) {
			if(!array_key_exists($milton, $array)) {
				return false;
			}
		}

		return true;
	}

} //end class
