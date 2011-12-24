<?php
/**
 *  @package    mysql-database
 */

/**
 *  MySQL Database
 *
 *  A singleton object which provides convenience methods for interfacing with
 *  a MySQL database in PHP 5. You can get the object's instance using the 
 *  static {@link getInstance()} method. Being a singleton object, this class
 *  only supports one open database connection at a time and idealy suited to
 *  single-threaded applications. You can read 
 *  about {@link http://php.net/manual/en/language.oop5.patterns.php the singleton 
 *  pattern in the PHP manual}.
 *  
 *  <b>Getting Started</b>
 *  <code>
 *  $db = MySqlDatabase::getInstance();
 *  
 *  try {
 *      $db->connect('localhost', 'user', 'password', 'database_name');
 *  } 
 *  catch (Exception $e) {
 *      die($e->getMessage());
 *  }
 *  </code>
 *
 *  @package    mysql-database
 *  @author     Micah Carrick
 *  @copyright  (c) 2010 - Micah Carrick
 *  @version    2.0
 *  @license 	BSD
 */
class MySqlDatabase
{
    /**
     *  The MySQL link identifier created by {@link connect()}
     *
     *  @var resource
     */
    public $link;
    
    /**
     *  @var string
     */
    private $conn_str;
    
    /**
     *  @var MySqlDatabase
     */
    private static $instance;
    
    const MYSQL_DATE_FORMAT = 'Y-m-d';
    const MYSQL_TIME_FORMAT = 'H:i:s';
    const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';
    
    const INSERT_GET_AUTO_INCREMENT_ID = 1;
    const INSERT_GET_AFFECTED_ROWS = 2;
    
    /**
     *  Constructor
     *
     *  Private constructor as part of the singleton pattern implementation.
     */
    private function __construct() {}

    /**
     *  Connect
     *
     *  Establish a connection to a MySQL database. Returns the MySQL link 
     *  link identifier or throws an exception if there is an error.
     *
     *  <code>
     *  // get an instance of the Database singleton
     *  $db = MySqlDatabase::getInstance();
     *
     *  // connect to a MySQL database (use your own login information)
     *  try {
     *      $db->connect('localhost', 'user', 'password', 'database_name');
     *  } 
     *  catch (Exception $e) {
     *      die($e->getMessage());
     *  }
     *  </code>
     *
     *  @param  string
     *  @param  string
     *  @param  string
     *  @param  string
     *  @param  boolean
     *  @return resource
     */
    public function connect($host, $user, $password, $database=false, $persistant=false)
    {
        if ($persistant) {
            $this->link = @mysql_pconnect($host, $user, $password);
        } else {
            $this->link = @mysql_connect($host, $user, $password);
        }
        
        if (!$this->link) 
        {
            throw new Exception('Unable to establish database connection: ' 
                                .mysql_error());
        }

        if ($database) $this->useDatabase($database);
        
        $version = mysql_get_server_info();
        $this->conn_str = "'$database' on '$user@$host' (MySQL $version)";
        
        return $this->link;
    }
    
    /**
     *  Delete
     *
     *  Executes the DELETE statement specified in the query and returns the
     *  value from either the PHP {@link mysql_affected_rows()} function. Throws
     *  and exception if there is a MySQL error in the query.
     *
     *  Note: With MySQL versions prior to 4.1.2, the affected rows on DELETE
     *  statements with no WHERE clause is 0. See {@link mysql_affected_rows()}
     *  for more information.
     *
     *  @param  string
     *  @return integer
     */
    public function delete($query) 
    {
        return $this->updateOrDelete($query);
    }
    
    /**
     *  Get Connection String
     *
     *  Gets a string representing the connection.
     *  
     *  <code>
     *  echo $db->getConnectionString();
     *  // example output: 'test_database' on 'web_user@localhost' (MySQL 5.1.47)
     *  </code>
     *
     *  @return string
     */
    public function getConnectionString() 
    {
        return $this->conn_str;
    }
    
    /**
     *  Get Instance
     *
     *  Gets the singleton instance for this object. This method should be called
     *  statically in order to use the Database object:
     *
     *  <code>
     *  $db = MySqlDatabase::getInstance();
     *  </code>
     *
     *  @return MySqlDatabase
     */
    public static function getInstance()
    {
        if (!isset(self::$instance))
        {
            self::$instance = new MySqlDatabase();
        }
        
        return self::$instance;
    }
    
