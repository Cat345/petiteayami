<?php
if (!defined('ABSPATH')) exit;
 return array(
 'root' => array(
 'name' => '__root__',
 'pretty_version' => 'dev-trunk',
 'version' => 'dev-trunk',
 'reference' => '2204b5ca89b23922c978392fbc797fd23a30a494',
 'type' => 'library',
 'install_path' => __DIR__ . '/../../',
 'aliases' => array(),
 'dev' => false,
 ),
 'versions' => array(
 '__root__' => array(
 'pretty_version' => 'dev-trunk',
 'version' => 'dev-trunk',
 'reference' => '2204b5ca89b23922c978392fbc797fd23a30a494',
 'type' => 'library',
 'install_path' => __DIR__ . '/../../',
 'aliases' => array(),
 'dev_requirement' => false,
 ),
 'automattic/jetpack-autoloader' => array(
 'pretty_version' => 'dev-trunk',
 'version' => 'dev-trunk',
 'reference' => 'd202767bf7a3286851762977d7ba4389437ffcc5',
 'type' => 'composer-plugin',
 'install_path' => __DIR__ . '/../automattic/jetpack-autoloader',
 'aliases' => array(
 0 => '5.0.x-dev',
 ),
 'dev_requirement' => false,
 ),
 'dragonmantank/cron-expression' => array(
 'pretty_version' => 'v3.5.0',
 'version' => '3.5.0.0',
 'reference' => '1b2de7f4a468165dca07b142240733a1973e766d',
 'type' => 'library',
 'install_path' => __DIR__ . '/../dragonmantank/cron-expression',
 'aliases' => array(),
 'dev_requirement' => false,
 ),
 'mixpanel/mixpanel-php' => array(
 'pretty_version' => '2.11.0',
 'version' => '2.11.0.0',
 'reference' => '4b0fafacf2129eff5d50721e129b07f0c32687e7',
 'type' => 'library',
 'install_path' => __DIR__ . '/../mixpanel/mixpanel-php',
 'aliases' => array(),
 'dev_requirement' => false,
 ),
 'mtdowling/cron-expression' => array(
 'dev_requirement' => false,
 'replaced' => array(
 0 => '^1.0',
 ),
 ),
 'woocommerce/action-scheduler' => array(
 'pretty_version' => '3.9.3',
 'version' => '3.9.3.0',
 'reference' => 'c58cdbab17651303d406cd3b22cf9d75c71c986c',
 'type' => 'wordpress-plugin',
 'install_path' => __DIR__ . '/../woocommerce/action-scheduler',
 'aliases' => array(),
 'dev_requirement' => false,
 ),
 'woocommerce/email-editor' => array(
 'pretty_version' => '2.18.0',
 'version' => '2.18.0.0',
 'reference' => 'f636bf3beef655dc0037ee7206ef10b4100cc1da',
 'type' => 'wordpress-plugin',
 'install_path' => __DIR__ . '/../woocommerce/email-editor',
 'aliases' => array(),
 'dev_requirement' => false,
 ),
 ),
);
