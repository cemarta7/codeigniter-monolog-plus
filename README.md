Codeigniter-Monolog-Plus
===================

Simple integration of the Monolog Package (https://github.com/Seldaek/monolog) into CodeIgniter by overwriting the CI_Log class.

Based on https://github.com/stevethomas/codeigniter-monolog but updating to support Codeigniter 3, monolog ^1.22, and supporting file logging more akin to native CodeIgniter logging.

This library registers Monolog as the PHP error handler to catch all errors and adds support for IntrospectionProcessor for additional meta data.

Supports CI-File (native style Codeigniter errors), File (RotatingFileHandler), New Relic (NewRelicHandler), HipChat (HipChatHandler), stderr (for use with Heroku), papertrail (log directly to papertrailapp.com), and graylog.org

Support now included to multi-line output into logs

Changes from Upstream
---------------------

* Added support for the Loggly driver
* Roughed in support for PHP Console driver - note that in testing this segfaulted PHP (7.4.0), not sure why or where
* Updated config to punt less things into the global config space
* Updated config to make it somewhat nicer to deal with additional drivers
* Better composer/autoload integration: **The install instructions are much different from upstream!**

Installation
------------

This package isn't in Packagist (yet) so installation must be done manually for now. 
 
* Configure this repo in your project's composer.json. See https://getcomposer.org/doc/02-libraries.md#publishing-to-a-vcs - the second code block there has a "repositories" option that you'll need to add. You will also need to manually add the "jkachel/codeigniter-monolog-plus" to your "require" section.
* Install the library: `composer install`
* Copy the config file to the CodeIgniter config file folder. The config file is in vendor/jkachel/codeigniter-monolog-plus/application/config/monolog-dist.php - copy it to "monolog.php" in your application/config folder.
* Update the config appropriately. If you're using the Loggly driver, you will need to get a token from your account; see the config file for info. 
* Create a shim for the CI_Log class. This can be as simple as this:
```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use CIMonologPlus\CIMonolog;

class CI_Log extends CIMonolog {
	public function __construct() {
		parent::__construct();
	}
}
```
* Enable Composer support in application/config/config.php. You will probably need to set the path explicitly, `BASEPATH . '/vendor/autoload.php'` tends to work. Otherwise, CI expects the Composer stuff to be in application/. 

Usage
-----
Use log_message() as normal in CodeIgniter to log error, debug and info messages. Log files are stored in the application/logs folder, unless you've changed the path.

Compatibility
-----

The shim was originally written to work with CI 3. Inadvertent testing with CI 4 worked OK but no in-depth testing has been done.

License
-------
codeigniter-monolog-plus is licensed under the MIT License - see the LICENSE file for details
