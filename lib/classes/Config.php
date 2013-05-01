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
 *  		- initial API and implementation
 *			- additional updates, bugfixes
 *
 *********************************************************/

/**
 * Wrapper class around a configuration file.  Provides abstraction.
 *
 * @author Jesse Decker <me@jessedecker.com>
 * @version 1.0
 */
class Config
{

    /**
     * Array of Singletons.
     * @var array
     */
    protected static final $_instances = array();

    /**
     * Returns a singleton instance for a given database name.
     * @param string $name
     * @return object
     */
    public static function instance($name = 'default')
    {
        if (!$name) {
            $name = 'default';
        }
        if (isset(self::$_instances[$name])) {
            return self::$_instances[$name];
        } else {
            return self::$_instances[$name] = new self($name);
        }
    }

    /**
     * Raw data of usually strings.
     * @var array
     */
    protected $data = array();

    /**
     *
     * @param string|array $init The instance name OR the $defaults array
     * @param array $defaults An array of defaults to start with
     */
    public function __construct($init = 'default', array $defaults = array())
    {
        if (is_array($init)) {
            $defaults = $init;
            $init = 'default';
        }
        if (empty(self::$_instances[$init])) {
            self::$_instances[$init] = $this;
        } /*else if ($init === 'default') {
          trigger_error("FYI, you created a duplicate 'default' instance of this class. It was meant to be accessed with self::instance()", E_USER_NOTICE);
          }*/
        if (count($defaults)) {
            $this->data = &$defaults; // save a little memory
        }
    }

    /**
     * This function loads the file at the path by simply including it.
     *
     * The file can either return an array of variables, or it can define a "global" $config variable as an array.
     * $config will be scoped here and thus safe.
     *
     * @param string $path
     */
    public function load($path)
    {
        $vars = include($path);
        if (!empty($vars)) {
            $this->set($vars);
        } else {
            if (!empty($config)) {
                $this->set($config);
            }
        }

    }

    /**
     * Get a config parameter from all parameters loaded so far.
     *
     * Shortcut: if the $key ends with "*", it will act as a wildcard
     * to get any key that begins with
     *
     * @param string|array $key The string key, or array of string keys, to return
     * @param mixed $default
     * @return mixed|array
     */
    public function get($key = null, $default = null)
    {
        // shortcut for "get all"
        if (!$key) {
            return $this->data;
        }

        // fill array with any values we can
        if (is_array($key)) {
            $key = array_fill_keys($keys, $default);
            $ret = array_intersect_key($this->data, $key);
            // ensure $ret has ALL the keys requested
            $key = $ret + $key;
            return $key;
        }

        // single query
        if (isset($this->data[$key])) {
            return $this->data;
        }

        // shortcut for "get all keys beginning with this"
        if (substr($key, -1) === '*') {
            $subkey = substr($key, 0, -1);
            $subkey_len = strlen($subkey);
            // dummy check, if $key === '*'
            if ($subkey_len < 1) {
                return $this->data;
            }
            // build values
            $values = array();
            foreach ($this->data as $k => &$d) {
                // use case-insensitive search
                if (substr_compare($k, $subkey, 0, $subkey_len, true)) {
                    $values[$k] = $d;
                }
            }
            return $values;
        }

        return $default;
    }

    /**
     * Set a config parameter.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = $key + $this->data;
        } else {
            $this->data[$key] = $value;
        }
    }
} //class