    /**
     *  Fetch One From Each Row
     *  
     *  Convenience method to get a single value from every row in a given
     *  query. This is usefull in situations where you know that the result will
     *  only have only one column of data and you need that all in a simple 
     *  array.
     *
     *  <code>
     *  
     *  $query = "SELECT name FROM users";
     *  $names = $db->fetchOneFromEachRow($query);
     *  echo 'Users: ' . implode(', ', $names);
     *  </code>
     *
     *  @param  string
     *  @return array
     */
    public function fetchOneFromEachRow($query)
    {
        $rval = array();
        
        foreach ($this->iterate($query, MySqlResultSet::DATA_NUMERIC_ARRAY) as $row) {
            $rval[] = $row[0];
        }

        return $rval;
    }
    
    /**
     *  Fetch One Row
     *  
     *  Convenience method to get a single row from a given query. This is 
     *  usefull in situations where you know that the result will only contain
     *  one record and therefore do not need to iterate over it. 
     *
     *  You can 
     *  optionally  specify the type of data to be returned (object or array) 
     *  using one of the MySqlResultSet Data Constants. The default is 
     *  {@link MySqlResultSet::DATA_OBJECT}.
     *
     *  <code>
     *  // get one row of data
     *  $query = "SELECT first, last FROM users WHERE user_id = 24 LIMIT 1";
     *  $row = $db->fetchOneRow($query);
     *  echo $row->foo;
     *  echo $row->bar; 
     *  </code>
     *
     *  @param  string
     *  @param  integer
     *  @return mixed
     */
    public function fetchOneRow($query, $data_type=MySqlResultSet::DATA_OBJECT)
    {
        $result = new MySqlResultSet($query, $data_type, $this->link);
        $result->rewind();
        $row = $result->current();

        return $row;
    }
    
    /**
     *  Fetch One
     *  
     *  Convenience method to get a single value from a single row. Returns the
     *  value if the query returned a record, false if there were no results, or
     *  throws an exception if there was an error with the query.
     *
     *  <code>
     *  // get the number of records in the 'users' table
     *  $count = $db->fetchOne("SELECT COUNT(*) FROM users");
     *  </code>
     *
     *  @param  string
     *  @return mixed
     */
    public function fetchOne($query)
    {
        $result = new MySqlResultSet($query, MySqlResultSet::DATA_NUMERIC_ARRAY, 
                                     $this->link);
        $result->rewind();
        $row = $result->current();

        if (!$row) return false;
        else return $row[0];
    }
    
