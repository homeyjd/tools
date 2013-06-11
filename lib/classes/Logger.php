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

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

/**
 * Base methods for a handler that writes log entries.
 * @author Jesse Decker <jesse.decker@am.sony.com>
 * @date Jun 5, 2013
 * @version 0.1
 */
interface LogWriter
{
    /**
     * Filter messages below this level.
     * @param int $level
     */
    public function setLevel($level);
    /**
     * Push a message to the log.
     * @param int $level
     * @param string $message
     */
    public function logLine($level, $message);
}

/**
 * Base methods for a LogWriter.
 * @author Jesse Decker <jesse.decker@am.sony.com>
 * @date Jun 5, 2013
 * @version 0.1
 * @see LogWriter
 */
abstract class AbstractLogWriter implements LogWriter
{
    /**
     * Level for this instance
     * @var int
     */
    protected $level = Logger::DEBUG;

    /**
     * @param int $level
     * @see LogWriter::setLevel()
     */
    public function setLevel($level)
    {
        $this->level = intval($level);
    }
}

/**
 * A PSR-3 compatible logging class with handlers.
 *
 * Based on KLogger by Kenny Katzgrau <katzgrau@gmail.com>.
 *
 * This class prefers the verbiage of "level" over "severity" because integers
 * are easier to check for.  The PSR-3 spec chose to use strings for severity
 * levels against the wisdom of other system designers.  This class is PSR-3
 * compatible for use with strings, but all self-defined severities are integers
 * and string values are converted into relevant integers for handling.
 *
 * This class implements LogWriter, so you can add multiple instances of Logger
 * as handlers for each other... if you ever need to. Just ensure you don't get
 * recursive logging loops!
 *
 * Usage:
 * $log = new Logger(Logger::INFO);
 * $log->logInfo('Returned a million search results');
 * $log->log(Logger::CRIT, 'Oh dear.');
 * $log->logDebug('x = 5'); //Prints nothing due to current severity threshhold
 *
 * @author  Jesse Decker <me@jessedecker.com>
 * @since   May 2013 | Last update June 2013
 * @link    http://jessedecker.com
 * @version 0.1
 */
class Logger extends AbstractLogger implements LoggerInterface, LogWriter
{
    /**
     * Error severity, from low to high. From BSD syslog RFC, secion 4.1.1
     * @link http://www.faqs.org/rfcs/rfc3164.html
     * @var int
     */
    const EMERG = 0; // Emergency: system is unusable
    const ALERT = 1; // Alert: action must be taken immediately
    const CRIT = 2; // Critical: critical conditions
    const ERR = 3; // Error: error conditions
    const WARN = 4; // Warning: warning conditions
    const NOTICE = 5; // Notice: normal but significant condition
    const INFO = 6; // Informational: informational messages
    const DEBUG = 7; // Debug: debug messages

    /**
     * Log nothing at all
     */
    const OFF = -1;
    /**
     * Alias for CRIT
     * @deprecated
     */
    const FATAL = 2;
    /**
     * Alias for ERR
     * @deprecated
     */
    const ERROR = 3;
    /**
     * Alias for WARN
     * @deprecated
     */
    const WARNING = 4;

    /**
     * We need a default argument value in order to add the ability to easily
     * print out objects etc. But we can't use NULL, 0, FALSE, etc, because those
     * are often the values the developers will test for. So we'll make one up.
     *
     * Unfortunately, to comply with PSR-3, the $args parameter must be an array.
     * Thankfully, PHP marks array() === null && array() == false, so we can still
     * use this arbitrary measurement, but it's not nearly as effective.
     * Unfortunately, you can't create an array in a static constructor, so I have
     * to do the assignment in the class's constructor.
     */
    const NO_ARGUMENTS = null;//'Logger::NO_ARGUMENTS';

    /**
     * Current minimum logging threshold
     * @var integer
     */
    public $level = self::INFO;
    /**
     * List of writers for this Logger
     * @var array
     */
    protected $handlers = array();
    /**
     * Wether to also log to PHP's error_log()
     * @var boolean
     */
    public $enableErrorLog = true;

    /**
     * Default severity of log messages, if not specified
     * @var integer
     */
    private static $_defaultSeverity = self::DEBUG;
    /**
     * Array of KLogger instances, part of Singleton pattern
     * @var array
     */
    private static $_instance = null;

    /**
     * Partially implements the Singleton pattern. Each $logDirectory gets one
     * instance.
     *
     * @param string  $logDirectory File path to the logging directory
     * @param integer $severity     One of the pre-defined severity constants
     * @return KLogger
     */
    public static function instance()
    {
        if (self::$_instance === null) {
            // saves in constructor
            new Logger();
        }
        return self::$_instance;
    }

