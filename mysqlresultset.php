<?php
/**
 *  MySQL Result Set
 *
 *  Class definition for the {@link MySqlResultSet} object.
 *
 *  @package    mysql-database
 */

/**
 *  MySQL Result Set
 *
 *  An iteratable object representing the result set from a MySQL SELECT query.
 *  The Iterator interface allows the object to be iterated over by PHP in a 
 *  foreach loop as objects or arrays representing each row of data.
 *
 *  For most applications you will not need to call any of this object's methods
 *  directly. Instead, it is typically obtained from {@link MySqlDatabase::iterate()}
 *  and iterated over using a foreach loop:
 *
 *  <code>
 *  $db = MySqlDatabase::getInstance();
 *  $db->connect('localhost', 'user', 'password', 'database_name');
 *
 *  // $db->iterate() returns a new MySqlResultSet instance
 *  foreach ($db->iterate("SELECT * FROM users LIMIT 100") as $row) {
 *      print_r($row);
 *  }
 *  </code>
 *
 *  @package    mysql-database
 *  @author     Micah Carrick
 *  @copyright  (c) 2010 - Micah Carrick
 *  @version    2.0
 *  @license    BSD
 */
class MySqlResultSet implements Iterator
{
    private $query;
    private $result;
    private $index = 0;
    private $num_rows = 0;
    private $row = false;
    private $type;

    /**
     *  Object Data
     *  
     *  The data will be fetched as an object, where the columns of the table
     *  are property naems of the object. See 
     *  {@link mysql_fetch_object()}.
     */
    const DATA_OBJECT = 1;
    
    /**
     *  Numeric Array Data
     *  
     *  The data will be fetched as a numerically indexed array. See
     *  {@link mysql_fetch_row()}.
     */
    const DATA_NUMERIC_ARRAY = 2;
    
    /**
     *  Keyed Array Data
     *  
     *  The data will be fetched as an associative array. See
     *  {@link mysql_fetch_assoc()}.
     */
    const DATA_ASSOCIATIVE_ARRAY = 3;
    
    /**
     *  Array Data
     *  
     *  The data will be fetched as both an associative and indexed array. See
     *  {@link mysql_fetch_array()}.
     */
    const DATA_ARRAY = 4;
    
    /**
     *  Constructor
     *  
     *  The constructor requires an SQL query which should be a query that 
     *  returns a MySQL result resource such as a SELECT query. If the query
     *  fails or does not return a result resource, the constructor will throw
     *  an exception.
     *
     *  The optional $data_type parameter specifies how to fetch the data. One 
     *  of the data constants can be specified or the default 
     *  {@link MySqlResultSet::DATA_OBJECT} will be used.
     *
     *  @param  string
     *  @param  integer
     */
    public function __construct($query, $data_type=MySqlResultSet::DATA_OBJECT, 
                                $link=false) 
    {
        if ($link) $this->result = @mysql_query($query, $link);
        else $this->result = @mysql_query($query);

        if (!$this->result) {
            throw new Exception(mysql_error());
        }
        
        if (!is_resource($this->result) 
            || get_resource_type($this->result) != 'mysql result') {
            throw new Exception("Query does not return an mysql result resource.");
        }
        
        $this->query = $query;
        $this->num_rows = mysql_num_rows($this->result);
        $this->type = $data_type;
    }
    
    /**
     *  Destructor
     *  
     *  The destructor will free the MySQL result resource if it is valid.
     */
    public function __destruct()
    {
        if (is_resource($this->result) 
            && get_resource_type($this->result) == 'mysql result') {
            mysql_free_result($this->result);   
        }
    }
    
    private function fetch()
    {
        if ($this->num_rows > 0) {
            switch ($this->type) {
                case MySqlResultSet::DATA_NUMERIC_ARRAY: 
                    $func = 'mysql_fetch_row';
                    break;
                case MySqlResultSet::DATA_ASSOCIATIVE_ARRAY: 
                    $func = 'mysql_fetch_assoc';
                    break;
                case MySqlResultSet::DATA_ARRAY: 
                    $func = 'mysql_fetch_array';
                default: 
                    $func = 'mysql_fetch_object';
                    break;
            }
            
            $this->row = $func($this->result);
            $this->index++;
        }
    }
    
    public function getResultResource()
    {
        return $this->result;
    }
    
    public function isEmpty()
    {
        if ($this->num_rows == 0) return true;
        else return false;
    }
    
    /**
     *  Rewind
     *  
     *  Rewind the Iterator to the first row of data.
     */
    public function rewind() 
    {
        if ($this->num_rows > 0) {
            mysql_data_seek($this->result, 0);
            $this->index = -1;  // fetch() will increment to 0
            $this->fetch();
        }
    }
    
    /**
     *  Current Row
     *  
     *  Get the current row of data. The type of data is determined by the $type
     *  parameter passed to the constructor.
     *
     *  @return mixed
     */
    function current() 
    {
        return $this->row;
    }
    
    /**
     *  Key
     *  
     *  Get the index for the current row. The index begins at 0 with the first
     *  row of data.
     *
     *  @return integer
     */
    function key() 
    {
        return $this->index;
    }
    
    /**
     *  Next
     *  
     *  Move forward to the next row in the result set.
     */
    function next() 
    {
        $this->fetch();
    }
    
    /**
     *  Valid
     *  
     *  Determines if the current row is valid.
     *
     *  @return boolean
     */
    function valid() 
    {
       if ($this->row === false) return false;
       else return true;
    }
}
?>
