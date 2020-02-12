<?php

/*
 * CI Monolog Plus installer
 *
 * This copies the CI_Log shim and the config file to the appropriate folders
 * in your CodeIgniter install. It does not have to be run but will save a 
 * step or two.
*/

if(!defined(READLINE_LIB)) {
	echo "CI Monolog Plus installer failed: no readline support in PHP. You'll have to copy the files in " . __DIR__ . "/application yourself.\n";
	exit();
}

echo "Current directory is: " . __DIR__ . "\n";

if(strotolower(trim(readline("Automatically install support stuff? (y/n)")) == 'y') {
	$ci_base = __DIR__ . '/../../../';

	if(!is_dir($ci_base . 'application/core')) {
		$ci_base = __DIR__ . '/../../../../';

		if(!is_dir($ci_base . 'application/core')) {
			echo "Not sure where your Code Igniter application is. Please provide the path to the folder containing your appllication folder.";
			$ci_base = readline('Path: ');

			if(!is_dir($ci_base . 'application/core')) {
				echo "\nCan't find the core folder within application in {$ci_base}, exiting. You'll have to copy the files over yourself.\n";
				exit();
			}
		}
	}

	echo "\nUsing {$ci_base} as the base folder to copy the shim and configuration file.\n";

	if(!copy(__DIR__ . '/application/config/monolog-dist.php', $ci_base . '/application/config/monolog-dist.php')) {
		echo "\nCouldn't copy the config file, exiting.\n";
		exit();
	}

	if(!copy(__DIR__ . '/application/core/Log.php', $ci_base . '/application/core/Log.php')) {
		echo "\nCouldn't copy the Log class shim, exiting.\n";
		exit();
	}

	echo "Ok! Files are copied. Make sure you copy monolog-dist.php to monolog.php and edit it with your own settings.\n\n";
} else {
	echo "Ok then.\n";
}