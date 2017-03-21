<?php

require_once "core/includes/password.inc";

// Create the tmp_db2 database.
exec("echo \"create database tmp_db2\" | mysql -u root -ppass");

// Dump DB and pipe into tmp_db2.
exec("mysqldump -h localhost -u root -ppass backdrop | mysql -h localhost -u root -ppass tmp_db2");

// Sanitize the users table.
db_set_active('tmp_db2');
$sql = "select * from users where 1;";
$result = db_query($sql);

$password = user_hash_password('password');
$i = 0;
foreach ($result as $r) {
  db_update('users')
    ->fields(array('mail' => "user+$i@localhost", 'pass' => $password))
    ->condition('uid', $r->uid)
    ->execute();

  $i++;
}
db_set_active('backdrop');

// Dump newly santitized DB to file.
$date = date('F-j-Y');
$db = 'borg-' . $date;
exec("mysqldump -h localhost -uroot -ppass tmp_db2 > $db.sql");

// Remove the tmp_db2 database.
exec("echo \"drop database tmp_db2\" | mysql -u root -ppass");
