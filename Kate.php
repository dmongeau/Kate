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
 *
 */

abstract class Kate {
	
	
	/*
	 *
	 * Overwrite this
	 *
	 */
	static $source = array(
		'type' => 'db',
		'table' => array(
			'name' => array('i' => 'items'),
			'primary' => 'id',
			'fields' => '*'
		)
	);
	
	
	/*
	 *
	 * Core properties
	 *
	 */
	
	
	protected static $_defaultDb;
	protected static $_currentQuery;
	protected static $_currentSelect;
	protected static $_currentItemsCount;
	
	
	protected $_db;
	protected $_cache;
	
	protected $_primary;
	protected $_id;
	protected $_data = array();
	protected $_describeTable;
	
	public $_isNew = false;
	public $_fetched = false;
	
	public function __construct($primary = null) {
		
		if(isset($primary)) {
			$primary = $this->verifyPrimary($primary);
			$this->setPrimary($primary);
		}
		
	}
	
	public function fetch() {
		
		$db = $this->getDatabase();
		
		$select = $db->select()->from($this->_getTable(),$this->_getTableFields());
		
		$select->where($this->_getTablePrimary(true).' = ?', $this->getPrimary());
		
		if(method_exists($this,'_filterSelect')) {
			$filterSelect = $this->_filterSelect($select);
			if(isset($filterSelect)) $select = $filterSelect;
		} 
		
		$leftJoins = $this->_getTableLeftJoins();
		if(isset($leftJoins) && sizeof($leftJoins)) {
			foreach($leftJoins as $leftJoin) {
				if(sizeof($leftJoin) == 3) $select->joinLeft($leftJoin[0],$leftJoin[1],$leftJoin[2]);	
			}
		}
		
		$data = $db->fetchRow($select);
		
		if(!$data) throw new App_Exception('Il s\'est produit une erreur',404);
		
		$this->setData($data,false);
		$this->_fetched = true;
		
		return $this->getData();
		
	}
	
	public function save() {
		
		$db = $this->getDatabase();
		
		$primary = $this->getPrimary();
		$source = self::getSource();
		$inputs = $this->getData();
			
		$data = array();	
		foreach($inputs as $key => $value) {
			if(method_exists($this,'_put'.$key)) eval('$data = $this->_put'.$key.'($data,$value,$inputs);');
			else $data[$key] = $value;
		}
		
		$data = $this->_filterTableFields($data);
		
		if(sizeof($data)) {
			if(!$primary) {
						
				if(isset($source['table']['nowFields'])) {
					$nowFields = is_array($source['table']['nowFields']) ? $source['table']['nowFields']:array($source['table']['nowFields']);
					foreach($nowFields as $field) {
						if(!isset($data[$field])) $data[$field] = new Zend_Db_Expr('NOW()');
					}
					$data = $this->_filterTableFields($data);
				}
				
				$db->insert($this->_getTable(),$data);
				$this->setPrimary($db->lastInsertId());
				$this->isNew(true);
				
			} else {
				$where = $db->quoteInto($this->_getTablePrimary().' = ?', $primary);
				$db->update($this->_getTableName(),$data,$where);
			}
		}
		
	}
	
	public function delete() {
		
		$db = $this->getDatabase();
		
		$primary = $this->getPrimary();
		$source = self::getSource();
		
		if($primary) {
			
			$where = $db->quoteInto($this->_getTablePrimary().' = ?', $primary);
			
			if(isset($source['table']['deletedField']) && $this->_isTableField($source['table']['deletedField'])) {
				$data = array();
				$data[$source['table']['deletedField']] = 1;
				$db->update($this->_getTableName(),$data,$where);
			} else {
				$db->delete($this->_getTableName(),$where);
			}
		} else {
			throw App_Exception('Il s\'est produit une erreur',500);
		}
		
	}
	
	public function cancel() {
		
		$db = $this->getDatabase();
		
		$primary = $this->getPrimary();
		$source = self::getSource();
				
		if($primary && $this->isNew()) {
			
			$where = $db->quoteInto($this->_getTablePrimary().' = ?', $primary);
			
			$db->delete($this->_getTableName(),$where);
			
		}
		
	}
	
	
	/*
	 *
	 * Data method
	 *
	 */
	
