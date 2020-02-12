<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use CIMonologPlus\CIMonolog;

class CI_Log extends CIMonolog {
	public function __construct() {
		parent::__construct();
	}
}