<?php
    /**
     * M2 Micro Framework - a micro PHP 5 framework
     *
     * @author      Alexander Chaika <marco.manti@gmail.com>
     * @copyright   2012 Alexander Chaika
     * @link        https://github.com/marco-manti/M2_micro
     * @version     0.3
     * @package     M2 Micro Framework
     * @license     https://raw.github.com/marco-manti/M2_micro/manti-by-dev/NEW-BSD-LICENSE
     *
     * NEW BSD LICENSE
     *
     * All rights reserved.
     *
     * Redistribution and use in source and binary forms, with or without
     * modification, are permitted provided that the following conditions are met:
     *  * Redistributions of source code must retain the above copyright
     * notice, this list of conditions and the following disclaimer.
     *  * Redistributions in binary form must reproduce the above copyright
     * notice, this list of conditions and the following disclaimer in the
     * documentation and/or other materials provided with the distribution.
     *  * Neither the name of the "M2 Micro Framework" nor "manti.by" nor the
     * names of its contributors may be used to endorse or promote products
     * derived from this software without specific prior written permission.

     * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
     * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
     * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
     * DISCLAIMED. IN NO EVENT SHALL COPYRIGHT HOLDER BE LIABLE FOR ANY
     * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
     * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
     * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
     * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
     * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
     * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
     */

    defined('M2_MICRO') or die('Direct Access to this location is not allowed.');

    /**
     * Class for database operations (Uses MySQLi extention)
     * NOTICE: This class implemented as Singleton
     * @name $database
     * @package M2 Micro Framework
     * @subpackage Library
     * @author Alexander Chaika
     * @since 0.1
     */
    class Database extends Application {

        /**
         * @var MySQLi_result|bool $res mysql resource pointer
         */
        private $res;

        /**
         * @var MySQLi|bool $mysqli mysql object
         */
        private $mysqli;

        /**
         * @var string $db_prefix tables prefix
         */
        private $db_prefix;

        /**
         * @var string $query latest query
         */
        private $query;
        
        /**
         * @var string $hash latest query hash
         */
        private $hash;

        /**
         * @var object $instance self pointer
         */
        protected static $instance = null;

        /**
         * Singleton protection
         */
        protected function __construct() {
            // Try connect to DB
            $this->connect();
        }

        /**
         * GetInstance class method
         * @return Database $instance
         */
        public static function getInstance() {
            if (is_null(self::$instance)) {
                self::$instance = new Database;
            }
            return self::$instance;
        }

        /**
         * Connect to DB and select TABLE
         * @return bool $state
         */
        public function connect() {
            // Already initialized?
            if (!empty($this->mysqli) && is_object($this->mysqli)) {
                return $this->mysqli;
            }

            // Parse config
            if (isset(Application::$config['db_path']) &&
                isset(Application::$config['db_port']) &&
                isset(Application::$config['db_user']) &&
                isset(Application::$config['db_pass']) &&
                isset(Application::$config['db_base'])) {
                    $this->db_prefix = Application::$config['db_prefix'];
            } else {
                return $this->_throw(T('Invalid DB config'), ERROR);
            }

            // Connect to DB
            $this->mysqli = new mysqli(
                Application::$config['db_path'],
                Application::$config['db_user'],
                Application::$config['db_pass'],
                Application::$config['db_base'],
                Application::$config['db_port']
            );

            if (!$this->mysqli || !empty($this->mysqli->error) || !empty($this->mysqli->connect_error)) {
                $error = !empty($this->mysqli->error) ? $this->mysqli->error : $this->mysqli->connect_error;
                return $this->_throw(T($error), ERROR);
            }

            // Set encoding
            if (!empty(Application::$config['db_encoding'])) {
                if (!$this->mysqli->query("SET NAMES '" . Application::$config['db_encoding'] . "'")) {
                    return $this->_throw(T($this->mysqli->error), ERROR);
                }
            }

            return $this->mysqli;
        }

        /**
         * Close connection to DB
         * @return bool $state
         */
        public function close() {
            if (!$this->mysqli->close()) {
                return $this->_throw($this->getError());
            } else {
                return $this->_clean();
            }
        }

        /**
         * Check last operation result
         * @return bool $result
         */
        public function checkResult() {
            if ($this->result < 1 && $this->getLastErrorNum() != 0) {
                return $this->_throw($this->getError(), ERROR);
            } elseif (($this->result < 1 && $this->getLastErrorNum() == 0) || !$this->res) { // #43635349
                return $this->_throw(T('Empty result'), NOTICE);
            } else {
                return true;
            }
        }

        /**
         * Replace #__ to db prefix
         * @param string $query query to execute
         * @return string $query with replaced prefix
         */
        private function replacePrefix($query) {
            return str_replace('#__', $this->db_prefix, $query);
        }

        /**
         * Set and execute query
         * if success return number of affected rows or false
         * !write the names of all tables without the prefix, but like #__
         * @param string $query query to execute
         * @return bool $state
         */
        public function query($query) {
            // Execute
            $this->query = $this->replacePrefix($query);
            $this->hash = Application::$config['memcache_suffix'] . '-model-' . crc32($query);

            // Check cache
            $result = Cache::get($this->hash);
            if ($result) {
                $this->result = 1;
                return true;
            }

            // Add debug info
            $this->logSQL($this->query);

            // Execute query
            $this->res = $this->mysqli->query($this->query);
            if ($this->getLastErrorNum() > 0) {
                return $this->_throw($this->getError(), ERROR);
            } else {
                $this->result = 1;
                while ($this->mysqli->next_result()) {
                    $resource = $this->mysqli->store_result();
                    $this->res = $resource ? $resource : $this->res;
                }
                return true;
            }
        }

        /**
         * Get first field value of result
         * @return string|bool $result
         */
        public function getField() {
            // Check cache
            if ($result = Cache::get($this->hash)) return $result;
        
            // Check result
            if (!$this->checkResult()) {
                return false;
            }

            // Return first field
            // #43635349 - Fix multiple saving for new posts
            $row = $this->res->fetch_row();

            // Check CALL result
            $this->res->free_result();

            if (!$row || empty($row)) {
                return false;
            } else {
                Cache::set($this->hash, $row[0]);
                return $row[0];
            }
        }

        /**
         * Get first resulting object of query
         * @return object|bool $result resulted object or false
         */
        public function getObject() {
            // Check cache
            if ($result = Cache::get($this->hash)) return $result;

            // Check result
            if (!$this->checkResult()) {
                return false;
            }

            // Return object
            $obj = $this->res->fetch_object();

            // Check CALL result
            $this->res->free_result();

            if (!$obj || empty($obj)) {
                return false;
            } else {
                Cache::set($this->hash, $obj);
                return $obj;
            }
        }

        /**
         * Get result objects array of query
         * @return array|bool $result resulted array of objects or false
         */
        public function getObjectsArray() {
            // Check cache
            if ($result = Cache::get($this->hash)) return $result;

            // Check result
            if (!$this->checkResult()) {
                return false;
            }

            // Return objects
            $result = array();
            /** @noinspection PhpAssignmentInConditionInspection */
            while($obj = $this->res->fetch_object()) {
                if (!$obj || empty($obj)) break;
                $result[] = $obj;
            }

            // Check CALL result
            $this->res->free_result();

            Cache::set($this->hash, $result);
            return $result;
        }

        /**
         * Get result array of query
         * @return array|bool $result resulted array of rows (arrays too) or false
         */
        public function getArray() {
            // Check cache
            if ($result = Cache::get($this->hash)) return $result;

            // Check result
            if (!$this->checkResult()) {
                return false;
            }

            // Return array
            $result = array();
            /** @noinspection PhpAssignmentInConditionInspection */
            while($row = $this->res->fetch_array(MYSQLI_NUM)) {
                if (!$row || empty($row)) break;
                $result[] = $row[0];
            }

            // Check CALL result
            $this->res->free_result();

            Cache::set($this->hash, $result);
            return $result;
        }

        /**
         * Get array of named pairs
         * @param string $index - array index name
         * @param string $field - array field name
         * @return array|bool $result associative array of pairs ($index => $field) or false
         */
        public function getPairs($index, $field) {
            // Check cache
            if ($result = Cache::get($this->hash)) return $result;

            // Check result
            if (!$this->checkResult()) {
                return false;
            }

            // Return pairs
            $result = array();
            /** @noinspection PhpAssignmentInConditionInspection */
            while($obj = $this->res->fetch_object()) {
                if (!$obj || empty($obj)) break;

                if (empty($obj->$index)) {
                    return $this->_throw(T('Incorrect DB index'));
                }

                $result[(int)$obj->$index] = $obj->$field;
            }

            // Check CALL result
            $this->res->free_result();

            Cache::set($this->hash, $result);
            return $result;
        }

        /**
         * Get last error string with error number
         * @return string $error last mysql error string and number
         */
        public function getError() {
            return $this->mysqli->errno .' : '. $this->mysqli->error;
        }

        /**
         * Get last error message
         * @return string $error last mysql error string
         */
        public function getLastErrorMsg() {
            return $this->mysqli->error;
        }

        /**
         * Get last error number
         * @return int $error_number last mysql error number
         */
        public function getLastErrorNum() {
            return (int)$this->mysqli->errno;
        }

        /**
         * Get last insert id
         * @return int $id
         */
        public function getLastInsertId() {
            return (int)($this->mysqli->insert_id ? $this->mysqli->insert_id : $this->getField());
        }

        /**
         * Escape string characters
         * @param string $string
         * @return string $result
         */
        public function escape($string) {
            return $this->mysqli->escape_string($string);
        }
    }