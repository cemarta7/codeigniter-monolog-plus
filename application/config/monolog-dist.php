<?php  if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/*
 * CodeIgniter Monolog Plus
 *
 * by Josh Highland <joshhighland@venntov.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * CodeIgniter Monolog Plus v2
 * james@jkachel.com
 *
 * If you're upgrading from the original CI Monolog package or from my v1.0 package, be advised that this config
 * file is very different from what it used to be.
 *
 */

$cimp_config = array(
	// GLOBAL OPTIONS: these affect the operation of all configured processors in the library

	// Turns on some metadata info to log entries.
	'introspection_processor' => true,
	// Items to skip. One per line.
	'exclusion_list' => array(),
	// Channel name - this affects log output. See the Monolog docs for more info on this: https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md
	// As of <add tag here eventually>, this is an incomplete implementation of this; it just passes it to the
	// created Logger object. (In other words, multiple channels aren't supported yet.)
	'channel' => 'MyApp',

	// HANDLER CONFIG: sets the individual options for each handler. You can have multiple configurations per handler.
	// Each handler here gets an array. The keys of that array are the configuration option block names (so, for example,
	// 'default', 'debug', 'production', 'marty_mcfly', etc.).
	//
	// Each configuration needs a few keys:
	// 'enabled' - boolean, whether or not this particular config is enabled
	// 'threshold' - CI threshold for logging (4 = ALL, 3 = INFO, 2 = DEBUG, 1 = ERROR)
	//
	// These settings also affect the priority list configuration further down the file:
	// - If the list includes a handler you don't have configured here, it will be skipped.
	// - If the list includes a handler with a config block you've not named here, it will be skipped.
	// -- UNLESS: there's one named default (or you explicitly include 'default').
	// - If the named handler and config have 'enabled' set to false, it will be skipped.

	'handlers' => array(
		// CI Log Handler: this is the basic CodeIgniter-style logging.
		'ci_file' => array(
			// The defaults for this. Update your settings here, use it as a base, etc.
			'default' => array(
				'enabled' => true,
				'threshold' => 4,
				// Enable multi-line logging (with newlines)
				'multiline' => true,
				// Log file path - date for log file rolling will be inserted before the extension (default .php).
				// If you change the extension you should also ensure the web server won't serve logs generated.
				'logfile' => APPPATH . '/logs/log.php'
			),
			// This is an example of a separate configuration block. This one logs just errors to a different file.
			'errorsOnly' => array(
				'enabled' => true,
				'threshold' => 1,
				// Enable multi-line logging (with newlines)
				'multiline' => true,
				// Log file path - date for log file rolling will be inserted before the extension (default .php).
				// If you change the extension you should also ensure the web server won't serve logs generated.
				'logfile' => APPPATH . '/logs/errors.php'
			)
		),
		// Syslog UDP Handler: logs to a syslogd server via UDP
		'syslogudp' => array(
			'default' => array(
				'enabled' => true,
				'threshold' => 4,
				// The hostname or IP to connect to.
				'host' => 'localhost',
				// The port - 514 is the default.
				'port' => 514,
				// An identifier for your project. Set this to something unique so it will be easy to find in the actual log.
				'ident' => 'Application',
				// Whether messages can bubble up the stack or not (see docs for your particular syslogd server)
				'bubble' => true
			),
			'errorsOnly' => array(
				'enabled' => true,
				'threshold' => 1,
				// The hostname or IP to connect to.
				'host' => 'localhost',
				// The port - 514 is the default.
				'port' => 514,
				// An identifier for your project. Set this to something unique so it will be easy to find in the actual log.
				'ident' => 'Application Errors',
				// Whether messages can bubble up the stack or not (see docs for your particular syslogd server)
				'bubble' => true
			)
		),
        // Generic File Handler: very similar to CI File, but doesn't format log entries in a CodeIgniter-sort of way
        'file' => array(
            'default' => array(
                'enabled' => false,
                'threshold' => 1,
                // Enable multi-line logging (with newlines)
                'multiline' => true,
                // Log file path - date for log file rolling will be inserted before the extension (default .php).
                // If you change the extension you should also ensure the web server won't serve logs generated.
                'logfile' => APPPATH . '/logs/log.php'
            )
        ),
	    // New Relic handler: logs to a New Relic account
	    'new_relic' => array(
	        'default' => array(
	            'enabled' => false,
                'threshold' => 1,
                // App name for your account
                'app_name' => 'APP NAME - ' . ENVIRONMENT
            )
        ),
	    // HipChat Handler: sends messages to HipChat - these will be log messages, not alerts and such
        'hipchat' => array(
            'default' => array(
                'enabled' => false,
                'threshold' => 1,
                // Your API token
                'token' => '',
                // Room that should be alerted (ID or name)
                'room_id' => '',
                // "From" field name
                'notification_name' => '',
                // Turn on notifications
                'notify' => false
            )
        ),
	    // PaperTrail Handler: logs to PaperTrail (uses Syslog UDP internally)
        'papertrail' => array(
            'default' => array(
                'enabled' => false,
                'threshold' => 1,
                // Host to connect to
                'host' => 'localhost',
                // Port to use
                'port' => 514,
                // Enable multiline messages
                'multiline' => true
            )
        ),
	    // Gelf Handler: send logs to a Gelf system
        'gelf' => array(
            'default' => array(
                'enabled' => false,
                'threshold' => 1,
                // Host to connect to
                'host' => '',
                // Port to use
                'port' => ''
            )
        ),
	    // Loggly handler: sends logs to a Loggly account - this has its own formatter that cannot be changed
        'loggly' => array(
            'default' => array(
                'enabled' => false,
                'threshold' => 1,
                // Your account token
                'token' => ''
            )
        ),
	    // PHP Console Handler: sends logs back to the browser via PHP Console - this disables itself in production
        // FIXME: this ignores all of the configuration options for this handler
        'phpconsole' => array(
            'default' => array(
                'enabled' => false,
                'threshold' => 1
            )
        )
    ),

	// PRIORITY CONFIG: sets the order that logs are written. This works in conjunction with the log threshold you
	// specify when logging an error - if you have, for instance, New Relic configured before CI Log but New Relic only
	// handles ERROR-level stuff, it'll get skipped when you log a debug message.
	//
	// This should be just a regular array, containing:
	// - array, with key being the handler name and value being the config to use
	// - string, just the handler name to use
	//
	// For just handler names, this will instruct it to look for a config block named "default" to use. If you want
	// to use one that's not explicitly named that, use the array syntax.
	//
	// If the block has enabled set to false, or isn't found, it will be skipped.

	'priority' => array(
		// This one sets the CI File errorsOnly to be the first one in the list.
		array('ci_file' => 'errorsOnly'),
		// ..then pull in syslogudp's default (whatever that might be)
		'syslogudp',
		// ..then pull in CI File again but this time the default log (which logs everything)
		array('ci_file' => 'default')
	)
);