    /**
     *  Import SQL File
     *
     *  Runs the queries defined in an SQL script file. The double-hyphen style 
     *  comments must have a single space after the hyphens. Hash style comments
     *  and C-style comments are also supported.
     *
     *  An optional user callback function can be specified to get information 
     *  about each MySQL statement. The user callback function takes 3
     *  parameters: the line number as an integer, the query as a string, and the 
     *  result of the query as a boolean.
     *
     *  <code>
     *  function import_sql_callback($line_number, $sql_query, $result) 
     *  {
     *      echo "Line $line_number: $sql_query ";
     *      if ($result) echo "(OK)<br/>";
     *      else echo "(FAIL)<br/>";    
     *  }
     *  </code>
     *
     *  You can optionally specify whether or not to abort importing statements 
     *  when an SQL error occurs (defaults to 'true') in which case an exception
     *  will be thrown for any MySQL error.
     *
     *  Returns the number of queries executed from the script or throws an
     *  exception if there is an error.
     *
     *  <code>
     *  // no callback, throw exception on MySQL errors
     *  $number = $db->importSqlFile('queries.sql');
     *
     *  // callback for each query, skip queries with MySQL errors
     *  $number = $db->importSqlFile('queries.sql', 'import_sql_callback', false);
     *  </code>
     *
     *  TODO: Ensure this works with huge files. Might need to use fopen()
     *
     *  @param  string
     *  @param  string
     *  @param  boolean
     *  @return integer
     */
    public function importSqlFile($filename, $callback=false, $abort_on_error=true)
    {
        if ($callback && !is_callable($callback)) {
            throw new Exception("Invalid callback function.");
        }

        $lines = $this->loadFile($filename);
        
        $num_queries = 0;
        $sql_line = 0;
        $sql = '';
        $in_comment = false;
        
        foreach ($lines as $num => $line) {
            
            $line = trim($line);
            $num++;
            if (empty($sql)) $sql_line = $num;
            
            // ignore comments
            
            if ($in_comment) {
                $comment = strpos($line, '*/');
                
                if ($comment !== false) {
                    $in_comment = false;
                    $line = substr($line, $comment+2);
                } else {
                    continue;
                }
                
            } else {
                
                $comment = strpos($line, '/*');
                
                if ($comment !== false) {
                    
                    if (strpos($line, '*/') === false) {
                        $in_comment = true;
                    }
                    
                    $line = substr($line, 0, $comment);
                    
                } else {
                
                    // single line comments
                    
                    foreach (array('-- ', '#') as $chars) {
                        $comment = strpos($line, $chars);
                        
                        if ($comment !== false) {
                            $line = substr($line, 0, $comment);
                        }
                    }
                }
            }

            // check if the statement is ready to be queried
            
            $end = strpos($line, ';');
            
            if ($end === false) {
                $sql .= $line;
            } else {
                $sql .= substr($line, 0, $end);
                $result = $this->quickQuery($sql);
                $num_queries++;
                
                if (!$result && $abort_on_error) {
                    $file = basename($filename);
                    $error = mysql_error($this->link);
                    throw new Exception("Error in $file on line $sql_line: $error");
                }
                
                if ($callback) {
                    call_user_func($callback, $sql_line, $sql, $result);
                }
                
                $sql = '';  // clear for next statement
                
            }
        }
        
        return $num_queries;
    }
    
    /**
     *  Is Connected
     *
     *  Determines if there is a connection open to the database.
     *
     *  @return boolean
     */
    public function isConnected()
    {
        if (!empty($this->link)) {
            return @mysql_ping($this->link);
        } else {
            return false;
        }
    }
    
    // insertPhpArray
    // insertSqlArray
    // sqlval()

    /**
     *  Insert
     *
     *  Executes the INSERT statement specified in the query and returns the
     *  value from either the PHP {@link mysql_insert_id()} function or the
     *  php {@link mysql_affected_rows()} function depending on the value of the
     *  $return_type parameter.
     *
     *  <code>
     *  $db = MySqlDatabase::getInstance();
     *  $query = "INSERT INTO foobar (col1, col2) VALUES (1, 2), (2, 3)";
     *  $rows = $db->insert($query, MySqlDatabase::INSERT_GET_AFFECTED_ROWS);
     *  echo $rows; // output: 2
     *  </code>
     *
     *
     *  @param  string
     *  @param  integer
     *  @return integer
     */
    public function insert($query, $r_type=MySqlDatabase::INSERT_GET_AUTO_INCREMENT_ID) 
    {
        $r = $this->query($query);
        
        if ($r_type == MySqlDatabase::INSERT_GET_AFFECTED_ROWS) {
            return @mysql_affected_rows($this->link);
        } else {
            return @mysql_insert_id($this->link);
        }
    }
    
    /**
     *  DO NOT USE
     *
     *  This was never finished... I don't think. The goal was to take a table
     *  name, an array of column names, and an array of values and generate a
     *  multiple record insert. You should not use this, but, you could help
     *  out and finish or rewrite this method.
     *
     *
     *  @param  deprecated
     */
    public function smartInsert($table, $columns, $values)
    {
        if (empty($table) || !is_string($table)) {
            throw new Exception('The $table parameter must be specified as a string.');
        }
        
        $table_sql = '`' . @mysql_real_escape_string($table) . '`';
        $query = "INSERT INTO $table_sql ";
        
        // columns
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }
        
        if (is_array($columns)) {
            foreach ($columns as &$col) {
                if (!is_string($col)) {
                    throw new Exception('The $columns parameter must be a string or an array of strings');
                }
                $col = @mysql_real_escape_string($col);
            }
            $column_sql = implode(',', $columns);
            $column_count = count($columns);
        } else {
            throw new Exception('The $columns parameter must be a string or an array of strings.');
        }
        