	public function getData() {
		if(!$this->_data && $this->getPrimary()) {
			$this->_data = $this->fetch();
		}
		
		return $this->_data;
	}
	
	public function setData($data, $merge = true) {
		if(isset($this->_data) && is_array($this->_data) && $merge) {
			$this->_data = array_merge($this->_data,$data);
		} else $this->_data = $data;
		if(isset($data[$this->_getTablePrimary()])) {
			$primary = $data[$this->_getTablePrimary()];
			if($primary != $this->getPrimary()) $this->setPrimary($primary);
		}
	}
	
	public function toArray() {
		return $this->_data;
	}
	
	public function getPrimary() {
		return $this->_primary;
	}
	
	public function setPrimary($primary) {
		$this->_primary = $primary;
	}
	
	public function verifyPrimary($primary) {
		
		$db = $this->getDatabase();
		
		$select = $db->select()->from($this->_getTable(),array($this->_getTablePrimary()));
		
		if(method_exists($this,'_selectPrimary')) {
			$select = $this->_selectPrimary($select,$primary);
		} else {
			$select->where($this->_getTablePrimary(true).' = ?', $primary);
		}
		$data = $db->fetchRow($select);
		
		if(!$data) throw new App_Exception('Invalid item',500);
		
		return $data[$this->_getTablePrimary()];
	}
	
	public function isNew($value = null) {
		if($value === false) $this->_isNew = false;
		else if($value === true) $this->_isNew = true;	
		
		return $this->_isNew;
	}
	
	
	/*
	 *
	 * Database
	 *
	 */
	
	public function setDatabase(&$db) {
		$this->_db = $db;
	}
	
	public function getDatabase() {
		return isset($this->_db) ? $this->_db:self::getDefaultDatabase();
	}
	
	protected function _getTable() {
		$source = self::getSource();
		
		if(is_array($source['table']['name'])) return $source['table']['name'];
		else return array($source['table']['name']);
	}
	
	protected function _getTableName() {
		$source = self::getSource();
		
		if(is_array($source['table']['name'])) return $source['table']['name'][$this->_getTableShort()];
		else return $source['table']['name'];
	}
	
	protected function _getTableShort() {
		$source = self::getSource();
		
		if(is_array($source['table']['name'])) {
			$keys = array_keys($source['table']['name']);
			return isset($keys[0]) ? $keys[0]:null;
		} else return null;
	}
	
	protected function _getTablePrimary($fieldname = false) {
		$source = self::getSource();
		
		if(!$fieldname) return $source['table']['primary'];
		else return $this->_getTableFieldName($source['table']['primary']);
	}
	
	protected function _getTableFields() {
		$source = self::getSource();
		
		if(!is_array($source['table']['fields'])) return array($source['table']['fields']);
		else return $source['table']['fields'];
	}
	
	protected function _getTableFieldName($field) {
		$source = self::getSource();
		
		if(strpos($field,'.') !== false) return $field;
		
		if($this->_getTableShort() && $this->_isTableField($field)) return $this->_getTableShort().'.'.$field;
		else return $field;
	}
	
	protected function _getTableLeftJoins() {
		$source = self::getSource();
		
		if(isset($source['table']['leftJoins']) && is_array($source['table']['leftJoins'])) return $source['table']['leftJoins'];
		else return null;
	}
	
	protected function _isTableField($field) {
		$fields = $this->_getDescribeTable();
		return isset($fields[$field]);
	}
	
	protected function _filterTableFields($data) {
		
		$fields = $this->_getDescribeTable();
		
		$return = array();
		foreach($data as $key => $value) {
			if(isset($fields[$key])) $return[$key] = $value;
		}
		unset($data);
		
		return $return;
	}
	
	protected function _getDescribeTable() {
		
		$db = $this->getDatabase();
		
		$cache = $this->_getCache();
		$cacheKey = $this->_getCacheKey().'_describe';
		
		if(!isset($this->_describeTable) && (!$this->_hasCache() || !$this->_describeTable = $cache->load($cacheKey))) {
			$this->_describeTable = $db->describeTable($this->_getTableName());
			if($this->_hasCache()) $cache->save($this->_describeTable, $cacheKey);
		}
		
		return $this->_describeTable;
	}
	
	
	/*
	 *
	 * Cache
	 *
	 */
	