    /**
     * Class constructor. The most recently constructed Logger instance
     * is saved for usage with the self::instance() method.
     *
     * @param string  $logDirectory File path to the logging directory
     * @param integer $severity     One of the pre-defined severity constants
     * @return void
     */
    public function __construct($severity = null)
    {
        if ($severity !== null) {
            $this->setLevel($severity);
        }
        self::$_instance = $this;
    }

    /**
     * Convenience function for configuring this instance and adding handlers.
     * @param array $config
     */
    public function init(array $config)
    {
        if (isset($config['level'])) {
            $this->setLevel($config['level']);
        }
        if (isset($config['error_log'])) {
            $this->enableErrorLog = !!$config['error_log'];
        }
        if (isset($config['file']) && is_array($config['file'])) {
            $c = $config['file'];
            if (isset($c['dir'])) {
                $h = new FileWriter($c['dir']);

                if (isset($c['level'])) {
                    $h->setLevel($c['level']);
                }

                if (isset($c['date_format'])) {
                    $h->setDateFormat($c['date_format']);
                }

                $this->addHandler($h);
            } else {
                trigger_error(__CLASS__.": tried building a FileWriter, but no dir");
            }
        }
        if (isset($config['syslog']) && is_array($config['syslog'])) {
            $c = $config['syslog'];
            $ident = isset($c['ident']) ? $c['ident'] : false;
            $options = isset($c['options']) ? $c['options'] : 0;
            $facility = isset($c['facility']) ? $c['facility'] : LOG_USER;
            $h = new SyslogWriter($ident, $options, $facility);

            if (isset($c['level'])) {
                $h->setLevel($c['level']);
            }

            $this->addHandler($h);
        }
    }

    /**
     * Register this Logger instance as PHP's default uncaught exception handler.
     */
    public function registerErrorHandler()
    {
        set_error_handler(array($this, 'error_handler'));
    }

