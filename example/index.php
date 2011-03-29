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
$Item = new Item();
$Item->setData(array('name'=>'item 1'));
$Item->save();
$item = $Item->fetch();

/*
 * Get this item
 */
$Item = new Item($item['id']);
var_dump($Item->fetch());
exit();
