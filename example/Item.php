<?php

require_once '../Kate.php';


class Item extends Kate {
	
	protected $_source = array(
		'type' => 'db',
		'table' => array(
			'name' => array('i' => 'items'),
			'primary' => 'id',
			'fields' => '*'
		)
	);
	
	
}