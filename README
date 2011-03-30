PHP MySQL Database Class
========================

Copyright (c) 2011 Micah Carrick
All Rights Reserved.

PHP MySQL Database Class is singleton pattern object which serves as a MySQL 
database wrapper and an iterator result set object. This project was developed
as a follow up to my older, PHP4 database class from 2004.


Basic Usage
-----------
For a more detailed explanation, consult the phpDocumentor docstrings within
the source code or visit http://www.micahcarrick.com/php5-mysql-database-class.html

Here are some simple examples:

// get the database singleton instance
$db = MySqlDatabase::getInstance();

// connect
try {
    $db->connect('localhost', 'username', 'password', 'database_name');
} 
catch (Exception $e) {
    die($e->getMessage());
}

// iterate a resultset
foreach ($db->iterate("SELECT foo FROM bar LIMIT 10") as $row) {
    echo $row->foo;
}

// get just one row
$count = $db->fetchOne("SELECT COUNT(*) FROM foo");

// import from a file (use with caution!)
$num = $db->importSqlFile('test-data.sql');
echo "Imported <strong>$num</strong> statements.<br/>";
