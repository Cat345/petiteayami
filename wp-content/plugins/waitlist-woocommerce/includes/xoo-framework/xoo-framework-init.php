<?php

namespace XooWL\Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const XOO_FW_DIR = __DIR__;
const XOO_FW_VERSION = '2.0.1';


function xoo_framework_includes(){
	require_once __DIR__.'/class-xoo-helper.php';
	require_once __DIR__.'/class-xoo-exception.php';
}

xoo_framework_includes();