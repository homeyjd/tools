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
 *    		- initial API and implementation
 *			- additional updates, bugfixes
 *
 *********************************************************/

/**
 * Wrapper class around a configuration array.
 *
 * This class is pretty simple but it provides an important abstraction layer
 * and a very convenient <code>proxy($prefix)</code> method for namespacing
 * configurations within a larger scope.
 *
 * This class has one interesting difference between a standard array wrapper:
 * all array keys are flattened to a single array with keys store using dot-notation.
 * This means multi-dimensional arrays will be flattened to "key1.key2" format.
 * This reduces hash lookups and simplifies data handling, making proxies possible.
 *
 * @author Jesse Decker <me@jessedecker.com>
 * @version 1.0
 */
class Config
{

    ////  Static scope  /////////////////////////////////////////

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
    public static function instance($name = 'global')
    {
        if (!$name) {
            $name = 'global';
        }
        if (isset(self::$_instances[$name])) {
            return self::$_instances[$name];
        } else {
            return self::$_instances[$name] = new self($name);
        }
    }

    /**
     * Takes a multi-dimensional array of values and transforms it into a flat array.
     *
     * Keys in the flat array are generated by concatenating the $connector string between the
     * keys of the levels fo the array. So <code>array('one'=>array('two'=>true))</code> becomes
     * <code>array('one.two'=>true)</code>.
     *
     * The process of flattening can reduce multiple hash tree lookup times, simplifies array organization,
     * and helps simplify the process of storing configuration entries.
     *
     * @param array $values Array of values to flatten
     * @param string $connector The string to place between the levels of arrays. Dot-notation is recommended, but technically can also be an empty string.
     * @param number $maxLevel Ability to specify the depth to flatten the array
     * @return array Will always return an array
     */
    public static function flatten(array $values, $connector = '.', $maxLevel = 10)
    {
        if (!$values) {
            return array();
        }

        $merged = array();

        // recursive lambda
        $lambda = function($prefix, &$value, $level = 0) use (&$connector, &$merged, &$maxLevel, &$lambda) {
            $prefix = $prefix . $connector;
            foreach ($value as $key => &$v) {
                if (is_array($v) && $level < $maxLevel) {
                    $lambda($prefix . $key, $v, $level+1);
                } else {
                    $merged[$prefix . $key] = $v;
                }
            }
        };

        foreach ($values as $key => &$v) {
            if (is_array($v)) {
                // invoke
                $lambda($key, $v);
            } else {
                $merged[$key] = $v;
            }
        }

        return $merged;
    }

    /**
     * Reverses the process of <code>flatten()</code>.
     * @param array $values
     * @param string $connector
     */
    public static function expand(array $values, $connector = '.')
    {
        if (!$values) {
            return array();
        }

        $merged = array();

        foreach ($values as $key => &$v) {
            // start out normal
            $cur =& $merged;
            $subkey = $key;
            // keep building sub-arrays
            while (0 < ($pos = strpos($key, $connector))) {
                // don't reassign if the connector is at the END of the string
                if ($pos === (strlen($key) - 1)) {
                    break;
                }
                // re-assign based on position of connector
                $subkey = substr($key, 0, $pos);
                $key = substr($key, $pos+1);
                if (array_key_exists($subkey, $cur)) {
                    if (is_array($cur[$subkey])) {
                        $cur =& $cur[$subkey];
                    } else {
                        $cur[$subkey] = array($cur[$subkey]);
                    }
                } else {
                    $cur[$subkey] = array();
                    $cur =& $cur[$subkey];
                }
            }
            // finally, assign
            $cur[$key] = $v;
        }

        return $merged;
    }


    ////  Instance scope  ///////////////////////////////////////

    /**
     * Raw data of usually strings.
     * @var array
     */
    protected $data = array();

    /**
     * Constructor.
     *
     * Accepts 1 or 2 parameters. If the first parameter is a string, it will be the
     * stored name of this global instance.  The next (or first) parameter will be used
     * as an array of initial values.
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
        if (!isset(self::$_instances[$init])) {
            self::$_instances[$init] = $this;
        } /*else if ($init === 'default') {
          trigger_error("FYI, you created a duplicate 'default' instance of this class. It was meant to be accessed with self::instance()", E_USER_NOTICE);
          }*/
        if (count($defaults)) {
            $this->data = $defaults;
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
     * to get any key that begins with the provided prefix. For example,
     * <code>$cfg->get('*')</code> will get all data whereas
     * <code>$cfg->get('database.*')</code> will get all keys prefixed by
     * <code>'database.'</code>.
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
            return $this->data[$key];
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
                // use case-sensitive search for performance
                if (substr_compare($k, $subkey, 0, $subkey_len, false) === 0) {
                    // deal with instance where matched key is shorter
                    $newSubkey = substr($k, $subkey_len);
                    if ($newSubkey) {
                        $values[$newSubkey] = $d;
                    } else {
                        $values = $values + $d;
                    }
                }
            }
            return $values;
        }

        return $default;
    }

    /**
     * Set a value at the specified key.
     *
     * You can specify your own key structure, but dot-notation is recommended.
     * This handles multi-level configuration options with ease instead of dealing
     * with multi-dimensional arrays.
     *
     * @param string|array $key String key or array of values
     * @param mixed $value
     * @param boolean $flatten Whether to run through Config::flatten() before saving
     */
    public function set($key, $value = null, $flatten = true)
    {
        if (is_array($key)) {
            $newData = $key;
        } else {
            $newData = array( $key => $value );
        }

        if ($flatten) {
            $newData = self::flatten($newData);
        }

        $this->data = $newData + $this->data;
    }


    /**
     * Generates a convenience function for namespacing options within a larger scope.
     *
     * Pass in a prefix you want to use and store the returned callback as a static member
     * or local variable. Any calls to that callback will automatically prefix any calls
     * to that callback.
     *
     * The callback is a single anonymous function that can be used to get or set parameters.
     *
     * Setup:
     * <code>
     * $mycfg = $cfg->proxy('MyClass.');
     * </code>
     *
     * Get:
     * <code>
     * $MyClassLength = $mycfg('length'); // queries $cfg for MyClass.length
     * </code>
     *
     * Set:
     * <code>
     * $mycfg(array('length' => 100)); // sets $cfg->set('MyClass.length', 100);
     * </code>
     *
     * @param string $prefix The prefix to prepend all calls the returned function
     * @return string
     */
    public function proxy($prefix = '')
    {
        // Maintain pointers to this object so not GC'd
        $self = $this;

        return function ($keys, $default = null) use ($self, $prefix)
        {
            if (is_string($keys)) {
                return $self->get($prefix . $keys, $default);
            }
            if (is_array($keys)) {
                // insert prefix in new array
                $values = array();
                foreach ($keys as $key => $value) {
                    $values[$prefix . $key] = $value;
                }
                $self->set($values);
            }

            return $default;
        };
    }
} //class