    /**
     * Handle a PHP exception as a log event. Translates user errors into appropriate
     * Logger levels.
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param number $errline
     * @param array $errcontext
     * @return boolean Always returns true to cause PHP to not use the built-in error handler
     */
    public function error_handler($errno, $errstr, $errfile = '[unknown]', $errline = 0, array $errcontext = null)
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }

        $format = "[$errno] $errstr";
        if ($errfile) {
            $format .= " ($errfile:$errline)";
        }

        $level = self::NOTICE;

        switch ($errno) {
            case E_USER_ERROR:
            case E_ERROR:
                $level = self::EMERG;
                break;

            case E_USER_WARNING:
            case E_WARNING:
                $level = self::WARN;
                break;

            case E_USER_NOTICE:
            case E_NOTICE:
            default:
                $level = self::NOTICE;
                break;
        }

        $this->log($level, $format);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Add a handler to the pool.
     * @param LogWriter $writer
     */
    public function addHandler(LogWriter $writer)
    {
        $this->handlers[] = $writer;
    }

    /**
     * Clear all handlers, and return the entire array.
     * @return array
     */
    public function clearHandlers()
    {
        $handlers = $this->handlers;
        $this->handlers = array();
        return $handlers;
    }

    /**
     * Sets the Log Level for this instance.
     * @param integer $lvl
     */
    public function setLevel($lvl)
    {
        $this->level = $lvl;
    }

    /**
     * Writes a $line to the log with a severity level of DEBUG
     *
     * @param string $line Information to log
     * @return void
     */
    public function logDebug($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::DEBUG, (array) $line);
    }

    /**
     * Writes a $line to the log with a severity level of INFO. Any information
     * can be used here, or it could be used with E_STRICT errors
     *
     * @param string $line Information to log
     * @return void
     */

    public function logInfo($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::INFO, $line, (array) $args);
    }

    /**
     * Writes a $line to the log with a severity level of NOTICE. Generally
     * corresponds to E_STRICT, E_NOTICE, or E_USER_NOTICE errors
     *
     * @param string $line Information to log
     * @return void
     */
    public function logNotice($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::NOTICE, $line, (array) $args);
    }

    /**
     * Writes a $line to the log with a severity level of WARN. Generally
     * corresponds to E_WARNING, E_USER_WARNING, E_CORE_WARNING, or
     * E_COMPILE_WARNING
     *
     * @param string $line Information to log
     * @return void
     */
    public function logWarn($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::WARN, $line, (array) $args);
    }

    /**
     * Writes a $line to the log with a severity level of ERR. Most likely used
     * with E_RECOVERABLE_ERROR
     *
     * @param string $line Information to log
     * @return void
     */
    public function logError($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::ERR, $line, (array) $args);
    }

    /**
     * Writes a $line to the log with a severity level of ALERT.
     *
     * @param string $line Information to log
     * @return void
     */
    public function logAlert($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::ALERT, $line, (array) $args);
    }

    /**
     * Writes a $line to the log with a severity level of CRIT.
     *
     * @param string $line Information to log
     * @return void
     */
    public function logCrit($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::CRIT, $line, (array) $args);
    }

    /**
     * Writes a $line to the log with a severity level of EMERG.
     *
     * @param string $line Information to log
     * @return void
     */
    public function logEmerg($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::EMERG, $line, (array) $args);
    }

    /**
     * Writes a $line to the log with a severity level of FATAL.
     *
     * @param string $line Information to log
     * @return void
     */
    public function logFatal($line, $args = self::NO_ARGUMENTS)
    {
        $this->log(self::FATAL, $line, (array) $args);
    }

    /**
     * Writes a $line to the log with the given severity
     *
     * @param integer $level    Severity level of log message (use constants)
     * @param string  $line     Text to add to the log
     */
    public function log($level, $line, array $args = null)
    {
        if (is_string($level)) {
            $level = $this->_strToLevel($level);
        }

        if ($level > $this->level) {
            return;
        }

        $status = $this->_levelToString($level);
        $dump = '';

        // $args will never be NO_ARGUMENTS, because an array is technically an object
        if (count($args)) { //$args !== self::NO_ARGUMENTS) {
            /* Print the passed object value */
            $dump = '; ' . var_export($args, true);
        }

        $this->logLine($level, "$status $line$dump");
    }

    /**
     * Separated from log() to comply with LogWriter.
     * @see LogWriter::logLine()
     */
    public function logLine($level, $line)
    {
        if ($level > $this->level) {
            return;
        }

        if ($this->enableErrorLog) {
            error_log($line);
        }

        foreach ($this->handlers as $h) {
            $h->logLine($level, $line);
        }
    }

    private function _strToLevel($str)
    {
        $str = strtolower($str);
        $level = self::$_defaultSeverity;

        switch ($str) {
            case 'emergency':
            case 'emerg':
                $level = Logger::EMERG;
                break;
            case 'alert':
                $level = Logger::ALERT;
                break;
            case 'critical':
            case 'crit':
                $level = Logger::CRIT;
                break;
            case 'error':
            case 'err':
                $level = Logger::ERR;
                break;
            case 'warning':
            case 'warn':
                $level = Logger::WARN;
                break;
            case 'notice':
                $level = Logger::NOTICE;
                break;
            case 'info':
                $level = Logger::INFO;
                break;
            case 'debug':
                $level = Logger::DEBUG;
                break;
        }

        return $level;
    }

    private function _levelToString($level)
    {
        switch ($level) {
            case self::EMERG:
                return "--EMERG--";
            case self::ALERT:
                return "--ALERT--";
            case self::CRIT:
                return "--CRIT--";
            case self::FATAL: # FATAL is an alias of CRIT
                return "--FATAL--";
            case self::NOTICE:
                return "--NOTICE--";
            case self::INFO:
                return "--INFO--";
            case self::WARN:
                return "--WARN--";
            case self::DEBUG:
                return "--DEBUG--";
            case self::ERR:
                return "--ERROR--";
            default:
                return "--LOG--";
        }
    }
} // class


/**
 * Writes messages to a flat file, prepending a date format.
 * @author Jesse Decker <jesse.decker@am.sony.com>
 * @date Jun 5, 2013
 * @version 0.1
 */
class FileWriter extends AbstractLogWriter
{

    /**
     * Internal status codes
     */
    const STATUS_LOG_OPEN = 1;
    const STATUS_OPEN_FAILED = 2;
    const STATUS_LOG_CLOSED = 3;

    /**
     * Octal notation for default permissions of the log file
     * @var integer
     */
    private static $_defaultPermissions = 0777;

    /**
     * Standard messages produced by the class. Can be modified for il8n
     * @var array
     */
    private static $_messages = array(
        'writefail'   => 'Could not write to log file. Check that permissions are correct.',
        'opensuccess' => 'Log file opened successfully.',
        'openfail'    => 'Log file could not be opened. Check permissions.',
    );

    /**
     * Current status of the log file
     * @var integer
     */
    private $_logStatus = self::STATUS_LOG_CLOSED;
    /**
     * Path to the log file
     * @var string
     */
    private $_logFilePath = null;
    /**
     * This holds the file handle for this instance's log file
     * @var resource
     */
    private $_fileHandle = null;
    /**
     * Valid PHP date() format string for log timestamps
     * @var string
     */
    private $_dateFormat = 'Y-m-d G:i:s O';

