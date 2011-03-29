<?php

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


require 'Item.php';
Kate::setDefaultDatabase($db);


$Item = new Item();
$Item->setData(array('name'=>'item 1'));
$Item->save();
$item = $Item->fetch();

$Item = new Item($item['id']);
var_dump($Item->fetch());
exit();
