<?php 

    /**
     * Query_Builder.php
     *
     * @author Christopher Brine <Chris.C.Brine@gmail.com>
     * @copyright 2018 Christopher Brine
     * @version 1.0
     */
    
    namespace Query_Builder;

    require_once 'query_result.php';

    class Query_Builder {


        //////////////////////
        // Object Variables //
        //////////////////////
                 
        /**
         * $SELECT what's being selected
         * @var string
         */
        private $SELECT;

        /**
         * $COMMAND is the query command used
         *     Currently supported DML types:
         *         SELECT|UPDATE|INSERT|DELETE
         * @var string
         */
        private $COMMAND;

        /**
         * $CONDITIONALS conditional statements
         * @var string
         */
        private $CONDITIONALS;

        /**
         * $JOINS Table Joins    // methods coming soon!
         * @var string
         */
        private $JOINS;

        /**
         * $SORTS - Order By | Group By   // methods coming soon!
         * @var string
         */
        private $SORTS;    

        /**
         * $Having - Raw query
         * @var string
         */
        private $HAVING;    

        /**
         * $DB is the MySQLi Connection
         * @var Object of type MySQLi
         */
        private $DB;

        /**
         * $DB_INFO contains the MySQLi DB Connection Information
         *         Host name or an IP address (Note: Prepending with p: opens persistent connection),
         *         User Name,
         *         Password,
         *         Database Name,
         *         Port,
         *         Socket
         * @var array
         */
        private $DB_INFO;

        /**
         * $TABLE_NAME Name of the table being querried aka base table
         * @var string
         */
        private $TABLE_NAME;    

        /**
         * $PK Primary Key Field Name
         * @var int
         */
        private $PK;
        
        /**
         * $GROUP_START Track start of grouping
         * @var boolean
         */
        private $GROUP_START;

        /**
         * $FIELDS description
         * @var Array
         */
        private $FIELDS;

        /**
         * $HELP Flag for query builder helping
         * @var boolean
         */
        private $HELP;

        /**
         * $SHOW_DELETED Model uses soft deletes
         * @var boolean
         */
        private $SHOW_DELETED;

        /**
         * $LIMIT Stores the recored return limit if provided
         * @var int
         */
        private $LIMIT;

        /**
         * $KEYED_BY the column to key the return array by
         * @var string
         */
        private $KEYED_BY;

        /**
         * $DISTINCT signals if the SELECT is actually a SELECT DISTINCT
         * @var boolean
         */
        private $DISTINCT;

        /**
         * $rows_affected returns the count of the rows affected for UPDATE and DELETE querries
         * @var int
         */
        private $rows_affected;

        ///////////////////
        // Magic Methods //
        ///////////////////

        /**
         * __construct Runs on initialization ( new $builder($table_name); ) 
         */
        public function __construct($table = null, $helper = false) {

            // If date.timezone isn't set in php.ini, then set it as UTC
            if ( empty(ini_get('date.timezone')) && @date_default_timezone_get() == 'UTC' ) {
                date_default_timezone_set('UTC');
            }
        	
            /////////////////////////
            // DB Connection Setup //
            /////////////////////////
            $this->DB_INFO = [
                'HOST' => '127.0.0.1',
                'USER_NAME' => 'root',
                'PASSWORD' => 'root',
                'DATABASE_NAME' => 'mydb',
                // 'PORT' => null, // Default: 3306
                // 'SOCKET' => null, 
            ];
            
            // Connect to DB
            $this->DB = new \mysqli(...array_values($this->DB_INFO));

            // Die and explain connection error (if any)
            if ($this->DB->connect_error) die("Connect Error ({$this->DB->connect_errno}) {$this->DB->connect_error}");

            // Set helper on initialization
            $this->HELP = $helper;

            // Set Table to one provided (can be initalized without it but must be set)
            if (!is_null($table)) $this->table($table); 

            $this->QUERY_TYPE = 'select';
        	
        }

        /**
         * __toString Allows printing of the query by printing the object ( echo $builder; ) 
         * @return string the full SQL Query that's been built so far
         */
        public function __toString() {

            // Fully closed query
            return $this->subquery() . ';';
        }

        /**
         * subquery generates the full query as a subquery
         * @return string subquery
         */
        public function subquery() {

            if (!empty($this->INSERT)) {
                return "{$this->INSERT};";
            }
            
            if (empty($this->SELECT)) {
                // defaults to select ALL  
                //  *Note: UPDATES are stored in SELECT
                $this->select(); 
            }

            $conditionals = $this->CONDITIONALS;

            // Model uses soft deletes, filter them out unless othwise requested via withTrashed()
            if (isset($this->FIELDS[$this->TABLE_NAME . '.deleted_at']) && !$this->SHOW_DELETED) $conditionals .= " AND `deleted_at` IS NULL";

            // Build the query
            $query = $this->SELECT . $this->JOINS . $conditionals . $this->SORTS . $this->HAVING;

            if (!empty($this->LIMIT)) {
                $query .= " LIMIT {$this->LIMIT}";
            }

            // Are all open groupings closed?
            if (substr_count($query, '(') - substr_count($query, ')') > 0) die('Not all groupings have been closed!') ;
            
            // Change SELECT -> SELECT DISTINCT if flag is set
            if ($this->DISTINCT) $query = preg_replace("/^SELECT/", "SELECT DISTINCT", $query);

            return $query;
        }

        /**
         * keyBy Will key the results by a given fieldname
         * @param  string $key must be a valid fieldname returned in results
         * @return $this
         */
        public function keyBy($key) {
            // Future plans:
            //      Incorporate the helper boolean to check if the $key exists in results
            $this->KEYED_BY = $key;
            return $this;
        }

        /**
         * __debugInfo Specifies what is shown when dumping the object ( var_dump($builder) or print_r($builder) )
         * @return Array of what will be dumped
         */
        public function __debugInfo() {
            $debug = [
                'Full Query'    => $this->__toString(),
                'Select'        => $this->SELECT,
                'Conditionals'  => $this->CONDITIONALS,
                'Joins'         => $this->JOINS,
                'Sorts'         => $this->SORTS,
                'Table Fields'  => $this->FIELDS,
                'Primay Key'    => $this->PK,
                'Having'        => $this->HAVING,
                
            ];
            if ($this->HELP) {
                $debug['Database Connection'] = $this->DB_INFO;
            }
            return $debug;
        }

        /////////////////////
        // Private Methods //
        /////////////////////
        
        /**
         * is_table_set causes fatal error if the table has not been set yet
         * @return Void
         */
        private function is_table_set() {
             if (empty($this->TABLE_NAME)) {
                die('Table is not set!');
            }
        }

        /**
         * field_check Checks the fields given against the potential fields
         * @param  string|Array $values field name(s) to check
         * @return Void
         */
        private function field_check($values) {
            $this->is_table_set();

            // Only run this if requesting help
            if ($this->HELP) {
                // Single item
                if (is_string($values)) {
                    if (!in_array($values, $this->FIELDS) ) {
                        die(print_r($values, true) . " does not exist in table: {$this->TABLE_NAME}.");
                    }
                } elseif(is_array($values)) {
                    // Check if all the fields are valid
                    $field_check = array_diff($values, $this->FIELDS);
                    if ( !empty($field_check) ) {
                        die("These do not exist in {$this->TABLE_NAME}: " . implode(', ',$field_check));
                    }
                }
            }
        }

        /**
         * field_update Updates Ongoing field options on helper mode
         * @param  string $table_name         Name of the table being added to this instance
         * @return string|boolean             Primary Key name or false if none
         */
        private function field_update($table_name) {
             
             // default PK of table to false 
             $pk = false;

             // Help mode
             if ($this->HELP) {
                
                // Check if it's a valid table name 
                $query = 'SELECT DISTINCT(`TABLE_NAME`)
                            FROM information_schema.tables
                            WHERE table_schema = "jhproperties"';
                
                // Save a query since joins use this as well
                if (empty($this->TABLE_OPTIONS)) {

                    $this->TABLE_OPTIONS = array_column($this->DB->query($query), 'TABLE_NAME');
                }

                // Check if the table name provided exists in this DB
                if (!in_array($table_name, $this->TABLE_OPTIONS)) {
                    die("`$table_name` does not exist in this DB");
                }

                // Get Column Names and add to current fieldname set
                $query = "SHOW COLUMNS FROM `$table_name`;";

                foreach ($this->DB->getThem($query) as $key => $value) {
                    $this->FIELDS[$table_name . '.' . $value['Field']] = $value['Field'];
                    if ($value['Key'] == "PRI") {
                        $pk = $value['Field'];
                    }
                }
            
            //  Setting this Table
            } elseif(empty($this->FIELDS)) {
                // Get Column Names and add to current fieldname set
                $query = "SHOW COLUMNS FROM `$table_name`;";

                foreach ($this->DB->getThem($query) as $key => $value) {
                    $this->FIELDS[$table_name . '.' . $value['Field']] = $value['Field'];
                    if ($value['Key'] == "PRI") {
                        $pk = $value['Field'];
                    }
                }
            }
            return $pk;
        }

        /**
         * format_field Formats field name with acute accents (`)
         * @param  string $field_name unwarapped field name
         * @return string             wrapped field name
         */
        private function format_field($field_name){

            // MySQL Functions
            if (strpos($field_name, "(") !== false) {
                $function = preg_replace("/(.*?)\(.*/", "$1", $field_name);
                $value = preg_replace("/.*?\((.*?)\)/", "$1", $field_name);
                return $function . "(`" .str_replace('.', '`.`', $value) . "`)";
            
            // MySQL Field Names
            } else {
                return "`" .str_replace('.', '`.`', $field_name) . "`";
            }
            
        }

        ////////////////////
        // Public Methods //
        ////////////////////

        /**
         * table Set the primary table that's being querried
         * @param  string $table_name name of the table
         * @return $this
         */
        public function table($table_name='', $set_pk = false) {

            // Set Primary Key of this object to the PK of this table
            if ($set_pk) {
                $this->PK = $this->field_update($table_name);
            }
           
            // Set the Table Name
            $this->TABLE_NAME = $table_name;
            
            return $this;
        }

        /**
         * help... Helps you build querries!
         * @return $this
         */
        public function help() {
            $this->HELP = true;
            return $this;
        }

        /**
         * complexJoin handles the core logic of all table joins
         * @param  string $new_table      Table to join
         * @param  string $pk             Primary of of Table to join]
         * @param  string $existing_table Table already existing table
         * @param  string $fk             Foreign Key of an existing table
         * @param  string $type           INNER | RIGHT | LEFT
         * @param  string $operator       Almost always '=' so that's what is defaulted
         * @return $this
         */
        public function complexJoin($new_table, $pk, $existing_table, $fk, $type = 'INNER', $operator = '=') {

            // Assume they have the same colum name if FK isn't given
            if (is_null($fk)) $fk = $pk;

            // PK and FK are both arrays (or one is not given)
            if (is_array($pk) && is_array($fk)) die("Either the PK of the new table or FK of the existing table must be a unique string.");

            // Update the fieldlist if on helpermode
            $primary_check = $this->field_update($new_table);
            
            if ($this->HELP) {
                // Table doesn't have a PK
                if ($primary_check === false) die("`$table_name` has no Primary Key" );
                // PK provided doesn't match actual PK of table
                if ($primary_check != $pk) die("The Primary Key for $table_name is $primary_check");
            }
            
            // Matches multiple columns on existing table
            if (is_array($fk)) {
                $join = " $type JOIN `$new_table` ON ";
                foreach ($fk as $value) {
                    $join .= "`$new_table`.`$pk` " . strtoupper($operator) . " `$existing_table`.`$value` OR ";
                }
                $join = rtrim($join, ' OR ');
            // Matches multiple columns on existing table
            } elseif(is_array($pk)) {
                $join = " $type JOIN `$new_table` ON ";
                foreach ($pk as $value) {
                    $join .= "`$new_table`.`$value` " . strtoupper($operator) . " `$existing_table`.`$fk` OR ";
                }
                $join = rtrim($join, ' OR ');
            } else {
                // Update Ongoing Join
                 $join = " $type JOIN `$new_table` ON `$new_table`.`$pk` " . strtoupper($operator) . " `$existing_table`.`$fk`";
            }

            $this->JOINS .= $join;

            return $this;
        }

        // Needs to be upgraded...
        /**
         * rawHaving Adds a Having clause as is as well as an optional raw select
         * @param  string $raw       raw HAVING query
         * @param  string $rawSelect raw SELECT query
         * @return $this
         */
        public function rawHaving($raw, $rawSelect = null) {
            if (!is_null($rawSelect)) $this->rawSelect($rawSelect);
            $having = " HAVING ( $raw )";
            $this->HAVING = $having;
            return $this;
        }

        // 
        public function having(...$args) {

            switch (count($args)) {
                case 1: // parameter exists?
                    $query = " HAVING " . $this->format_field($args[0]) . " IS NOT NULL";
                    break;
                case 2: // parameters are assumed equal (Most common)
                    $query = " HAVING " . $this->format_field($args[0]) . " = '" . trim($args[1]) . "'";
                    break;
                case 3: 
                    // allows for other operators
                    // Array would be for a subquery / HAVING IN
                    $argument_three = is_array($args[2]) ? "('" . implode("','", $args[2]) . "')" : "'" . $args[2] . "'";
                    $query = " HAVING " . $this->format_field($args[0]) . " " .strtoupper(trim($args[1])) ." " . $argument_three;
                    break;
                default:
                    $query = ""; // beats me...
                    break;
            }

            // Is Having already set?
            if (!empty($this->HAVING)) {
                $query = str_replace("HAVING", "AND", $query);
            }
            $this->HAVING .= $query;
            return $this;
        }

        
        /** 
         * Joins below this are for easier access to joining things with a FK on the base table
         */

        // Easy call the Join function with a RIGHT join
        public function rightJoin($table_name,$pk, $fk = NULL, $operator = '=') {
            return $this->join($table_name, $pk, $fk, 'RIGHT', $operator);
        }

        // Easy call the Join function with a LEFT join
        public function leftJoin($table_name,$pk, $fk = NULL, $operator = '=') {
            return $this->join($table_name, $pk, $fk, 'LEFT', $operator);
        }

        // Calls Complex Join using the base table
        public function join($table_name, $pk, $fk = NULL, $type = 'INNER', $operator = '=') {
            return $this->complexJoin($table_name, $pk, $this->TABLE_NAME, $fk, $type, $operator);
        }

        /**
         * database Sets a different database than default
         * @param  Object $db Of type MySQLi
         * @return Void
         */
        public function database($db) {
            $this->DB = $db;
            return $this;
        }
        
        /**
         * get run the built query
         * @return Array|boolean    Array of results or TRUE on success
         */
        public function get($limit = null) {
            
            $results = $this->multiGet($limit);
            
            // DB object has different calls based on expected singular or muliple, 
            // Always request muliple and return first if singular (to match both call results)
            if ($this->num_rows == 1) {
                return new QueryResult($results[0]);
            } else {
                return $results;
            }
        }

        // Exactly like get() except it returns a multidemensional array of results 
        // even if only a single result or limit = 1
        public function multiGet($limit = null) {
            
            mysqli_report(MYSQLI_REPORT_OFF); //Turn off irritating default messages

            $this->is_table_set();

            $this->LIMIT = $limit;

            // Full Query
            $query = $this->__toString();

            // Run the Query and gather any results (if any)
            $results = $this->DB->query($query);

            // Check for any MySQLi Errors
            if ($this->DB->error) {
                try {
                    
                    // Code that may throw an Exception or Error.
                    throw new \Exception("MySQL error {$this->DB->error}: <br><br> Query:<br> $query", $this->DB->errno);

                } catch (\Throwable $t) {
                    
                    // Executed only in PHP 7, will not match in PHP 5
                    http_response_code(400);
                    echo "<br>Error No: ".$t->getCode(). "<br>". $t->getMessage() . "<br><br>";
                    die(nl2br($t->getTraceAsString()));

                } catch (\Exception $e) {
                    
                    // Executed only in PHP 5, will not be reached in PHP 7
                    http_response_code(400);
                    echo "Error No: ".$e->getCode(). " - ". $e->getMessage() . "<br >";
                    die(nl2br($e->getTraceAsString()));
                }
            }

            // Result Based on DML Type
            switch ($this->QUERY_TYPE) {
                case 'insert':
                    // Handled in insert()
                    break;
                case 'update';
                case 'delete';
                    $this->num_rows = $this->DB->affected_rows;
                    $return = $this->num_rows;
                    break;
                default: // select
                    $return = [];
                    $this->num_rows = $results->num_rows;
                    if ($this->num_rows > 0) {
                        if (!is_null($this->KEYED_BY)) {
                            while($row = $results->fetch_assoc()) $return[$row[$this->KEYED_BY]] = $row;
                        } else {
                            while($row = $results->fetch_assoc()) $return[] = $row;
                        }
                    }
                    break;
            }            
            
            return new QueryResult($return);
        }

        /**
         * startGroup Starts a grouping
         * @return Void Query builds after this will be grouped until closed
         */
        public function startGroup() {
            $this->GROUP_START = 1;
            return $this;
        }

        /**
         * endGroup Ends grouping
         * @return Void
         */
        public function endGroup() {
            $this->CONDITIONALS .= ")";
            return $this;
        }

        /**
         * rawSelect When you need aggrigates, use this to add them
         * @param  string $value Raw SELECT statment
         * @return Void        Updates the SELECT
         */
        public function rawSelect($value='') {
            if (!empty($this->SELECT)) {
                $temp = ltrim($this->SELECT, 'SELECT ');
                $this->SELECT = "SELECT $value, $temp";
            } else {
                $this->SELECT = "$value FROM `{$this->TABLE_NAME}`";
            }
            $this->IS_UPDATE = false;
            return $this;
        }

        /**
         * select Specifies fiels to return, defaults to all
         * @param  string|Array $fields fieldnames you want returned in your results
         * @return Void
         */
        public function select($fields = ''){

            // Table needs to be set for field comparison
            $this->is_table_set();
               
            $select = "";

            // Default to select all
            if (empty($fields)) {
                $select = "*";
            
            // Array of fields passed, Select Only those
            } elseif (is_array($fields)) {
                foreach ($fields as $field) {
                  $select .=  $this->format_field($field) . ',';
                }
                $select = rtrim($select, ",");
            // Single field requested
            } elseif(is_string($fields)){
                $select = $this->format_field($fields);
            }

            $this->SELECT = "SELECT $select FROM `{$this->TABLE_NAME}`";
            $this->IS_UPDATE = false;
            return $this;
        }

        public function distinct() {
            $this->DISTINCT = true;
            return $this;
        }

        public function groupBy($fields) {
            $group = " GROUP BY ";
            if(is_string($fields)){
                $group .= $this->format_field($fields);
            } else {
                foreach ($fields as $field) {
                  $group .=  $this->format_field($field) . ',';
                }
                $group = rtrim($group, ",");
            }
            $this->SORTS .= $group;

            return $this;
        }

        public function complexWhere($operator, $arg_array) {

            // Closest thing to overloading in PHP
            switch (count($arg_array)) {
                case 1: // 1 parameter exists?
                    $query = " WHERE " . $this->format_field($arg_array[0]) . " IS NOT NULL";
                    break;
                case 2: // 2 parameters are assumed equal (Most common)
                    $query = " WHERE " . $this->format_field($arg_array[0]) . " = '" . trim($arg_array[1]) . "'";
                    break;
                case 3: // 3 parameters passed: 1 = fieldname, 2 = Operator, 3 = See below
                
                    // Array would be for WHERE IN / NOT IN
                    if ( is_array($arg_array[2]) ) {
                        $argument_three = "('" . implode("','", $arg_array[2]) . "')";
                    
                    // Can take other Query Builders as sub querries
                    } elseif ($arg_array[2] instanceof $this) {
                        $argument_three = "(" . $arg_array[2]->subquery() . ")";

                    // Field name
                    } else {
                        $argument_three = "'" . $arg_array[2] . "'";
                    }

                    $query = " WHERE " . $this->format_field($arg_array[0]) . " " . strtoupper(trim($arg_array[1])) ." " . $argument_three;
                    break;
                default: // 4 = ???
                    return $this;
                    break;
            }

            // Is WHERE already set?
            if (!empty($this->CONDITIONALS)) {
                $query = preg_replace("/WHERE/", $operator, $query, 1); // Swap only the first WHERE for (AND | OR)
            }

            // Starting a group?
            if ($this->GROUP_START) {
                // First WHERE Grouping
                if (strpos($query, 'WHERE') !== false) {
                    $query = str_replace("WHERE ", "WHERE (", $query); // Start WHERE Grouping
                // ANDing
                } else {
                    $query = preg_replace("/$operator /", "$operator (", $query); // Start AND Grouping
                }
                $this->GROUP_START = false;
            }
            
            // Add to ongoing query     
            $this->CONDITIONALS .= $query;

            return $this;
        }
        /**
         * where easily set & compound conditional statments
         * @return $this
         */
        public function where(...$args) {
            // Allow for multiple where clauses in the form of a multi dimentensional array to be passed
            // Ex:  
            //  [
            //      ['cats', 1],
            //      ['dogs', 3],
            //      ['lions']
            //  ]
            //  would produce:
            //      WHERE `cats` = 1 AND `dogs` = 3 AND `lions` IS NOT NULL
            //  
            if (count($args) == 1 && is_array($args[0])) {
                foreach ($args[0] as $args_set) {
                    $this->complexWhere('AND', $args_set);
                }
                return $this;
            } else {
                return $this->complexWhere('AND', $args);
            }
        }

        /**
         * rawWhere WHERE & ADD conditionals without the checks
         * @param  string $raw Query without AND or WHERE
         * @return $this
         */
        public function rawWhere($raw) {

            $query = " WHERE ";

            // Is WHERE already set?
            if (!empty($this->CONDITIONALS)) {
                $query = str_replace("WHERE", "AND", $query); // Swap the WHERE for AND         
            }   
            $query .= $raw;

            // Add to ongoing query     
            $this->CONDITIONALS .= $query;
            return $this;
        }

        /**
         * whereIn Shorthand for where("fieldname", "IN", values or subquery)
         * @param  string $field        fieldname
         * @param  Array|Query_Builder  $values values for fieldname to match
         * @return $this
         */
        public function whereIn($field, $values) {
            
            if (!is_array($values) || !($values instanceof $this)) {
                if ($this->HELP) trigger_error('whereIn($field, $values): Second parameter must be an array of at least 1 item or a subquery!', E_USER_NOTICE);
            }
            return $this->where($field, 'IN', $values);
        }

        /**
         * whereNotIn Shorthand for where("fieldname", "NOT IN", values or subquery)
         * @param  string               $field  fieldname
         * @param  Array|Query_Builder  $values values for fieldname to NOT match
         * @return $this
         */
        public function whereNotIn($field, $values) {

            // Show warnings if asking for help
             if (!is_array($values) || !($values instanceof $this)) {
                // Let them know what's wrong if they're asking
                if ($this->HELP) trigger_error('whereNotIn($field, $values): Second parameter must be an array of at least 1 item or a subquery!', E_USER_NOTICE);
            }
            return $this->where($field, 'NOT IN', $values);
        }

        /**
         * whereNot It's like where... but it's NOT
         * @param  string                       $field fieldname
         * @param  string|Array|Query_Builder   $value values NOT to match
         * @return $this
         */
        public function whereNot($field, $value = null) {
            
            // IS NULL
            if (is_null($value)) return $this->rawWhere($this->format_field($field) . ' IS NULL');
            
            // Where Not In
            if ((is_array($value) || ($value instanceof $this)) && !empty($value)){ 
                return $this->whereNotIn($field, $value);
            }
            
            // Everything else
            return $this->where($field, '<>', $value);
        }

        /**
         * orWhere Everything you love about where now in an OR flavor!
         * @return Void    Updates the ongoing conditionals
         */
        public function orWhere(...$args) {
            if (count($args) == 1 && is_array($args[0])) {
                foreach ($args[0] as $args_set) {
                    $this->complexWhere('OR', $args_set);
                }
                return $this;
            } else {
                return $this->complexWhere('OR', $args);
            }
        }

        /**
         * orWhereIn Shorthand for orWhere($field, 'IN', $values)
         * @param  string               $field  [description]
         * @param  Array|Query_Builder  $values [description]
         * @return $this
         */
        public function orWhereIn($field, $values) {
            // Show warnings if asking for help
             if (!is_array($values) || !($values instanceof $this)) {
                // Let them know what's wrong if they're asking
                if ($this->HELP) trigger_error('orWhereIn($field, $values): Second parameter must be an array of at least 1 item or a subquery!', E_USER_NOTICE);
            }

            return $this->orWhere($field, 'IN', $values);
        }

        /**
         * orderBy sorts results by ASC or DESC value of given field(s).
         * @param  string|Array $fields     one or many field names
         * @param  string $direction        Ascending or Descending (single field)
         * @return Void
         */
        public function orderBy($fields, $direction = 'DESC') {

            //  If helper enabled, check field names against the ones given

            $direction = strtoupper($direction);
            if (!in_array($direction, ['ASC', 'DESC'])) die('Please use a valid sorting direction (ASC / DESC)');
            
            $order = empty($this->SORTS) ? " ORDER BY " : ", ";

            if (is_string($fields)) {
                $order .= $this->format_field($fields) . " " . $direction;
            } elseif(is_array($fields)) {
                foreach ($fields as $field => $direction) {
                  $order .=  $this->format_field($field) . ' ' . strtoupper($direction) . ', ';
                }
                $order = rtrim($order, ", ");
            }

            $this->SORTS .= "$order";
            return $this;
        }

        /**
         * find limits query for a single primary id
         * @param  int $id Primary key value to lookup
         * @return $this
         */
        public function find($id) {
            $this->is_table_set();
            if (empty($this->PK)) {
                die("{$this->TABLE_NAME} does not contain a primary key!");
            }

            return $this->where("{$this->TABLE_NAME}.{$this->PK}", $id);
        }


        /**
         * pluck Returns a select all for a single primary id
         * @param  int $id Primary key to lookup
         * @return Array     Results of the query
         */
        public function pluck($id) {
            $this->is_table_set();
            if (empty($this->PK)) {
                $this->table($this->TABLE_NAME, true);
            }
            return $this->find($id)->get();
        }

        /**
         * withTrashed Soft Deleted items are no longer excluded from the query
         * @return Void 
         */
        public function withTrashed() {
            $this->SHOW_DELETED = true;
            return $this;
        }

        /**
         * update description
         * @param  Array $to_update Field Name to new value pairs
         * @return Void            Updates the SELECT to an UPDATE
         */
        public function update($to_update) {

            $this->QUERY_TYPE = 'update';

            // Update current model
            $updates = "UPDATE `{$this->TABLE_NAME}` SET ";

            // With these values
            foreach ($to_update as $field => $value){
                if (is_null($value)) {
                    $updates .= "`$field` = NULL,";
                } else {
                    $updates .= "`$field` = '$value',";
                }
            }
            
            $this->SELECT = rtrim($updates, ',');
            $this->IS_UPDATE = true;
            return $this;
        }

        /**
         * Insert a new record in the form of an associateive array
         * @param  array $record    Associative array of [fieldnames => values];
         * @param  array $entries   Used with multiple inserts.
         * @return int|boolean      Primary Key of new record (if single insert), boolean insert success for multi
         */
        public function insert($record, $entries = null) {

            $this->QUERY_TYPE = 'insert';

            // Single Record
            if (is_null($entries)) {
                $keys =  "(`" . implode("`, `", array_keys($record)) . "`)";
                $values = "('" . implode("','", array_values($record)) . "')";    
                $this->SELECT = "INSERT INTO `{$this->TABLE_NAME}` $keys VALUES $values";
                return $this->DB->query($this->SELECT) === TRUE ? $this->insert_id : FALSE;
                
            // Multiple Records
            } else {
                $keys =  "(`" . implode("`, `", array_values($record)) . "`)";
                $values = "";
                foreach ($entries as $value) {
                    $values .= "('" . implode("','", array_values($value)) . "'),";
                }
                $values = rtrim($values, ',');
                $this->SELECT = "INSERT INTO `{$this->TABLE_NAME}` $keys VALUES $values";    
                return $this->DB->multi_query($this->SELECT) === TRUE;
            }
        }

        /**
         * Delete Query - Can be combined with wheres & joins
         * @param  string|Array $tables Additional tables names(s) to delete from. Default = only parent table
         * @return Null
         */
        public function delete($tables = null){
            
            // Soft delete
            if (isset($this->FIELDS[$this->TABLE_NAME . '.deleted_at'])) {
                $this->update(['deleted_at', date("Y-m-d H:i:s")]);
            
            // Actual delete
            } else { 

                // Uses Join(s)
                if (!empty($this->JOINS)) {
                    
                    // Default table to apply deletes
                    $delete = "DELETE `{$this->TABLE_NAME}`";
                    
                    // Extra Tables Supplied
                    if (!is_null($tables)) {
                        
                        // Single table
                        if (is_string($tables)) {
                            $delete .= " , `$tables`";
                        
                        // Multiple additional tables
                        } elseif (is_array($tables)) {
                            foreach ($tables as $value) $delete .= " , `$value`";
                        }
                    }
                    $this->SELECT = "$delete FROM `{$this->TABLE_NAME}`";    

                // No Joins
                } else {
                    $this->SELECT = "DELETE FROM `{$this->TABLE_NAME}`";
                }
            }
            $this->QUERY_TYPE = 'delete';
            return $this;        
        }
    }
?>