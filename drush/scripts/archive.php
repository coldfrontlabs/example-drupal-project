<?php

/**
 * @file
 * Script to create an archive of a site.
 *
 * This file is managed by dropfort/dropfort_build.
 * Modifications to this file will be overwritten by default.
 *
 * This file is managed by dropfort/dropfort_build.
 * Modifications to this file will be overwritten by default.
 */

// Get the drush status in php format and prepare it for unserializing.
exec('drush status --format=php', $drushinfo);
$drush_info = ltrim(reset($drushinfo));

// Unserialize the drush status.
$drush_status = unserialize($drush_info, ['allowed_classes' => FALSE]);

// If the drush status cannot be unserialized, exit.
if ($drush_status === FALSE) {
  echo("Failed to unserialize the drush status. \n");
  exit(EXIT_FAILURE);
}

// Get the source folder by removing the `/web` directory from the root.
$source_folder = preg_replace('/\/web$/', '', $drush_status['root']);

// If the source folder does not exist, exit.
if (!file_exists($source_folder)) {
  echo("Failed to find the source folder " . $source_folder . " \n");
  exit(EXIT_FAILURE);
}

// Get the Drupal environment.
$date_of_backup = date('o-m-d-W-w-His');
$filename = $date_of_backup;

// Get the enviroment variables.
$arb_path = $argv[1];
if (empty($arb_path)) {
  $folder_path = "/var/tmp/cibuilds/";
}
else {
  $folder_path = "{$arb_path}/";
}

$sql_file_path = $folder_path . $filename . '/database.sql';
$archiveFile = $folder_path . $filename . '.tar.gz';

// Create the filename directory.
if (mkdir($folder_path . $filename, 0777, TRUE)) {
  // Save the sql dump to the filename directory.
  exec('drush sql-dump --result-file=' . $sql_file_path . ' --gzip');
  // Backup the site files in filename directory.
  exec('rsync -av --no-links --exclude-from "../drush/scripts/exclude-list.txt" ' . $source_folder . '/ ' . $folder_path . $filename . '/');
  // Delete the filename directory if the folder_path dir cannot be created.
  if (!file_exists($folder_path)) {
    if (!mkdir($folder_path)) {
      echo("Failed to create the archive directory " . $folder_path . " \n");
      // Cleanup the tmp directory.
      exec('rm -rf ' . $folder_path . $filename);
      exit(EXIT_FAILURE);
    }
  }
  // Compress the files in the filename directory and save as filename.tar.gz.
  exec('tar --exclude=".git" --dereference -czf ' . $archiveFile . ' ' . $folder_path . $filename, $output);
  if (empty($output)) {
    echo("archive create " . $archiveFile . "\n");
  }
  else {
    echo("There was failures on the tar command" . $output);
  }
  // Cleanup the tmp filename folder.
  exec('chmod -R 777 ' . $folder_path . $filename . '/');
  exec('rm -rf ' . $folder_path . $filename);
}
else {
  echo("Failed to create folder " . $folder_path . "\n");
  exit(EXIT_FAILURE);
}
