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


/* GENERAL OPTIONS */
$config['handlers'] = array('ci_file', 'loggly'); // valid handlers are ci_file | file | new_relic | hipchat | stderr | papertrail | gelf | loggly | phpconsole
$config['channel'] = ENVIRONMENT; // channel name which appears on each line of log
$config['threshold'] = 4; // 'ERROR' => '1', 'DEBUG' => '2',  'INFO' => '3', 'ALL' => '4'
$config['introspection_processor'] = TRUE; // add some meta data such as controller and line number to log messages

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
