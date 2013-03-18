<?php
/**
 * Custom class to manage database connections.
 * 
 * @author Jesse Decker, me@jessedecker.com
 * @version 0.2 - Mar1,2013
 */
class Database
{
	
	/**
	 * Array of Singletons.
	 * @var array
	 */
	protected static $_instances = array();
	
	/**
	 * Returns a singleton instance for a given database name.
	 * @param string $name
	 * @return object
	 */
	public static function instance ($name = 'default') {
		if (!empty(self::$_instances[$name])) {
			return self::$_instances[$name];
		} else {
			return self::$_instances[$name] = new self($name);
		}
	}
	
	/**
	 * Available config variables:
	 *		server: URL
	 *		username: for database
	 *		password: for database
	 *		database: default name
	 *		close_on_shutdown: register shutdown hook
	 *		
	 * @var array
	 */
	protected $config;
	protected $connected = false;
	protected $numQueries = 0;
	protected $handle;
	protected $queries = array();
	protected $cur_db;
	protected $numErrors = 0;
	
	/**
	 * Constructor
	 * @param string $name Named instance to store OR array with config data.
	 * @return instance The class instance associated
	 */
	public function __construct ($init = 'default', array $config = array()) {
		if (is_array($init)) {
			$config = $init;
			$init = 'default';
		}
		if (empty(self::$_instances[$init])) {
			self::$_instances[$init] = $this;
		}
		if (!empty($config)) {
			$this->config = $config;
		}
		if (!empty($config['close_on_shutdown'])) {
			register_shutdown_function(array($this,'disconnect'));
		}
		
	}
	
	/**
	 * Modify configuration.
	 * @param $vars Array or object of variables to merge into $config
	 * @return The current $config array
	 */
	public function config ($vars = array()) {
		if (empty($vars)) {
			return $this->config;
		}
		if (is_object($vars)) {
			$vars = (array) $vars;
		}
		return $this->config = array_merge($this->config, $vars);
	}
	
	/**
	 * Automatically called if not connected upon any transaction.
	 */
	public function connect () {
		if ($this->connected) {
			return;
		}
		
		extract($this->config);
		$this->handle = @mysql_connect($server, $username, $password);
		$this->connected = !!$this->handle;
		if (!$this->connected) {
			throw new Exception('Could not connect to database at '.$server);
		}
		if (!empty($database)) {
			return $this->select_db($database);
		}
		return $this->connected;
	}
	
	public function disconnect () {
		if ($this->handle) {
			@mysql_close( $this->handle );
			$this->handle = null;
			$this->connected = false;
		}
	}
	
	public function select_db ($name) {
		if (!$this->connected) {
			$this->connect();
		}
		$res = mysql_select_db($name, $this->handle);
		if ($res) {
			$this->config['database'] = $name;
		}
		return $res;
	}
	
	public function insert($table, array $values, $extra = '') {
		if (!$this->connected) {
			$this->connect();
		}
		
		if (empty($values) || !$table || !strlen($table)) {
			throw new Exception('Database::insert: params cannot be empty');
		}
		
		// check if key-array
		if (empty($valuse[0]) || !is_array($values[0])) {
			$cols = array_keys($values);
			$values = array($values);
		} else {
			$cols = array();
			foreach ($values as $v) {
				$cols[] = array_keys((array)$v);
			}
			$cols = array_unique( call_user_func_array('array_merge', $cols) );
		}
		
		
		$sql = "INSERT INTO `$table` ( `" . implode('`, `', $cols) . "` ) VALUES ";
		
		$vals = array();
		foreach ($values as $v) {
			$v = (array) $v;
			$cur_vals = array();
			
			foreach ($cols as $c) {
				if (isset($v[$c])) {
					if (is_int($v[$c]) || is_float($v[$c])) {
						$cur_vals[] = "{$v[$c]}";
					} elseif (is_bool($v[$c])) {
						$cur_vals[] = $v[$c] ? 'TRUE' : 'FALSE';
					} else {//if (is_string($v[$c])) {
						$cur_vals[] = "'".mysql_real_escape_string($v[$c], $this->handle)."'";
					}
				} else {
					$cur_vals[] = 'NULL';
				}
			}
			
			$vals[] = '('.implode(',',$cur_vals).')';
		}
		
		$sql .= implode(',',$vals) . ' ' . $extra;
		
		$this->query($sql);
		
		return mysql_affected_rows($this->handle);
	}
	
	
	public function update($table, array $values, $where) {
		if (!$this->connected) {
			$this->connect();
		}
		
		if (empty($values) || !$table || !strlen($table) || empty($where)) {
			throw new Exception('Database::update: params cannot be empty');
		}
		
		$sql = "UPDATE `$table` SET ";
		
		$vals = array();
		foreach ($values as $k=>$v) {
			$cur_val = "`$k`=";
			if (is_int($v) || is_float($v)) {
				$cur_val .= "{$v}";
			} elseif (is_bool($v)) {
				$cur_val .= $v ? 'TRUE' : 'FALSE';
			} else {//if (is_string($v[$c])) {
				$cur_val .= "'".mysql_real_escape_string($v, $this->handle)."'";
			}
			
			$vals[] = $cur_val;
		}
		
		$sql .= implode(',',$vals) . ' WHERE ' . $where;
		
		$this->query($sql);
		
		return mysql_affected_rows($this->handle);
	}
	
