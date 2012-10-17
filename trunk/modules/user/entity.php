<?php
    defined('M2_MICRO') or die('Direct Access to this location is not allowed.');

    /**
     * Entity class for user operations
     * NOTICE: This class implemented as Singleton
     * @name $database
     * @package M2 Micro Framework
     * @subpackage Library
     * @author Alexander Chaika
     * @since 0.2RC2
     * @todo TEST IT!!!
     */
    
    class UserEntity extends Entity {
        private $id;
        private $username;
        private $email;
        private $password;
        private $group;
            
        protected static $instance = null;

        /**
         * Singleton protection
         * @param int $id user ID for load
         */
        protected function __construct($id = null) {
            // Get db handler
            $this->database = Database::getInstance();
        }
        
        /**
          * GetInstance class method
          * @return object $instance
          */
        public static function getInstance() {
            if (is_null(self::$instance)) {
                self::$instance = new UserEntity;
            }
            return self::$instance;
        }
        
        /**
         * Create user by email and pass
         * @param array $options
         * @return int $uid or 0 if not exist
         */
        public function create($options) {
            // Get items
            $this->database->query("CALL UPSERT_USER(0, '".$options['username']."','".$options['email']."','".md5(Application::$config['secret'].$options['password'])."');");
            return $this->database->getLastInsertId();
        }
        
        /**
         * Get user id by email and pass or cookie token
         * @param array $options[email+password || cookie] OPTIONAL
         * @return int $user_id or 0 if not exist
         */
        public function getId($options = null) {
            // Check email & pass
            if (isset($options['email']) && isset($options['password'])) {
                $this->database->query("CALL CHECK_LOGIN('".$options['email']."','".md5(Application::$config['secret'].$options['password'])."');");
                if ($result = $this->database->getField()) {
                    return $result;
                }
            }
            
            // Check cookie
            if (isset($options['cookie'])) {
                $this->database->query("CALL CHECK_COOKIE('".Application::$config['secret']."','".$options['cookie']."');");
                if ($result = $this->database->getField()) {
                    return $result;
                }
            }
            
            // Check username
            if (isset($options['username'])) {
                $this->database->query("CALL CHECK_USERNAME('".$options['username']."');");
                if ($result = $this->database->getField()) {
                    return $result;
                }
            }
            
            // Check internal id
            if ((int)$this->id > 0) {
                return $this->id;
            }
            
            // Check session
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
                return $_SESSION['user_id'];
            }
            
            return false;
        }
        
        /**
         * Load user data to self object by id
         * @param int $id user ID
         * @return object $data or false if not exist
         */
        public function load($id) {
            // Check id
            if (empty($id)) {
                return $this->_throw('User id could not be empty');
            }
            
            // Get items
            $this->database->query("CALL GET_USER_BY_ID($id);");
            if ($result = $this->database->getObject()) {
                // Add data to object
                $this->id = $result->id;
                $this->username = $result->username;
                $this->email = $result->email;
                $this->password = $result->password;
                $this->group = $result->group;
                
                // Return data
                return $result;
            } else {
                return false;
            }
        }
        
        /**
         * Setup user session by id
         * @param int $id user ID
         * @param bool $remember (optional) save session
         * @return object $this or false if not exist
         */
        public function setupSession($id, $remember = true) {
            // Check user id
            if (empty($id)) {
                return $this->_throw(T('User ID could not be empty'), WARNING);
            }

            // Set session
            $_SESSION['user_id'] = $id;
            $result = $this->checkSession();
            if ($result) {
                // Set cookie
                if ($remember) {
                    setCookie('token', md5(Application::$config['secret'] . $result->email));
                } else {
                    setCookie('token', '');
                }

                return $this;
            } else {
                return $result;
            }
        }
        
        /**
         * Check user session and try to setup it
         * @return bool $result
         */
        public function checkSession() {
            // Check local data
            if (empty($this->id) ||
                empty($this->username) ||
                empty($this->email) ||
                empty($this->password) ||
                empty($this->group) ||
                empty($_SESSION['user_id'])) {
                    // try to load
                    return $this->load($_SESSION['user_id']);
            }

            return true;
        }
        
        /**
         * Clear user session data
         * @return bool TRUE
         */
        public function clearSession() {
            // Clear session data
            setCookie('token', '');
            $_SESSION['user_id'] = 0;
            
            // Clear user object
            $this->id = null;
            $this->username = null;
            $this->email = null;
            $this->password = null;
            $this->group = null;
                
            return true;
        }
        
        /**
         * Clear user session data
         * @return bool $result
         */
        public function isLoggined() {
            // Get cookie uid
            if (isset($_COOKIE['token']) && $user_id = $this->getId(array('cookie' => $_COOKIE['token']))) {
                if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = $user_id;
                return true;
            }

            // Check session
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
                $this->id = $_SESSION['user_id'];
                return $this->checkSession();
            }

            return false;
        }

        /**
         * Check user by email
         * @param array $email
         * @return bool $result
         */
        public function checkEmail($email) {
            // Get items
            $this->database->query("CALL CHECK_EMAIL('".$email."');");
            return ($this->database->checkResult() ? true : false);
        }
        
        /**
         * Set new pass for user by email
         * @param string $email
         * @param string $password
         * @return bool $result
         */
        public function setNewPassword($email, $password) {
            // Get items
            $this->database->query("CALL UPDATE_PASSWORD('".$email."','".md5(Application::$config['secret'] . $password)."');");
            return ($this->database->checkResult() ? true : false);
        }

        /**
         * Get username
         * @return string $username
         */
        public function getUsername() {
            $this->checkSession();
            return $this->username;
        }

        /**
         * Get user group
         * @return string $username
         */
        public function getGroup() {
            $this->checkSession();
            return $this->group;
        }
        
        /**
         * Get user IP
         * @return string $ip
         */
        public static function getIp() {
            if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                return $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                return $_SERVER['REMOTE_ADDR'];
            }
        }

        /**
         * Get user browser
         * @return string $user_agent
         */
        public static function getUserAgent() {
            return (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : false);
        }

        /**
         * Get referer
         * @return string $referer
         */
        public static function getReferer() {
            return (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false);
        }
    }
