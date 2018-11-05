# Query_Builder
Abstraction Layer for Building Queries in PHP.

## Instructions
1. Download Query_Builder.php and Query_Result.php
2. Update Query_Builder.php with the database connection information (Currently starting on line 156)
```php
            'HOST' => '127.0.0.1',
                'USER_NAME' => 'root',
                'PASSWORD' => 'root',
                'DATABASE_NAME' => 'mydb',
```
3. Include Query_Builder.php
```PHP 
include "Query_Builder.php";
use Query_Builder\Query_Builder as Builder;
```


## Future Plans (in no particular order):
- Sample code in README.md
- Return results
    - More array manipulation methods
- Query Optimizer
    - added as a part of Helper
    - Makes suggestions for building better querries
        - Ex: Using "WHERE `field` IN('option1', 'option2')" instead of "WHERE `field` = 'value1' OR `field` = 'value2'"
- Improve select statements for better handeling of functions & change from string to stack or make use of improved subqueries for use with groupings
    - Improve functionality of serialized/json arrays as field data
    - Functions for searching through pipe (|) delimited column
        - EX: Partial Code 1
    - Handle multiple querries
        - Partial Code 2
- add MySQL DDL class statements
- Relationships or at least a way to insert into multiple Tables
    - Partial Code 3:

Partial Code 1 (MySQL):
```SQL
 `field` REGEXP '[[:<:]]item[[:>:]]'
```

Partial Code 2 (PHP):
```PHP
if ($mysqli->multi_query($query)) {
    $i = 0;
    do {
        /* store first result set */
        if ($result = $mysqli->store_result()) {
            while ($row = $result->fetch_row()) {
                $listing_alerts[$i]['property_alerts'] = $row[0];
            }
            $result->free();
        }
        $i++;
    } while ($mysqli->more_results() && $mysqli->next_result());
}
```

Partial Code 3 (MySQL):
```SQL
BEGIN;
    Insert Statement 1
    SELECT LAST_INSERT_ID() INTO @last_id;
    Insert Statement 2 (using @last_id )
    Insert Statement 2 (using @last_id )
COMMIT;
```
