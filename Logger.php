<?php

/**
 * @author Todd Hedrick
 *
 * Class Logger
 * This class will contain ONLY functions for logging messages to the log file
 *
 * All log messages from this class are prepended with "[utc_timestamp][{$uppercase_level}] "
 *
 * Levels: debug, info, warn, deprecated, error, fatal, security
 *
 * Example: $log->debug('<message>');
 * 
 * We recommend using this class in a global variable: $GLOBALS['log'] = new Logger($config);
 */
class Logger {
    private const DEFAULT_LOGGING_LEVEL = 'deprecated';
    private const DEFAULT_LOG_FILE_LOCATION = 'application.log';

    /** @var string $current_level */
    private $current_level;

    /**
     * These are the log level mappings anything with a lower value than your current log level will be logged
     * @var array
     */
    private $logging_levels = array(
        'debug' => 99,
        'info' => 6,
        'warn' => 5,
        'deprecated' => 4,
        'error' => 3,
        'fatal' => 2,
        'security' => 1,
        'off' => 0,
    );

    /** @var array $config */
    private $config = array();

    /**
     * @param array $config
     * @property string $config['logging_level']
     * @property string $config['log_file_location']
     */
    public function __construct($config){
        $this->config = $config;
    }

    /**
     * @param $message
     */
    public function debug($message) {
        $this->log(__FUNCTION__, $message);
    }

    /**
     * @param $message
     */
    public function info($message) {
        $this->log(__FUNCTION__, $message);
    }

    /**
     * @param $message
     */
    public function warn($message) {
        $this->log(__FUNCTION__, $message);
    }

    /**
     * @param $message
     */
    public function deprecated($message) {
        $this->log(__FUNCTION__, $message);
    }

    /**
     * @param $message
     */
    public function error($message) {
        $this->log(__FUNCTION__, $message);
    }

    /**
     * @param $message
     */
    public function fatal($message) {
        $this->log(__FUNCTION__, $message);
    }

    /**
     * @param $message
     */
    public function security($message) {
        $this->log(__FUNCTION__, $message);
    }
  
    /**
     * Sets the $this->$current_level to the default or to the value in the $this->config
     */
    private function getCurrentLoggingLevel() {
        if (!isset($this->$current_level)) {
            $key = "logging_level";
            $this->$current_level = (isset($this->config[$key])) ? $this->config[$key] : self::DEFAULT_LOGGING_LEVEL;
        }
    }

    /**
     * @param string $level
     * @param string $message
     */
    private function log($level, $message) {
        $this->getCurrentLoggingLevel();
        if ($this->$logging_levels[$level] <= $this->$logging_levels[$this->$current_level]) {
            $uppercase_level = strtoupper($level);
            $timestamp = gmdate('Y-m-d H:i:s');
            $log_message = "[{$timestamp}][{$uppercase_level}] " . print_r($message, 1) . PHP_EOL;

            $key = "log_file_location";
            $filename = (isset($this->config[$key])) ? $this->config[$key] : self::DEFAULT_LOG_FILE_LOCATION;
            file_put_contents($filename, $log_message, FILE_APPEND);
        }
    }
}
