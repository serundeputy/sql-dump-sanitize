<?php

/**
 * Backup the files directory of a Backdrop CMS site.
 */


// Check if we already have config array.
if (!isset($config)) {
 $config = parse_ini_file('config.ini');
}

// Get path to files directory.
$backdrop_root = $config['BACKDROP_ROOT'];
$destination = $config['BACKUP_DESTINATION'];
$backups_to_keep = $config['NUM_KEEP'];

// Check which options were passed in on the command line.
if (in_array('--rollover_files', $argv) || in_array('-rf', $argv)) {
  $rollover_files = TRUE;
}
else {
  $rollover_files = FALSE;
}

// Get timestamp.
date_default_timezone_set('EST');
$date = date('Ymd-Gis');

// Make backup.
exec(
  "tar czf backdropcmsorg-files-$date.tar.gz -C $backdrop_root files/ &&
  mkdir -p $destination/files_backups
  mv backdropcmsorg-files-$date.tar.gz $destination/files_backups"
);

if ($rollover_files) {
  _rollover_files_backups($destination, $backups_to_keep);
}

/**
 * Helper function to delete stale files backups.
 *
 * @param string $backup_destination
 *   The path to the directory where you would like to delete stale backups.
 *
 * @param int $backups_to_keep
 *   The number of backups you would like to keep.  Defaults to 3.
 */
function _rollover_files_backups($backup_destination, $backups_to_keep = 3) {
  $filemtime_keyed_array = [];
  $backups = scandir($backup_destination . '/files_backups');
  foreach ($backups as $key => $b) {
    if (strpos($b, '.tar.gz') === FALSE) {
      unset($backups[$key]);
    }
    else {
      $my_key = filemtime("$backup_destination/files_backups/$b");
      $filemtime_keyed_array[$my_key] = $b;
    }
  }
  ksort($filemtime_keyed_array);
  $newest_backups_first = array_reverse($filemtime_keyed_array);
  $k = 0;
  foreach ($newest_backups_first as backup) {
    if ($k > ($backups_to_keep - 1)) {
      exec("rm $backup_destination/files_backups/backup");
    }
    $k++;
  }
}