        try {
            $column_info = array();
            
            foreach ($this->iterate("SHOW COLUMNS FROM $table_sql") as $row) {
                $column_info[] = $row;
            }
        } 
        catch (Exception $e) {
            throw new Exception("Could not get column information for table $table_sql.");
        }
        
        $query .= "($column_sql) ";
        
        // values
        
        if (is_array($values)) {
            for ($i=0; $i < count($values); $i++) {
                $info = $column_info[$i];
                $value = $values[i];
                
                // Where the heck did I leave off?
            }
        } else {
            // TODO: if only 1 column, then this will work

            throw new Exception('The $values parameter must be a string or an array.');
        }
        
        if (isset($column_count) && $column_count <> $value_count) {
            throw new Exception("Column count ($column_count) does not match values count ($value_count).");
        }
        
        $query .= "VALUES ($value_sql) ";

        echo $query;
        
    }
    
    /**
     *  Iterate Result Set
     *
     *  Returns a {@link MySQL_ResultSet} iteratable object for a query. The $type 
     *  parameter indicates the data being iterated should be an object,
     *  a numerically indexed array, an associative array, or an array with
     *  both numeric and associative indexes. Defaults to objects.
     *
     *  <code>
     *  $sql_query = "SELECT col1, col2 FROM table";
     *
     *  // iterate as objects
     *  foreach ($db->iterate("SELECT col1, col2 FROM table") as $row) {
     *      echo $row->col1 . '<br/>';
     *      echo $row->col2 . '<br/>';
     *  }
     *
     *  // iterate as both associative and numerically indexed array
     *  foreach ($db->iterate($sql_query, MySQL_Db::DATA_ARRAY) as $row) {
     *      echo $row[0] . '<br/>';
     *      echo $row['col1'] . '<br/>';
     *  }
     *  </code>
     *
     *  @param  string
     *  @param  integer
     *  @return boolean
     */
    public function iterate($sql, $data_type=MySqlResultSet::DATA_OBJECT) 
    {
        return new MySqlResultSet($sql, $data_type, $this->link);
    }
    
    /**
     *  Load File
     *  
     *  Loads the specified filename into an array of lines. Throws an exception
     *  if there is an error.
     *
     *  @param  string
     *  @return boolean
     */
    private function loadFile($filename)
    {
        if (!file_exists($filename)) {
            throw new Exception("File does not exist: $filename");
        }
        
        $file = @file($filename, FILE_IGNORE_NEW_LINES);
        
        if (!$file) {
            throw new Exception("Could not open $filename");
        }
        
        return $file;
    }

    public function query($query) 
    {
        $r = @mysql_query($query, $this->link);

        if (!$r) {
            throw new Exception("Query Error: " . mysql_error());
        }
        
        return $r;
    }
    
    /**
     *  Quick Query
     *  
     *  Executes a MySQL query and returns a boolean value indicating success
     *  or failure. This method will close any resources opened from
     *  SELECT, SHOW, DESCRIBE, or EXPLAIN statements and would not be very
     *  usefull for those types of queries. This method is used internally for 
     *  importing SQL scripts.
     *
     *  @param  string
     *  @return boolean
     */
    public function quickQuery($query)
    {
        $r = @mysql_query($query, $this->link);
        
        if (!$r) return false;
        if (is_resource($r)) mysql_free_result($r);

        return true;
    }
    
    /**
     *  Update
     *
     *  Executes the UPDATE statement specified in the query and returns the
     *  value from either the PHP {@link mysql_affected_rows()} function. Throws
     *  and exception if there is a MySQL error in the query.
     *
     *  Note: The number of rows affected include only those in which the new
     *  value was not the same as the old value. See {@link mysql_affected_rows()}
     *  for more information.
     *
     *  @param  string
     *  @return integer
     */
    public function update($query) 
    {
        return $this->updateOrDelete($query);
    }
    
    private function updateOrDelete($query)
    {
        $r = $this->query($query);
        return @mysql_affected_rows($this->link);
    }
    
    /**
     *  Use Database
     *
     *  Selects the database to use. Throws an exception if there is an error
     *  using the specified database.
     *
     *  @param  string
     *  @return integer
     */
    public function useDatabase($database) 
    {
        if (!@mysql_select_db($database, $this->link))
        {
            throw new Exception('Unable to select database: ' . mysql_error($this->link));
        }
    }
}



?>