// The following line pulls the config into the CI config namespace. Don't edit it.
$config['cimp'] = $cimp_config;

// The rest of this crap is stuff from the original version of the library and is something you should remove before
// this gets published anywhere, James.

/* GENERAL OPTIONS */
$config['handlers'] = array('ci_file', 'loggly'); // valid handlers are ci_file | file | new_relic | hipchat | stderr | papertrail | gelf | loggly | phpconsole
$config['introspection_processor'] = TRUE; // add some meta data such as controller and line number to log messages


$config['channel'] = ENVIRONMENT; // channel name which appears on each line of log
$config['threshold'] = 4; // 'ERROR' => '1', 'DEBUG' => '2',  'INFO' => '3', 'ALL' => '4'

/* CI FILE - DEFAULT CODEIGNITER LOG FILE STRUCTURE
* Log to default CI log directory (must be writable ie. chmod 757).
* Filename will be encoded to current system date, ie. YYYY-MM-DD-ci.log
*/
$config['ci_file_logfile'] = APPPATH . '/logs/log.php';
$config['ci_file_multiline'] = TRUE; //add newlines to the output

/* FILE HANDLER OPTIONS
 * Log to default CI log directory (must be writable ie. chmod 757).
 * Filename will be encoded to current system date, ie. YYYY-MM-DD-ci.log
*/
$config['file_logfile'] = APPPATH . '/logs/ci.log';
$config['file_multiline'] = TRUE; //add newlines to the output

/* NEW RELIC OPTIONS */
$config['new_relic_app_name'] = 'APP NAME - ' . ENVIRONMENT;

/* HIPCHAT OPTIONS */
$config['hipchat_app_token'] = ''; //HipChat API Token
$config['hipchat_app_room_id'] = ''; //The room that should be alerted of the message (Id or Name)
$config['hipchat_app_notification_name'] = 'Monolog'; //Name used in the "from" field
$config['hipchat_app_notify'] = false; //Trigger a notification in clients or not
$config['hipchat_app_loglevel'] = 'WARNING'; //The minimum logging level at which this handler will be triggered

/* PAPER TRAIL OPTIONS */
$config['papertrail_host'] = ''; //xxxx.papertrailapp.com
$config['papertrail_port'] = ''; //port number
$config['papertrail_multiline'] = TRUE; //add newlines to the output

/* GELF OPTIONS */
$config['gelf_host'] = ''; //xxxx.papertrailapp.com
$config['gelf_port'] = ''; //port number

/*
 * Loggly options
 * 
 * The Loggly support only has one option - 'token'. Get your customer token
 * from the setup page in Loggly and put it here. 
 *
 * Docs: https://www.loggly.com/docs/php-monolog/
 *
*/
$config['ci_monolog']['loggly']['token'] = ''; 

/*
 * SyslogUdp options
 *
 * This is different than the Syslog handler and expects to connect to a server
 * over UDP. 
 *
*/

$config['ci_monolog']['syslogudp'] = [
	'host' => 'localhost', // The hostname or IP to connect to.
	'port' => 514, // The port - 514 is the default. 
	'ident' => 'Applicaiton', // An identifier for your project. Set this to something unique so it will be easy to find in the actual log.
	'bubble' => true // Whether messages can bubble up the stack or not (see docs for your particular syslogd server)
];

// exclusion list for pesky messages which you may wish to temporarily suppress with strpos() match
$config['exclusion_list'] = array();
