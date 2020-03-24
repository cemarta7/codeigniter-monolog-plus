# Codeigniter-Monolog-Plus V2

This is a fork and a now mostly rewrite of [Codeigniter-Monolog-Plus](https://github.com/JoshHighland/codeigniter-monolog-plus) by @JoshHighland, which itself is based on [Codeigniter-Monolog](https://github.com/stevethomas/codeigniter-monolog) by @SteveThomas.

CodeIgniter Monolog Plus brings the [Monolog](https://github.com/Seldaek/monolog) logging package into a CodeIgniter 3 project, replacing the built-in _CI_Log_ library and adding a bunch of configurability. Multiple loggers can be set up for various purposes, locations, and services, and individual thresholds can be set to have their own logging.

This started as a simple fork to add Loggly, PHP Console, and SyslogUdp (bare) support and ended up being a bit of a rewrite to make things more useful. It also now more closely integrates in with Composer. 

## Changes from Upstream

* Added support for the Loggly, PHP Console, and SyslogUdp drivers
* Better composer/autoload integration: **The install instructions are much different from upstream!**
* A semi-automated installer - will copy the shim and config file over for you
* Multiple configuration blocks for each handler
* Ability to set a priority list of loggers, so you can define which services get logged to in order
* Debug facility for the library itself and a failsafe if it's not able to read your config file

## Installation

This package is now in Packagist so `composer require jkachel/codeigniter-monolog-plus` ought to be sufficient. If you'd rather add dev-master:  
 
* Configure this repo in your project's composer.json. See https://getcomposer.org/doc/02-libraries.md#publishing-to-a-vcs - the second code block there has a "repositories" option that you'll need to add. 
* Add the package. `composer require jkachel/codeigniter-monolog-plus:dev-master`
* Install the shim and config file.
  * Optionally: run the installer. cd into the `vendor/jkachel/codeigniter-monolog-plus` folder and then run `installer.php`. This will copy the files needed to integrate the library with a standard CodeIgniter 3 install. It will try to figure it out on its own; if it can't, you will be prompted for the base path for your project.
  * You can also just copy the files from application/ yourself. They go in the same places they are in the application root.
  * Make sure you rename monolog-dist.php to monolog.php in the config folder.
* Update the config file. The file itself is documented, or see the section below.
* Enable Composer support in application/config/config.php. You will probably need to set the path explicitly, `BASEPATH . '/vendor/autoload.php'` tends to work. Otherwise, CI expects the Composer stuff to be in application/. 

## Usage

Use log_message() as normal in CodeIgniter to log error, debug and info messages. File loggers log to the files you've specified, and everything else logs as you've set it in the configuration file.

## Log Levels

This library does not (yet) use the standard Monolog/RFC 5424 log levels; it still depends on the CodeIgniter defaults. They're mapped in this manner:
* 'error' => *ERROR* (400)
* 'info' => *INFO* (200)
* 'debug' => *DEBUG* (100)

CI also defines an "all", which maps to DEBUG. It's the lowest level, so it's a catch-can for everything.

## Configuration

_*The configuration file is very different from the upstream library and is not compatible.*_

The library structures configuration options into a single array, with three main sections:
1. Global options - these are things that pertain to the entire library (and are mostly applied to the Monolog instance itself)
1. Handler options - settings for each individual handler that you might want
1. Priority list - the order in which to log things (as a base, the threshold also takes effect here)

The array itself is just a standard associative array, like such:

```php
$config = array(
    // Global opts
    'introspection_processor' => true,
    ...
    // Handlers
    'handlers' => array(
        'ci_file' => array( 'default' => array( /* its config options */ ), 'testConfig' => array()),
        'file' => array( 'default' => array() ),   
    ),
    // Priority
    'priority' => array(
        'ci_file', // use default settings for this one
        array('file' => 'justInfos') // specific settings for this handler
    ) 
);
```

Note that simply having the handler in the config file is not necessarily enough to make the handler work - in most cases, you'll also need to make sure the packages are there for that given handler. (For example, if you want to use the Loggly handler, you'll need to install the Loggly package through composer.) See the Monolog documentation for details on what you'll need to install for the given handler you want to use. The exceptions here are the file-based ones and the stream/stderr one; those are part of the core Monolog package and are automatically installed.

### Global Options

These go at the root of the array. 

* `introspection_processor` - Boolean, turns on/off some additional data output (file, line, class, etc. though this will mostly be from the log library itself)
* `exclusion_list` - Array, strings to skip logging
* `channel` - String, sets the channel name. This is just passed to the Monolog constructor; this library doesn't necessarily support different channels quite yet.

### Handler Options

Each handler can have as many blocks as you require, but each one that's actually being used should have a default block. The library will look for a default block if it can't find a named one or if you specify the handler without a block name. The handlers themselves are:

* `file` - Basic file logger, with rotation.
* `ci_file` - Mostly the same as `file`, but logs are written with a formatter tha makes them more "CodeIgniter"-y.
* `syslogudp` - Logs to a Syslog server over UDP.
* `new_relic` - Logs to a New Relic account.
* `gelf` - Logs to a Gelf account.
* `hipchat` - Logs to a HipChat room.
* `stderr` - Logs to stderr (and therefore probably to your PHP error log)
* `papertrail` - Logs to a PaperTrail account.
* `loggly` - Logs to a Loggly account.
* `phpconsole` - Logs to PHP Console. (This is very unsafe in production so if your app-wide ENVIRONMENT define is set to 'production' it will disable itself.) 

The blocks are another associative array. They all must have these two keys:
* `enabled` - boolean, turns the block on or off
* `threshold` - integer, the (numeric) threshold for log messages. This is mostly the RFC 5424 thresholds divided by 100 (so 1, 2, 4); less permissive as they go up (so 1 - DEBUG - gets everything, essentially).

Outside of that, each handler has its own set of options. 

#### file / ci_file

These two use the same Monolog handler, so they have the same options. 
* `multiline` - Boolean, allows for newlines in the log output
* `logfile` - The location to log files to. The log handler will adjust the file name on its own to add dates.

#### syslogudp

* `host` - The host to connect to
* `port` - The port to connect to (default 514). As the name should indicate, this will connect via UDP.
* `bubble` - Allow messages to bubble upwards (default to true). If this is set to false, logging will stop once it hits this handler. 
* `ident` - The syslog identity for the app.

#### new_relic

* `app_name` - The application name to use.

#### hipchat

* `token` - Your HipChat API token.
* `room_id` - The room to send messages to (ID or name).
* `notification_name` - The identity to post messages as.
* `notify` - Boolean, send notifications to clients when messages are sent.

#### papertrail

* `host` - The host to connect to.
* `port` - The port to use.
* `multiline` - Boolean, enables newlines in the output.

#### gelf

* `host` - The host to connect to.
* `port` - The port to use.

#### loggly

* `token` - Your Loggly API token.

#### phpconsole

If you're using phpconsole, you do need to set up a block for the `enable` and `threshold` options, but at present this library does not expose any other options.

### Priority

Priority is simply a list of handlers. They're in FIFO order - first one in the list gets priority. Each entry is one of two things:
* The name of the handler to use - 'ci_file', 'loggly', etc. This will use the default block in the configuration for the specified handler.
* A key-value pair specifying the handler to use and what config block to use. Specify this as `array('handler' => 'block name')`. The code will pull the first key and use it for the handler name and the value for it for the conf block to use. If the block isn't found, it will try to use the default one. 

If a suitable setup isn't found for the entry, it will just be skipped. It will also skip it if the block has enabled set to false. 

### Debug Mode/Failsafe Logging

The library has a debugging mode. This is especially useful if you're doing a lot with configuration settings or if you're adding in support for other handlers. To enable this, define "CIMONOLOGDEBUG" in your index.php file and set it to true.

There is also a failsafe for log functions if there are issues with the config file. (This functionality is used for debug mode logging as well.) The failsafe log is just another Monolog instance that's configured to write to _application root_/application/logs/log-failsafe.php - if you're seeing that file in there, that likely means you have something not set up right in the config file and it's confused. 

A few error modes will trigger the failsafe logging to be enabled (including debug mode) but it will not trigger if there's simply nothing configured to log to: if all the config blocks are set to disabled, or if they're empty, it will set up a Monolog instance without any actual handlers in it.

## Compatibility

The shim was originally written to work with CI 3. Inadvertent testing with CI 4 worked OK but no in-depth testing has been done. 4 uses its own better logging stuff, though.

## License

codeigniter-monolog-plus is licensed under the MIT License - see the LICENSE file for details
