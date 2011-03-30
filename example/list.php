<?php

/*
 *
 * Create Zend Db Object
 *
 */
set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__).'/');

require_once 'Zend/Db.php';
$db = Zend_Db::factory('pdo_mysql',array(
	'host' => 'localhost',
	'username' => 'test',
	'password' => '1234',
	'dbname' => 'test'
));
$db->getConnection();
$db->query("SET NAMES 'utf8'");


/*
 *
 * Kate example
 *
 */
require 'Item.php';
Kate::setDefaultDatabase($db);

/*
 * Create new item
 */
$items = Item::getItems(array('id'=>array(6,7,8)));

var_dump($items);
echo Item::getItemsCount();
