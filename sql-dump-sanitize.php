<?php

/**
 * @file
 * Backup and sanitize Backdrop CMS database to the filesystem.
 *
 * Required configuration variables. Copy the config.ini.example file to
 *   config.ini and replace with values for your server.
 *
 *   DB_USER = root
 *   DB_PASSWORD = pass
 *   DB_NAME = backdrop
 *   DB_HOST = localhost
 *   BACKDROP_ROOT = /var/www/html
 *   BACKUP_DESTINATION = /home/user/me/backups
 */

// Load up the config variables.
$config = parse_ini_file('config.ini');
$db_user = $config['DB_USER'];
$db_password = $config['DB_PASSWORD'];
$db_name = $config['DB_NAME'];
$db_host = $config['DB_HOST'];
$backdrop_root = $config['BACKDROP_ROOT'];
$backup_destination = $config['BACKUP_DESTINATION'];
$num_keep = $config['NUM_KEEP'];

// Get some *.inc files we need.
require_once "$backdrop_root/core/includes/bootstrap.inc";
require_once "$backdrop_root/core/includes/password.inc";

// Check which options were passed in on the command line.
if (in_array('--quiet', $argv) || in_array('-q', $argv)) {
  $quiet = TRUE;
}
else {
  $quiet = FALSE;
}
if (in_array('--sanitize', $argv) || in_array('-s', $argv)) {
  $sanitize = TRUE;
}
else {
  $sanitize = FALSE;
}
if (in_array('--rollover', $argv) || in_array('-r', $argv)) {
  $rollover = TRUE;
}
else {
  $rollover = FALSE;
}
if (in_array('--latest', $argv) || in_array('-l', $argv)) {
  $latest = TRUE;
}
else {
  $latest = FALSE;
}

if ($sanitize) {
  _sanitize($db_user, $db_password, $db_host, $db_name);
  $db_name = 'tmp_db2';
  $backup_destination = $backup_destination . '/sanitized';
}

// Dump DB to file.
date_default_timezone_set('EST');
$date = date('F-j-Y-Gis');
$file_name = $sanitize ? "$db_name-$date-sanatized" : "$db_name-$date";
exec("mkdir -p $backup_destination");
exec("mysqldump -h $db_host -u$db_user -p$db_password $db_name | gzip > $backup_destination/$file_name.sql.gz");

// Get nice name.
$db_name = $config['DB_NAME'];
$nice_name = $sanitize ? "$db_name-$date-sanatized" : "$db_name-$date";
if ($latest) {
  if (file_exists("$backup_destination/$db_name-latest.sql.gz")) {
    unlink("$backup_destination/$db_name-latest.sql.gz");
  }
  symlink("$backup_destination/$file_name.sql.gz", "$db_name-latest.sql.gz");
}

// Give feedback if the --quiet option is not set.
if (!$quiet) {
  if (file_exists("$backup_destination/$nice_name.sql.gz")) {
    print "\n\t\tBackup successful: $backup_destination/$nice_name.sql.gz\n\n";
  }
  else {
    print "\n\t\tBackup failed: Perhaps check your config.ini settings?\n\n";
  }
}

// Remove the tmp_db2 database.
if ($sanitize) {
  exec("echo \"drop database tmp_db2\" | mysql -u $db_user -p$db_password");
}

if ($rollover) {
  _rollover_backups($backup_destination, $num_keep);
}

/**
 * Helper function to optionally sanitize the database backup.
 *
 * @param string $db_user
 *   Database user with permission to create and drop databases.
 *   Passed in via DB_USER in config.ini.
 *
 * @param string $db_password
 *   Password of the $db_user; passed in DB_PASSWORD via config.ini.
 *
 * @param string $db_host
 *   Usually 'localhost' passed in via DB_HOST in config.ini.
 *
 * @param string $db_name
 *   The database to sanitize and backup.
 *   Passed in via DB_NAME in config.ini.
 */
function _sanitize($db_user, $db_password, $db_host, $db_name) {
  // Create the tmp_db2 database.
  exec("echo \"create database tmp_db2\" | mysql -u $db_user -p$db_password");

  // Dump DB and pipe into tmp_db2.
  exec("mysqldump -h $db_host -u $db_user -p$db_password $db_name | mysql -h $db_host -u $db_user -p$db_password tmp_db2");

  // Clear the cache% tables.
  _truncate_cache_tables($db_user, $db_password, $db_host, $db_name);

  // Get mysql connection to $db_name.
  try {
    $conn = new PDO("mysql:host=$db_host;dbname=tmp_db2", $db_user, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "select * from users where 1;";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $result = $stmt->fetchAll();
    $password = user_hash_password('password');

    $i = 0;
    foreach ($result as $r) {
      $uid = $r['uid'];
      $mail = $r['mail'];
      if ($uid != 0) {
        $update = "update users
          set
            mail=\"user+$i@localhost\",
            init=\"user+$i@localhost\",
            pass=\"$password\"
          where uid = $uid;";
        $exec = $conn->prepare($update);
        $exec->execute();
      }
      $i++;
    }
  }
  catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
  }
}

/**
 * Helper function to delete stale backups.
 *
 * @param string $backup_destination
 *   The path to the directory where you would like to delete stale backups.
 *
 * @param int $num_keep
 *   The number of backups you would like to keep.  Defaults to 3.
 */
function _rollover_backups($backup_destination, $num_keep = 3) {
  $filemtime_keyed_array = [];
  $bups = scandir($backup_destination);
  foreach ($bups as $key => $b) {
    if (strpos($b, '.sql.gz') === FALSE) {
      unset($bups[$key]);
    }
    else {
      $my_key = filemtime("$backup_destination/$b");
      $filemtime_keyed_array[$my_key] = $b;
    }
  }
  ksort($filemtime_keyed_array);
  $newes_bups_first = array_reverse($filemtime_keyed_array);
  $k = 0;
  foreach ($newes_bups_first as $bup) {
    if ($k > ($num_keep - 1)) {
      exec("rm $backup_destination/$bup");
    }
    $k++;
  }
}

/**
 * Helper function to truncate cache tables.
 *
 * @param string $db_user
 *   Database user with permission to create and drop databases.
 *   Passed in via DB_USER in config.ini.
 *
 * @param string $db_password
 *   Password of the $db_user; passed in DB_PASSWORD via config.ini.
 *
 * @param string $db_host
 *   Usually 'localhost' passed in via DB_HOST in config.ini.
 *
 * @param string $db_name
 *   The database to sanitize and backup.
 *   Passed in via DB_NAME in config.ini.
 */
function _truncate_cache_tables($db_user, $db_password, $db_host, $db_name) {
  try {
    $conn = new PDO("mysql:host=$db_host;dbname=tmp_db2", $db_user, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT concat('TRUNCATE TABLE `', TABLE_NAME, '`;')
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_NAME LIKE 'cache%' and table_schema=\"$db_name\";";

    $statement = $conn->prepare($sql);
    $statement->execute();

    $result = $statement->fetchAll();
    foreach ($result as $r) {
      $clear_statement = $conn->prepare($r[0]);
      $clear_statement->execute();
    }
  }
  catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
  }
}
