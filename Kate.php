<?php

/*
 *
 * Kate
 *
 * Single file PHP Model abstract class
 *
 * This is an abstract class to easily create data model
 * that lets you interact with databases.
 *
 * @author David Mongeau-Petitpas <dmp@commun.ca>
 * @version 0.1
 *
 */

abstract class Kate {
	
	public $_fetched = false;
	
	public function __construct($primary) {
		
	}
	
	public function fetch() {
		
	}
	
	public function save() {
		
	}
	
	public function delete() {
		
	}
	
	public function cancel() {
		
	}
	
	
	public function getData() {
		
	}
	
	public function setData($data) {
		
	}
	
}