	public function & fetch_assoc ($sql) {
		$res = $this->query($sql);
		$values = array();
		while ($vals = mysql_fetch_assoc($res)) {
			$values[] = $vals;
		}
		@mysql_free_result($res);
		return $values;
	}
	
	public function ping () {
		$res = @mysql_ping($this->handle);
		if (!$res) {
			$this->connected = false;
		}
		return !!$res;
	}
	
	public function query ($sql) {
		if (!$this->connected) {
			$this->connect();
		}
		
		$res = mysql_query($sql, $this->handle);
		
		if (!$res) {
			$errno = mysql_errno($this->handle);
			if ($errno === 2006) {
				$this->connected = false;
			}
			throw new Exception('Query error ('.$sql.' ):   '.mysql_error($this->handle), $errno);
		}
		
		return $res;
	}
	

	public function multi_query_start () {
	}
	
	public function multi_query_commit () {
	}
	
	public function affected_rows () {
		if (!$this->connected) {
			return 0;
		}
		return mysql_affected_rows($this->handle);
	}
	
	public function info () {
		if (!$this->connected) {
			return 0;
		}
		return mysql_info($this->handle);
	}
	
	public function insert_id () {
		if (!$this->connected) {
			return null;
		} else {
			return mysql_insert_id($this->handle);
		}
	}
	
	public function & fetch_one_cell ($sql) {
		if (stripos($sql,'LIMIT ')===FALSE) {
			$sql .= ' LIMIT 0,1';
		}
		$result = $this->fetch_assoc($sql);
		if (!empty($result) && !empty($result[0])) {
			$result = array_shift($result[0]); // return reference
			return $result;
		} else {
			return null;
		}
	}
	
	public function & fetch_one ($sql) {
		if (stripos($sql,'LIMIT ')===FALSE) {
			$sql .= ' LIMIT 0,1';
		}
		$res = $this->fetch_assoc($sql);
		if (is_array($res) && count($res)) {
			$res = current($res); // return reference
			return $res;
		} else {
			return null;
		}
	}
	
	public function escape ($text) {
		if (!$this->connected) {
			$this->connect();
		}
		return mysql_real_escape_string($text);
	}
	
	public function each ($sql, $callable, $chunksize = 100, $max = 0) {
		$sql .= ' LIMIT ';
		$cur = 0;
		
		if ($max > 0 && $chunksize > $max) {
			$chunksize = $max;
		}
		
		while(true) {
			if ($max > 0) {
				if ($cur >= $max) {
					return; // done
				} elseif (($cur+$chunksize) > $max) {
					$chunksize = $max - $cur;
				}
			}
			
			$data = $this->fetch_assoc($sql . "$cur,$chunksize");
			
			if (count($data)) {
				foreach($data as $d) {
					$callable($d);
				}
			}
			
			if (count($data) == $chunksize) {
				$cur += $chunksize;
			} else {
				return;
			}
		}
	}
	
}//class

class DB {

	public static function q ($sql) {
		return Database::instance()->fetch_assoc($sql);
	}
	
	public static function query($sql) {
		return Database::instance()->query($sql);
	}
	
	public static function insert ($table, $values, $extra='') {
		return Database::instance()->insert($table, $values, $extra);
	}
	
	public static function insert_id () {
		return Database::instance()->insert_id();
	}
	
	public static function update($table, $values, $where) {
		return Database::instance()->update($table, $values, $where);
	}
	
	public static function & fetch_assoc ($sql) {
		return Database::instance()->fetch_assoc($sql);
	}
	
	public static function & fetch_one ($sql) {
		return Database::instance()->fetch_one($sql);
	}
	
	public static function & fetch_one_cell ($sql) {
		return Database::instance()->fetch_one_cell($sql);
	}
	
	public static function escape ($text) {
		return Database::instance()->escape($text);
	}
	
}