    /**
     * Class constructor
     *
     * @param string  $logDirectory File path to the logging directory
     * @param integer $severity     One of the pre-defined severity constants
     * @return void
     */
    public function __construct($logDirectory, $severity = null)
    {
        if ($severity !== null) {
            $this->setLevel($severity);
        }

        $this->setFilepath($logDirectory);
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Close any open file handles.
     */
    public function close()
    {
        if ($this->_fileHandle) {
            fclose($this->_fileHandle);
        }
    }

    /**
     * Sets the date format used by all instances of KLogger
     *
     * @param string $dateFormat Valid format string for date()
     */
    public function setDateFormat($dateFormat)
    {
        $this->_dateFormat = $dateFormat;
    }

    /**
     * Sets up the file handle and opens a file for appending. If passed null, will close the file.
     *
     * @param string $path Directory where to place log files
     */
    public function setFilepath($logDirectory)
    {
        $this->_logStatus = self::STATUS_LOG_CLOSED;

        if (!$logDirectory) {
            if ($this->_fileHandle) {
                fclose($this->_fileHandle);
            }
            return;
        }

        $logDirectory = rtrim($logDirectory, '\\/');

        $this->_logFilePath = $logDirectory . DIRECTORY_SEPARATOR . 'log_' . date('Y-m-d') . '.txt';

        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, self::$_defaultPermissions, true);
        }

        $format = __CLASS__ . ": %s ({$this->_logFilePath})";

        if (file_exists($this->_logFilePath) && !is_writable($this->_logFilePath)) {
            $this->_logStatus = self::STATUS_OPEN_FAILED;
            trigger_error(sprintf($format, self::$_messages['writefail']), E_USER_WARNING);
            return;
        }

        if (is_writable($logDirectory) && ($this->_fileHandle = fopen($this->_logFilePath, 'a'))) {
            // Log BEFORE we open, else this will go in the file
            Logger::instance()->log(Logger::DEBUG, sprintf($format, self::$_messages['opensuccess']));
            $this->_logStatus = self::STATUS_LOG_OPEN;
        } else {
            $this->_logStatus = self::STATUS_OPEN_FAILED;
            trigger_error(sprintf($format, self::$_messages['openfail']), E_USER_WARNING);
        }
    }

    /**
     * Writes a line to the log without prepending a status or timestamp
     *
     * @param string $line Line to write to the log
     * @return void
     */
    public function logLine($level, $line, array $context = null)
    {
        if ($this->_logStatus !== self::STATUS_LOG_OPEN || $this->level < $level) {
            return;
        }

        $line = date($this->_dateFormat) . ' ' . $line;

        if (fwrite($this->_fileHandle, $line . PHP_EOL) === false) {
            // Change status FIRST, else we might get two errors
            $this->_logStatus = self::STATUS_OPEN_FAILED;
            trigger_error(self::$_messages['writefail'], E_USER_WARNING);
        }
    }

}

/**
 * Writes messages to syslog.
 * @author Jesse Decker <jesse.decker@am.sony.com>
 * @date Jun 5, 2013
 * @version 0.1
 */
class SyslogWriter extends AbstractLogWriter
{
    private $status = false;

    /**
     * Constructor
     * @param unknown $facility
     */
    public function __construct($ident = false, $option = 0, $facility = LOG_USER)
    {
        $this->status = openlog($ident, $option, $facility);
    }

    /**
     * Closes any open connections.
     *
     * This function is commented out. Syslog connections are implicitly closed by PHP
     * on shutdown, so this is not necessary. Also, a syslog connection is per-process,
     * so multiple instances of this class have to re-use the connection.
     */
    public function __destruct()
    {
        //closelog();
    }

    /**
     * Push message to syslog.
     * @see LogWriter::log()
     */
    public function logLine($level, $message)
    {
        if ($this->status && $this->level < $level) {
            return;
        }

        switch ($level) {
            case Logger::ALERT:
                $level = LOG_ALERT;
                break;
            case Logger::CRIT:
                $level = LOG_CRIT;
                break;
            case Logger::DEBUG:
                $level = LOG_DEBUG;
                break;
            case Logger::EMERG:
                $level = LOG_EMERG;
                break;
            case Logger::ERR:
                $level = LOG_ERR;
                break;
            case Logger::INFO:
                $level = LOG_INFO;
                break;
            case Logger::NOTICE:
                $level = LOG_NOTICE;
                break;
            case Logger::WARN:
                $level = LOG_WARNING;
                break;
        }

        syslog($level, $message);
    }
}
