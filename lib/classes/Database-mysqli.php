<?php
/**********************************************************
 * Copyright (c) 2013 Jesse Decker.
 *
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *     Jesse Decker <me@jessedecker.com>
 *			- initial API and implementation
 *			- additional updates, bugfixes
 *
 *********************************************************/

/**
 * Custom class to manage database connections.
 *
 * @author Jesse Decker, me@jessedecker.com
 * @version 0.5 - Jan1,2013
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
    public static function instance($name = 'default')
    {
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
     *
     * @var array
     */
    protected $config = array();

    protected $connected = false;
    protected $num_queries = 0;
    protected $num_errors = 0;
    protected $last_query = null;
    protected $handle = null;

    // caches queries until multi_query_commit() is run.
    private $_multi_queries = null;

    /**
     * Constructor.
     *
     * @param string $name Stores this instance for use with <code>instance()</code>, OR can pass an array with config data and <code>$init</code> will equal "default".
     * @param array $config Array of configuration parameters. See <code>$this->config</code> for documentation.
     * @return instance
     */
    public function __construct($init = 'default', array $config = array())
    {
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
        // 		if (!empty($config['close_on_shutdown'])) {
        // 			register_shutdown_function(array($this,'disconnect'));
        // 		}
    }

    /**
     * Closes the connection.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Modify and/or retrieves the configuration.
     * @param $vars Array or object of variables to merge into $config
     * @return The current $config array after modifications, if any
     */
    public function config($vars = array())
    {
        if (empty($vars)) {
            return $this->config;
        }
        if (is_object($vars)) {
            $vars = (array) $vars;
        }
        return $this->config = array_merge($this->config, $vars);
    }

    /**
     * Opens a connection using the current configuration variables, if a connection does not already exist.
     *
     * Normally, explicitly calling this is not required -- it is automatically called by most query functions that require an active connection.
     *
     * @throws DatabaseException if there was a problem connecting
     * @return boolean connection success
     */
    public function connect()
    {
        if ($this->connected) {
            return;
        }

        extract(array_merge(array('database' => null, 'port' => null, 'socket' => null, 'flags' => null), $this->config));
        $this->handle = mysqli_init();

        if (defined('MYSQLI_OPT_INT_AND_FLOAT_NATIVE')) {
            $this->handle->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        }

        $this->connected = @$this->handle->real_connect($server, $username, $password, $database, $port, $socket, $flags);

        if (!$this->connected) {
            $this->num_errors++;
            throw new DatabaseException('Connection error: ' . $this->handle->error, $this->handle->errno);
        }

        return $this->connected;
    }

    /**
     * Explicitly disconnect and close the database connection if one exists.
     */
    public function disconnect()
    {
        if ($this->handle) {
            @mysqli_close($this->handle);
            $this->handle = null;
            $this->connected = false;
            $this->_multi_queries = null;
        }
    }

    /**
     * Selects a database.
     *
     * @param string $name
     * @return boolean Success
     */
    public function select_db($name)
    {
        if (!$this->connected) {
            $this->connect();
        }
        $res = mysqli_select_db($this->handle, $name);
        if ($res) {
            $this->config['database'] = $name;
        }
        return $res;
    }

    /**
     * Inserts data into a table.
     *
     * @param string $table Name of the database table
     * @param array $values Can be an array of arrays, or a single array, of key-indexed values.
     * @param string $extra Additional SQL to tack to the end. Mosty used for "ON DUPLICATE KEY UPDATE..."
     * @throws DatabaseException if $table is empty or the query fails
     * @return NULL|boolean|int NULL if there is a problem with generating the query, false on query failure, or mysqli::$affected_rows on success
     */
    public function insert($table, array $values, $extra = '')
    {
        if (!$this->connected) {
            $this->connect();
        }

        if (!$table || !strlen($table)) {
            $this->num_errors++;
            throw new DatabaseException('Must specify a table');
        }

        if (empty($values)) {
            return null;
        }

        // check if key-array
        if (empty($values[0]) || !is_array($values[0])) {
            $cols = array_keys($values);
            $values = array($values);
        } else {
            $cols = array();
            foreach ($values as $v) {
                $cols[] = array_keys((array) $v);
            }
            $cols = array_unique(call_user_func_array('array_merge', $cols));
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
                        $cur_vals[] = "'" . $this->handle->real_escape_string($v[$c]) . "'";
                    }
                } else {
                    $cur_vals[] = 'NULL';
                }
            }

            $vals[] = '(' . implode(',', $cur_vals) . ')';
        }

        $sql .= implode(',', $vals) . ' ' . $extra;

        if (!$this->query($sql)) {
            return false;
        }

        return $this->handle->affected_rows;
    }

    /**
     * Update data in a table.
     *
     * @param string $table Name of the database table
     * @param array $values A single array of key-indexed values.
     * @param string $where SQL to select the data to change. Should NOT start with the text "WHERE ".
     * @throws DatabaseException if any parameter is empty, or the query fails
     * @return NULL|boolean|int NULL if there is a problem with generating the query, false on query failure, or mysqli::$affected_rows on success
     */
    public function update($table, array $values, $where)
    {
        if (!$this->connected) {
            $this->connect();
        }

        if (empty($values)) {
            $this->num_errors++;
            throw new DatabaseException('$table and $values cannot be empty');
        }
        if (!$table || !strlen($table)) {
            $this->num_errors++;
            throw new DatabaseException('Must specify a table');
        }
        if (empty($where)) {
            $this->num_errors++;
            throw new DatabaseException('Must specify a where clause');
        }

        $sql = "UPDATE `$table` SET ";

        $vals = array();
        foreach ($values as $k => $v) {
            $cur_val = "`$k`=";
            if (is_int($v) || is_float($v)) {
                $cur_val .= "{$v}";
            } elseif (is_bool($v)) {
                $cur_val .= $v ? 'TRUE' : 'FALSE';
            } else {//if (is_string($v[$c])) {
                $cur_val .= "'" . $this->handle->real_escape_string($v) . "'";
            }

            $vals[] = $cur_val;
        }

        $sql .= implode(',', $vals) . ' WHERE ' . $where;

        if (!$this->query($sql)) {
            return false;
        }

        return $this->handle->affected_rows;
    }

    /**
     * Fetches the results of the query as an associated array.
     * @param string $sql The query
     * @return array Results
     */
    public function &fetch_assoc($sql)
    {
        $this->query($sql);
        $result = $this->handle->store_result();
        $values = array();
        while ($vals = $result->fetch_assoc()) {
            $values[] = $vals;
        }
        $result->free();
        return $values;
    }

    /**
     * Makes sure the server is still connected. Sets the state to FALSE and returns FALSE if the connection was lost.
     * Remember, <code>$this->connect()</code> is called by most SQL functions, so it might not need to be explictly called in the event of a disconnect.
     * @return boolean Is the server still responding
     */
    public function ping()
    {
        $res = @$this->handle->ping();
        if (!$res) {
            $this->connected = false;
        }
        return !!$res;
    }

    /**
     * Runs a query against a database. Will automatically open a connection if there is none.
     * In the event of a multi-query, this will simply queue up the SQL. You must call multi_query_commit() to send.
     * @param string $sql The query
     * @throws DatabaseException On any database error
     * @return boolean Query success
     */
    public function query($sql)
    {
        if ($this->_multi_queries !== null) {
            $this->_multi_queries[] = $sql;
            return true;
        }

        if (!$this->connected) {
            $this->connect();
        }

        $this->num_queries++;
        $this->last_query = &$sql;
        $res = mysqli_real_query($this->handle, $sql);

        if (!$res) {
            $errno = mysqli_errno($this->handle);
            if ($errno === 2006) {
                $this->connected = false;
            }
            $this->num_errors++;
            throw new DatabaseException('Query error (' . $sql . ' ):   ' . mysqli_error($this->handle), $errno);
        }

        return $res;
    }

    /**
     * Generates a Prepared Statement from the SQL provided.
     * @param string $sql The query
     * @return mysqli::stmt The prepared statement
     */
    public function prepare($sql)
    {
        $res = mysqli_prepare($this->handle, $sql);
        return $res;
    }

    /**
     * Begins a multi-query session. Any subsequent calls to any <code>Database</code> functions will be cached.
     * Call <code>$this->multi_query_commit()</code> when ready to send all SQL to the server.
     */
    public function multi_query_start()
    {
        $this->_multi_queries = array();
    }

    /**
     * Sends all cached queries to the server as one string.
     * The cached queries will be implode()'d with a semi-colon -- DO NOT include a semi-colon in any provided queries.
     * @throws DatabaseException If any of the queries had a problem.
     * @return NULL nothing
     */
    public function multi_query_commit()
    {
        if (!count($this->_multi_queries)) {
            return null;
        }

        $sql = implode("; \n\n", $this->_multi_queries);
        // stats
        $this->num_queries += count($this->_multi_queries);
        $this->last_query = &$sql;
        // reset
        $this->_multi_queries = null;

        return multi_query($sql);
    }

    /**
     * Perform all SQL.
     *
     * @param string $sql
     * @throws DatabaseException
     */
    public function multi_query($sql)
    {
        // connect if need be
        if (!$this->connected) {
            $this->connect();
        }

        // DO IT
        $first_result = mysqli_multi_query($this->handle, $sql);

        do {
            $res = mysqli_use_result($this->handle);
            $error = ($res === false && mysqli_errno($this->handle) !== 0);

            if ($res instanceof mysqli_result) {
                @mysqli_free_result($res);
            }
            if ($error) {
                $this->num_error++;
                throw new DatabaseException('Query error (' . $sql . ' ):   ' . mysqli_error($this->handle), mysqli_errno($this->handle));
            }
        } while (mysqli_next_result($this->handle));

        return $first_result;
    }

    public function affected_rows()
    {
        if (!$this->connected) {
            return 0;
        }
        return mysqli_affected_rows($this->handle);
    }

    public function info()
    {
        if (!$this->connected) {
            return '';
        }
        return $this->handle->info;
    }

    public function insert_id()
    {
        if (!$this->connected) {
            return null;
        } else {
            return $this->handle->insert_id;
        }
    }

    public function &fetch_one_cell($sql)
    {
        if (stripos($sql, 'LIMIT ') === FALSE) {
            $sql .= ' LIMIT 0,1';
        }

        $this->query($sql);
        $result = $this->handle->store_result();

        if ($result instanceof mysqli_result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_row();
                $cell = array_shift($row); // return reference
                $result->free();
                return $cell;
            } else {
                $result->free();
            }
        }
        return null;
    }

    public function &fetch_one($sql)
    {
        if (stripos($sql, 'LIMIT ') === FALSE) {
            $sql .= ' LIMIT 0,1';
        }

        $this->query($sql);
        $result = $this->handle->store_result();

        if ($result instanceof mysqli_result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc(); // return reference
                $result->free();
                return $row;
            } else {
                $result->free();
            }
        }
        return null;
    }

    /**
     * Escapes the provided text according to the current database connection's settings for inclusion in a query string.
     * @param string $text
     * @return the escaped string
     */
    public function escape($text)
    {
        if (!$this->connected) {
            $this->connect();
        }
        return mysqli_real_escape_string($this->handle, $text);
    }

    public function __toString()
    {
        return sprintf('%s( queries:%d, errors:%d, last_query:"%s" )', __CLASS__, $this->num_queries, $this->num_errors, substr($this->last_query, 0, 30));
    }

    public function each($sql, $callable, $chunksize = 100, $max = 0, $startat = 0)
    {
        $sql .= ' LIMIT ';
        $cur = $startat;

        // chunk never bigger than max
        if ($max > 0 && $chunksize > $max) {
            $chunksize = $max;
        }

        // loop until we break
        while (true) {
            if ($max > 0) {
                if ($cur >= ($max + $startat)) { //relative to starting point
                    return $cur; // done
                } elseif (($cur + $chunksize) > ($max + $startat)) { // relative to starting point
                    $chunksize = $max + $startat - $cur;
                }
            }

            // run query
            $res = $this->query($sql . " " . $cur . "," . ($cur + $chunksize));

            // error, return progress
            if (!$res) {
                throw new DatabaseException('Each error (' . $sql . ' ):   ' . $this->handle->error, $this->handle->errno);
            }

            // buffer results
            $res = $this->handle->store_result();

            // error, return progress
            if (!$res) {
                throw new DatabaseException('Each error (' . $sql . ' ):   ' . $this->handle->error, $this->handle->errno);
            }

            // main loop
            while ($d = mysqli_fetch_assoc($res)) {
                if (is_array($callable)) {
                    call_user_func($callable, $d);
                } else {
                    $callable($d);
                }
            }

            $num_rows = $res->num_rows;

            // free the resource
            mysqli_free_result($res);

            if ($num_rows == $chunksize) {
                $cur += $chunksize;
            } else {
                // return that last instance
                return null;//$cur += $res->num_rows;
            }
        }//while
    }

}//class

/**
 * Custom subclass to throw from Database.
 * @author jdecker
 */
class DatabaseException extends Exception
{
    // no explicit constructors, so it inherits from parent
}

if (!class_exists('DB', false)) {

    /**
     * This is strictly a convenience class that forwards to the default <code>Database</code> instance.
     * @author jdecker
     */
    class DB
    {

        public static function q($sql)
        {
            return Database::instance()->fetch_assoc($sql);
        }

        public static function query($sql)
        {
            return Database::instance()->query($sql);
        }

        public static function insert($table, array $values, $extra = '')
        {
            return Database::instance()->insert($table, $values, $extra);
        }

        public static function update($table, array $values, $where)
        {
            return Database::instance()->update($table, $values, $where);
        }

        public static function &fetch_assoc($sql)
        {
            return Database::instance()->fetch_assoc($sql);
        }

        public static function &fetch_one($sql)
        {
            return Database::instance()->fetch_one($sql);
        }

        public static function &fetch_one_cell($sql)
        {
            return Database::instance()->fetch_one_cell($sql);
        }

        public static function escape($text)
        {
            return Database::instance()->escape($text);
        }

    }//class
}
