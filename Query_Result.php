<?php 

    /**
     * Query_Result.php
     *
     * @author Christopher Brine <Chris.C.Brine@gmail.com>
     * @copyright 2018 Christopher Brine
     * @version 1.0
     */
    
    namespace Query_Builder;

    class Query_Result implements \Iterator, \ArrayAccess, \Serializable, \Countable {

        /**
         * $QB_RESULTS - Referred to as dataset
         * @var Array
         */
        private $QB_RESULTS;

        /** 
         * $position Current Postion of Iterator
         * @var string|int
         */
        private $position;

        /**
         * __construct 
         * @param array $qb_results results from Query_Builder's Get Method(s)
         */
        function __construct(array $qb_results) {
            $this->QB_RESULTS = $qb_results; 
        }

        //////////////
        // Iterator //
        //////////////

        public function current() {
            return current($this->QB_RESULTS);
        } 
        public function rewind() {
            return reset($this->QB_RESULTS);
        }
        public function next() {
            return next($this->QB_RESULTS);
        }
        public function key() {
            return key($this->QB_RESULTS);
        }
        public function valid() {
            return key($this->QB_RESULTS) !== null;
        }

        /////////////////
        // ArrayAccess //
        /////////////////
        
        public function offsetSet($offset, $value) {
            if (is_null($offset)) {
                $this->QB_RESULTS[] = $value;
            } else {
                $this->QB_RESULTS[$offset] = $value;
            }
        }

        public function offsetExists($offset) {
            return isset($this->QB_RESULTS[$offset]);
        }

        public function offsetUnset($offset) {
            unset($this->QB_RESULTS[$offset]);
        }
        
        public function offsetGet($offset) {
            return isset($this->QB_RESULTS[$offset]) ? $this->QB_RESULTS[$offset] : null;
        }

        //////////////////
        // Serializable //
        //////////////////
        
        public function serialize() {
            return serialize([
                $this->QB_RESULTS,
                $this->position
            ]);
        }

        public function unserialize($data) {
            list($this->QB_RESULTS, $this->position) = unserialize($data);
        }

        // Countable
        public function count() {
            return count($this->QB_RESULTS);
        }

        // 

        ///////////////////
        // Magic Methods //
        ///////////////////

        // __debugInfo Specifies what is shown when dumping the object 
        //  ( var_dump($query_result) or print_r($query_result) )
         public function __debugInfo() {
            return $this->QB_RESULTS;
        }

        // $query_result->$name
        public function __get($name){
            
            // Return it if it exists
            if($this->__isset($name)) return $this->QB_RESULTS[$name];
            
            // Give me a soft warning and return null
            $trace = debug_backtrace();
            trigger_error(
                'Undefined property: '.__CLASS__.'::' . $name .
                ' in ' . $trace[0]['file'] .
                ' on line ' . $trace[0]['line'],
                E_USER_NOTICE);
            return null;
        }
        
        // $query_result->$name = "new value";
        public function __set($name, $value) {
            $this->QB_RESULTS[$name] = $value;
        }

        // isset($query_result->$name)
        public function __isset($name) {
            return array_key_exists($name, $this->QB_RESULTS);
        }

        // unset($query_result->$name)
        public function __unset($name) {
            unset($this->QB_RESULTS[$name]);
        }

        ////////////////////
        // Custom Methods //
        ////////////////////

        /**
         * toArray returns current dataset as array.
         * @return array current dataset
         */
        public function toArray() {
            return $this->QB_RESULTS;
        }

        /**
         * all alias of toArray()
         * @return array current dataset
         */
        public function all() {
            return $this->toArray();
        }
        
        /**
         * avg returns the average value of a given key
         * @param  int|string $key 2nd dimensional array key
         * @return numeric          Average
         */
        public function avg($key = null) {
            $to_average = is_null($key) ? $this->QB_RESULTS : array_column($this->QB_RESULTS, $key);
            return array_sum($to_average) / count($to_average);
        }

        /**
         * toJSON returns current dataset as JSON encoded array
         * @return string JSON encoded array
         */
        public function toJSON($args = JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT) {
            $result = json_encode($this->QB_RESULTS, $args);
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    return $result;
                break;
                case JSON_ERROR_DEPTH:
                    $error = 'Maximum stack depth exceeded';
                break;
                case JSON_ERROR_STATE_MISMATCH:
                    $error = 'Underflow or the modes mismatch';
                break;
                case JSON_ERROR_CTRL_CHAR:
                    $error = 'Unexpected control character found';
                break;
                case JSON_ERROR_SYNTAX:
                    $error = 'Syntax error, malformed JSON';
                break;
                case JSON_ERROR_UTF8:
                    // Malformed UTF-8 characters, possibly incorrectly encoded
                    $array = $this->QB_RESULTS;
                    array_walk_recursive($array, function(&$item, $key){
                        if(!mb_detect_encoding($item, 'utf-8', true)){
                                $item = utf8_encode($item);
                        }
                    });
                   return json_encode($array, $args);
                break;
                default:
                    $error = 'Unknown error';
                break;
                die(__METHOD__ . " - $reason");
            }
        }

        /**
         * recursiveSort - Sorts all levels of dataset
         * @param  string       $order      ASC (Ascending) | DESC (Descending)
         * @param  boolean      $natural    TRUE = compare items numerically | False = compare items normally
         * @return QueryResult              With dataset sorted on all levels
         */
        public function recursiveSort($order = "ASC", $natural = TRUE) {
            function recursiveSortCall(&$to_sort, $order, $natural) {
                if (is_array($to_sort)) {
                    $order($to_sort, $natrual);
                    foreach ($to_sort as &$value) {
                        recursiveSortCall($value);
                    }
                }    
            }
            recursiveSortCall($this->QB_RESULTS, "DESC" ? 'krsort' : "ksort", $natural ? SORT_NATURAL : SORT_REGULAR);
            return $this;
        }

        /**
         * set - Sets multidemensional key (MDK) with the new value
         * @param  array         $keys         MDK
         * @param  any           $new_value    Value to set
         * @return QueryResult                 With new value on MDK
         */
        function set(array $keys, $new_value) {
            $pointer = &$this->QB_RESULTS;
            foreach ($keys as $key) {
                $pointer = &$pointer[$key];
            }
            $pointer['info'] = $new_value;
            return $this;
        }

        /**
         * filter - Removes from dataset based on function supplied (TRUE = Keep, FALSE = Remove)
         * @param  callable     $filter     Function to test key value pairs against
         * @return QueryResult              With new value on MDK
         */
        public function filter(callable $filter) {
            $results = [];
            foreach ($this->QB_RESULTS as $key => $value) {
                if ($filter($value, $key)) {
                    $results[] = $value;
                }
            }
            $this->QB_RESULTS = $results;
            return $this;
        }

        /**
         * reject - Removes from dataset based on function supplied (FALSE = Keep, TRUE = Remove)
         * @param  callable     $filter     Function to test key value pairs against
         * @return QueryResult              With new value on MDK
         */
        public function reject(callable $filter) {
            $this->filter(!$filter);
            return $this;
        }
    }
 ?>