	public function _hasCache() {
		if(!isset($this->_cache) || !$this->_cache) return false;
		return true;
	}
	public function _getCache() {
		return $this->_cache;
	}
	public function _setCache($cache) {
		$this->_cache = $cache;
	}
	
	protected function _ensureCache() {
		if(isset($this->_cache)) return true;
		else {
			$this->_cache = App::get()->cache->getCache('database');
			return true;
		}
	}
	
	protected function _getCacheKey() {
		return 'kate_'.$this->_getTableShort().'_'.$this->_getTableName();
	}
	
	
	
	
	/*
	 *
	 * Static methods to set core data source
	 *
	 */
	
	public static function setDefaultDatabase(&$db) {
		self::$_defaultDb = $db;
	}
	
	public static function getDefaultDatabase() {
		if(!isset(self::$_defaultDb) && class_exists('Gregory') && isset(Gregory::get()->db)) return Gregory::get()->db;
		else return self::$_defaultDb;
	}
	
	protected static function getSource() {
		return self::$source;
	}
	public static function setSource($source) {
		self::$source = $source;
	}
	
	
	/*
	 *
	 * Get items
	 *
	 */
	
	public static function getItems($query = null, $data = null) {
		
		$db = self::getDefaultDatabase();
		$source = self::getSource();
		
		self::setCurrentQuery($query,$data);
		
		$select = $db->select()->from($source['table']['name'],$source['table']['fields']);
		if(is_string($query) && !empty($query)) {
			$query = self::getCurrentQuery();
			$select->where($query);
		} else if(is_array($query) && is_string($data)) {
			$select = self::_parseParam($select,$query);
			if(!empty($query)) $select->where($data);
		} else if(is_array($query)) {
			$select = self::_parseParam($select,$query);
		}
		
		self::setCurrentSelect($select);
		
		return $db->fetchAll($select);
		
	}
	
	public static function getItemsCount() {
		
		if(self::$_currentItemsCount == -1) {
			$db = self::getDefaultDatabase();
			
			$select = self::getCurrentSelect();
			
			$select->reset(Zend_Db_Select::COLUMNS)->reset(Zend_Db_Select::ORDER);
			$select->columns(array('kate_count'=>'COUNT(1)'));
			
			$result = $db->fetchRow($select);
			
			self::$_currentItemsCount = count($result) > 0 ? $result['kate_count'] : 0;
		}
		
		return self::$_currentItemsCount;
	}
	
	public static function getCurrentQuery() {
		
		return self::$_currentQuery;
		
	}
	
	public static function setCurrentQuery($query,$data = null) {
		
		self::$_currentQuery = self::_parseQuery($query,$data);
		
	}
	
	public static function getCurrentSelect() {
		
		return self::$_currentSelect;
		
	}
	
	public static function setCurrentSelect($select) {
		if($select != self::$_currentSelect) {
			self::$_currentItemsCount = -1;
			self::$_currentSelect = $select;
		}
		
	}
	
	protected static function _parseParam($select,$query) {
		
		$db = self::getDefaultDatabase();
		
		foreach($query as $field => $value) {
			if(is_array($value) && sizeof($value)) $select->where($field.' IN('.$db->quote($value).')');
			elseif(isset($value)) $select->where($field.' = ?',$value);
		}
		
		return $select;
		
	}
	
	protected static function _parseQuery($query,$data = null) {
		
		if(is_string($query) && isset($data)) {
			foreach($data as $key => $value) {
				$query = str_replace(':'.$key, $db->quoteInto('?',$value),$query);
			}
		}
		
		return $query;
		
	}
	
	
	/*
	 *
	 * Magic methods to access object data as property
	 *
	 */
	
	
	
	public function __get($name) {
		$data = $this->getData();
		if(isset($data[$name])) return $data[$name];
	}
	
	public function __set($name, $value) {
		$this->_data[$name] = $value;
	}
	
	
}