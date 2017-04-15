<?php

/**
 * @file
 *   Backup and sanitize Backdrop CMS database to the filesystem.
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

// Get some *.inc files we need.
require_once "core/includes/password.inc";

// Load up the config variables.
$config = parse_ini_file('config.ini');
$db_user = $config['DB_USER'];
$db_password = $config['DB_PASSWORD'];
$db_name = $config['DB_NAME'];
$db_host = $config['DB_HOST'];
$backdrop_root = $config['BACKDROP_ROOT'];
$backup_destination = $config['BACKUP_DESTINATION'];

// TODO: pass argument for quiet.
$quiet = FALSE;

// TODO: pass argument for sanitize.
$sanitize = FALSE;

if ($sanitize) {
  _sanitize($db_user, $db_password, $db_host, $db_name);
  $db_name = 'tmp_db2';
  $backup_destination = $backup_destination . '/sanitized';
}

// Dump DB to file.
$date = date('F-j-Y-Gis');
$file_name = $sanitize ? "$db_name-$date-sanatized" : "$db_name-$date";
exec("mkdir -p $backup_destination");
exec("mysqldump -h $db_host -u$db_user -p$db_password $db_name > $backup_destination/$file_name.sql");

// Get nice name.
$db_name = $config['DB_NAME'];
$nice_name = $sanitize ? "$db_name-$date-sanatized" : "$db_name-$date";
exec("mv $backup_destination/$file_name.sql $backup_destination/$nice_name.sql");

// Give feedback if the --quiet option is not set.
if (file_exists("$backup_destination/$file_name.sql") && !$quiet) {
  print "\n\t\tBackup successful: $backup_destination/$file_name.sql\n\n";
}
else {
  print "\n\t\tSanitized backup failed: Perhaps check your config.ini settings?\n\n";
}

// Remove the tmp_db2 database.
if ($sanitize) {
  exec("echo \"drop database tmp_db2\" | mysql -u $db_user -p$db_password");
}

// TODO: set flag for --rollover_backups.
$rollover = TRUE;
if ($rollover) {
  // Do it once for the backups.
  _rollover_backups($backup_destination);
  // Do it again for the sanitized backups.
  _rollover_backups($backup_destination . "/sanitized");
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

  // Get mysql connection to $db_name.
  try {
    $conn = new PDO("mysql:host=$db_host;dbname=tmp_db2", $db_user, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "select * from users where 1;";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $result = $stmt->fetchAll();

    $i = 0;
    foreach($result as $r) {
      $uid = $r['uid'];
      $mail = $r['mail'];
      if ($uid != 0) {
        $update = "update users
          set mail=\"user+$i@localhost\", init=\"user+$i@localhost\"
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
    if (strpos($b, '.sql') === FALSE) {
      unset($bups[$key]);
      // ignore; no action needed.
    }
    else {
      $my_key = filemtime("$backup_destination/$b");
      $filemtime_keyed_array[$my_key] = $b;
    }
  }
  ksort($filemtime_keyed_array);
  print_r($filemtime_keyed_array);
  $newes_bups_first = array_reverse($filemtime_keyed_array);
  $k = 0;
  foreach ($newes_bups_first as $bup) {
    if ($k > ($num_keep - 1)) {
      exec("rm $backup_destination/$bup");
    }
    $k++;
  }
}
