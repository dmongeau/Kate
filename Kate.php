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
	public $source = array(
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
	
	protected static $_models = array();
	
	protected static $_defaultDb;
	protected $_currentQuery;
	protected $_currentSelect;
	protected $_currentItemsCount;
	
	
	public $db;
	protected static $_cache;
	
	protected $_primary;
	protected $_id;
	protected $_data = array();
	protected $_dataSet = array();
	protected $_describeTable;
	
	public $_isNew = true;
	public $_fetched = false;
	
	public function __construct($primary = null) {
		
		if(isset($primary)) {
			$primary = $this->verifyPrimary($primary);
			$this->setPrimary($primary);
			$this->isNew(false);
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
		
		if(!$data) throw new Exception('Il s\'est produit une erreur',404);
		
		$item = array();
		foreach($data as $key => $value) {
			if(method_exists($this,'_get'.$key)) $item = $this->{'_get'.$key}($item,$value,$data);
			elseif(!isset($item[$key])) $item[$key] = $value;
		}
		
		//$this->setData($item,false);
		$this->setData($data,false,true);
		$this->_fetched = true;
		
		return $this->getData();
		
	}
	
	public function save() {
		
		$db = $this->getDatabase();
		
		$primary = $this->getPrimary();
		$source = $this->getSource();
		//$inputs = $this->getData();
		$inputs = $this->_dataSet;
			
		$data = array();	
		foreach($inputs as $key => $value) {
			if(method_exists($this,'_put'.$key)) $data = $this->{'_put'.$key}($data,$value,$inputs);
			elseif(!isset($data[$key])) $data[$key] = $value;
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
				
			} else {
				$where = $db->quoteInto($this->_getTablePrimary().' = ?', $primary);
				$db->update($this->_getTableName(),$data,$where);
			}
			$this->_dataSet = array();
		}
		
	}
	
	public function delete() {
		
		$db = $this->getDatabase();
		
		$primary = $this->getPrimary();
		$source = $this->getSource();
		
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
			throw Exception('Il s\'est produit une erreur',500);
		}
		
	}
	
	public function cancel() {
		
		$db = $this->getDatabase();
		
		$primary = $this->getPrimary();
		$source = $this->getSource();
		
		if($this->isCancelable()) {
			
			$where = $db->quoteInto($this->_getTablePrimary().' = ?', $primary);
			
			$db->delete($this->_getTableName(),$where);
			
			$this->setData(array());
			$this->setPrimary(null);
			
			return true;
			
		}
		
		return false;
		
	}
	
	public function isCancelable() {
		
		$primary = $this->getPrimary();
		
		return $primary && $this->isNew() ? true:false;
		
	}
	
	/*
	 *
	 *
	 * Related items
	 *
	 *
	 */
	public function getRelatedItems($table) {
	
		$db = $this->getDatabase();
		
		$select = $db->select()->from(array($table),array('*'));
		
		$select->where($this->_getTablePrimary().' = ?',$this->getPrimary());
		
		$items = $db->fetchAll($select);
		
		return $items;
	}
	
	public function updateRelatedItems($items,$table,$primary) {
	
		$db = $this->getDatabase();
		
		$primaryField = $this->_getTablePrimary();
		$primaryValue = $this->getPrimary();
		
		$ids = array();
		foreach($items as $item) {
			$item[$primaryField] = $primaryValue;
			if(isset($item[$primary]) && (int)$item[$primary] > 0) {
				$db->update($table,$item,$db->quoteInto($primary.' = ?',$item[$primary]));
				$ids[] = $item[$primary];
			} else {
				$item['dateadded'] = date('Y-m-d H:i:s');
				$db->insert($table,$item);
				$ids[] = $db->lastInsertId();
			}
		}
		
		if(sizeof($ids)) $db->delete($table,$primary.' NOT IN('.implode(',',$ids).') AND '.$primaryField.' = '.$primaryValue);
		else $db->delete($table,$db->quoteInto($primaryField.' = ?',$primaryValue));
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
	
	/*public function setData($data, $merge = true) {
		if(isset($this->_data) && is_array($this->_data) && $merge) {
			$this->_data = array_merge($this->_data,$data);
		} else $this->_data = $data;
		if(isset($data[$this->_getTablePrimary()])) {
			$primary = $data[$this->_getTablePrimary()];
			if($primary != $this->getPrimary()) $this->setPrimary($primary);
			$this->isNew(false);
		}
	}*/
	public function setData($data, $merge = true, $fetched = false) {
		if(isset($this->_data) && is_array($this->_data) && $merge) {
			$this->_data = array_merge($this->_data,$data);
		} else {
			$this->_data = $data;
		}
		if(!$fetched) $this->_dataSet = array_merge($this->_dataSet,$data);
		if(isset($data[$this->_getTablePrimary()])) {
			$primary = $data[$this->_getTablePrimary()];
			if($primary != $this->getPrimary()) $this->setPrimary($primary);
			$this->isNew(false);
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
		
		if(is_array($primary)) {
			$select = $this->_parseQuery($select,$primary);
		} else if(method_exists($this,'_selectPrimary')) {
			$select = $this->_selectPrimary($select,$primary);
		} else {
			$select->where($this->_getTablePrimary(true).' = ?', $primary);
		}
		$data = $db->fetchRow($select);
		
		if(!$data) throw new Exception('Il s\'est produit une erreur',500);
		
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
	
	protected function getSource() {
		return $this->source;
	}
	public function setSource($source) {
		$this->source = $source;
	}
	
	public function setDatabase(&$db) {
		$this->db = $db;
	}
	
	public function getDatabase() {
		if(!isset($this->db)) $this->db = self::getDefaultDatabase();
		return $this->db;
	}
	
	protected function _getTable() {
		$source = $this->getSource();
		
		if(is_array($source['table']['name'])) return $source['table']['name'];
		else return array($source['table']['name']);
	}
	
	protected function _getTableName() {
		$source = $this->getSource();
		
		if(is_array($source['table']['name'])) return $source['table']['name'][$this->_getTableShort()];
		else return $source['table']['name'];
	}
	
	protected function _getTableShort() {
		$source = $this->getSource();
		
		if(is_array($source['table']['name'])) {
			$keys = array_keys($source['table']['name']);
			return isset($keys[0]) ? $keys[0]:null;
		} else return null;
	}
	
	protected function _getTablePrimary($fieldname = false) {
		$source = $this->getSource();
		
		if(!$fieldname) return $source['table']['primary'];
		else return $this->_getTableFieldName($source['table']['primary']);
	}
	
	protected function _getTableFields() {
		$source = $this->getSource();
		
		if(!is_array($source['table']['fields'])) return array($source['table']['fields']);
		else return $source['table']['fields'];
	}
	
	protected function _getTableFieldName($field) {
		$source = $this->getSource();
		
		if(strpos($field,'.') !== false) return $field;
		
		if($this->_getTableShort() && $this->_isTableField($field)) return $this->_getTableShort().'.'.$field;
		else return $field;
	}
	
	protected function _getTableLeftJoins() {
		$source = $this->getSource();
		
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
		
		if(isset($this->_describeTable)) return $this->_describeTable;
		
		$cache = self::getCache();
		$cacheKey = $this->_getCacheKey().'_describe';
		
		if((!self::hasCache() || !$this->_describeTable = $cache->load($cacheKey))) {
			$db = $this->getDatabase();
			$this->_describeTable = $db->describeTable($this->_getTableName());
			if(self::hasCache()) $cache->save($this->_describeTable, $cacheKey);
		}
		
		return $this->_describeTable;
	}
	
	
	/*
	 *
	 * Cache
	 *
	 */
	
	protected function _getCacheKey() {
		return 'kate_'.$this->_getTableShort().'_'.$this->_getTableName();
	}
	
	public static function hasCache() {
		if(!isset(self::$_cache) || !self::$_cache) return false;
		return true;
	}
	public static function getCache() {
		return self::$_cache;
	}
	public static function setCache($cache) {
		self::$_cache = $cache;
	}
	
	protected static function _ensureCache() {
		if(isset(self::$_cache)) return true;
		else {
			self::$_cache = Gregory::get()->cache->getCache('kate');
			return true;
		}
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
		if(!isset(self::$_defaultDb) && class_exists('Gregory') && Gregory::get()->db != null) {
			self::$_defaultDb = Gregory::get()->db;
		}
		return self::$_defaultDb;
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
	
	
	/*
	 *
	 * Get items
	 *
	 */
	
	public function getItems($query = null, $opts = array()) {
		
		$opts = array_merge(array(
			'page' => -1,
			'rpp'=> 50,
		),$opts);
		
		$db = self::getDefaultDatabase();
		
		$select = $this->buildItemsSelect($query,$opts);
		
		$this->setCurrentItemsSelect($select);
		
		if(isset($opts['page']) && isset($opts['rpp']) && (int)$opts['page'] > 0 && (int)$opts['rpp'] > 0) {
			$total = isset($opts['total']) ? (int)$opts['total']:$this->getItemsCount();
			$totalPages = ceil($total/$opts['rpp']);
			$page = $opts['page'] > $totalPages ? 1:(int)$opts['page'];
			$select->limitPage($page,$opts['rpp']);
		}
		
		return $db->fetchAll($select);
		
	}
	
	public function getItemsAsObjects($query = null, $opts = array()) {
		
		$items = $this->getItems($query,$opts);
		
		$className = get_class($this);
		
		$objects = array();
		foreach($items as $item) {
			eval('$Item = new '.$className.'();');
			$Item->setData($item);
			$objects[] = $Item;
		}
		
		return $objects;
		
	}
	
	public function getItemsCount() {
		
		if($this->_currentItemsCount == -1) {
			$db = self::getDefaultDatabase();
			
			$select = clone $this->getCurrentItemsSelect();
			
			$select->reset(Zend_Db_Select::COLUMNS)->reset(Zend_Db_Select::ORDER);
			$select->columns(array('kate_count'=>'COUNT(1)'));
			
			$result = $db->fetchRow($select);
			
			$this->_currentItemsCount = count($result) > 0 ? $result['kate_count'] : 0;
		}
		
		return $this->_currentItemsCount;
	}
	
	public function buildItemsSelect($query = null, $opts = null) {
		
		$db = self::getDefaultDatabase();
		
		$source = $this->getSource();
		if(is_a($query,'Zend_Db_Select')) {
			$select->from($source['table']['name'],$source['table']['fields']);
			if(isset($opts['query'])) $query = $opts['query'];
		} else {
			$select = $db->select()->from($source['table']['name'],$source['table']['fields']);
		}
		
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
			
		if(is_string($query) && !empty($query)) {
			$select->where($query);
		} else if(is_array($query) && sizeof($query)) {
			$select = $this->_parseQuery($select,$query);
		}
		
		return $select;
	}
	
	public function getCurrentItemsSelect() {
		
		return $this->_currentSelect;
		
	}
	
	public function setCurrentItemsSelect($select) {
		if($select != $this->_currentSelect) {
			$this->_currentItemsCount = -1;
			$this->_currentSelect = $select;
		}
		
	}
	
	protected function _parseQuery($select,$query) {
		
		$db = self::getDefaultDatabase();
		
		$methods = get_class_methods($this);
		
		foreach($query as $field => $value) {
			
			
			$fieldName = strpos($field,'.') !== false ? substr($field,strpos($field,'.')+1):$field;
			$field = $this->_getTableFieldName($field);
			$methodName = '_query'.strtoupper(substr($fieldName,0,1)).strtolower(substr($fieldName,1));
			
			if(in_array($methodName,$methods)) $select = $this->{$methodName}($select,$value);
			elseif($field == 'order by') {
				$value = !is_array($value) ? array($value):$value;
				foreach($value as $order) {
					$asc = strtolower(substr($order,-4));
					$desc = strtolower(substr($order,-5));
					if($asc == '_asc' || $asc == ' asc') {
						$field = substr($order,0,strlen($order)-4);
						$orientation = 'asc';
					} elseif($desc == '_desc' || $desc == ' desc') {
						$field = substr($order,0,strlen($order)-5);
						$orientation = 'desc';
					} else {
						$field = $order;
						$orientation = 'asc';
					}
					
					if($field == 't.tid = m.tid1') {
						$select->order(new Zend_Db_Expr($field.' '.strtoupper($orientation)));	
					} else {
						$select->order($this->_getTableFieldName($field).' '.strtoupper($orientation));
					}
				}
			}
			else if(is_array($value) && sizeof($value)) $select->where($field.' IN('.$db->quote($value).')');
			elseif($field == 'group by') {
				$select->group($this->_getTableFieldName($value));
			}
			elseif(substr($field,0,4) == 'not ') {
				$field = $this->_getTableFieldName(substr($field,4));
				
				if(is_array($value) && sizeof($value)) $select->where($field.' NOT IN('.$db->quote($value).')');
				else if(!empty($value)) $select->where($field.' != ?',$value);
				
			}
			elseif(substr($field,0,6) == 'lower ') {
				$field = $this->_getTableFieldName(substr($field,6));
				
				if(is_array($value) && sizeof($value)) $select->where('LOWER('.$field.') IN('.$db->quote($value).')');
				else if(!empty($value)) $select->where('LOWER('.$field.') = ?',strtolower($value));
				
			}
			elseif(isset($value) && $this->_isTableField($fieldName)) $select->where($field.' = ?',$value);
			
		}
		
		return $select;
		
	}
	
	/*protected function _parseQuery($query,$data = null) {
		
		if(is_string($query) && isset($data) && is_array($data)) {
			foreach($data as $key => $value) {
				$query = str_replace(':'.$key, $db->quoteInto('?',$value),$query);
			}
		}
		
		return $query;
		
	}*/
	
	
	
	public static function requireModel($model) {
		
		$key = strtolower($model);
		
		if(!isset(self::$_models[$key])) {
			self::$_models[$key] = PATH_MODELS.'/'.strtoupper(substr($model,0,1)).strtolower(substr($model,1)).'.php';
			require self::$_models[$key];
		}	
		
	}
	
	
	public static function create($model,$primary = null) {
		
		$classname = strtoupper(substr($model,0,1)).strtolower(substr($model,1));
		
		self::requireModel($classname);
		
		eval('$Item = new '.$classname.'($primary);');
		return $Item;
		
	}
	
	public static function get($model,$primary) {
		
		return self::create($model,$primary);
		
	}
	
	public static function getAll($model,$query = array(),$opts = array()) {
		
		$Item = self::create($model);
		return $Item->getItems($query,$opts);
		
	}
	
	
}