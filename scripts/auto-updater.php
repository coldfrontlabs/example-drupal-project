<?php

/**
 * @file
 * Checks for and attempts to apply security updates to composer dependencies.
 *
 * This script is intended to be run as part of a Gitlab CI pipeline.
 *
 * This is a first draft to replace the current auto updater job in Gitlab
 * and could use some improvement.
 *
 * This file is managed by dropfort/dropfort_build.
 * Modifications to this file will be overwritten by default.
 */

/**
 * Logs an error message to a file.
 *
 * GitlabCI does not allow external scripts to log to the console,
 * so we need to log errors to a file.
 *
 * @param string $message
 *   The error message to log.
 */
function log_error($message) {
  file_put_contents('auto-updater.txt', time() . ' - ' . $message . "\n");
  exit(1);
}

/**
 * Gets the audit information from composer.
 *
 * @param string $composer
 *   The path to the composer executable.
 *
 * @return array
 *   The audit information.
 */
function get_composer_audit_info(string $composer) {
  try {
    exec($composer . ' audit --format=json', $composer_audit_info, $result_code);

    return json_decode(implode('', $composer_audit_info), TRUE);
  }
  catch (Exception $e) {
    log_error($e->getMessage());
  }
}

/**
 * Gets the audit information from npm.
 *
 * @param string $npm
 *   The path to the npm executable.
 *
 * @return array
 *   The audit information.
 */
function get_npm_audit_info(string $npm) {
  try {
    exec($npm . ' audit --json --omit dev', $npm_audit_info, $result_code);

    return json_decode(implode('', $npm_audit_info), TRUE);
  }
  catch (Exception $e) {
    log_error('Error getting npm audit information: ' . $e->getMessage());
  }
}

/**
 * Gets the outdated information from composer.
 *
 * @param string $composer
 *   The path to the composer executable.
 *
 * @return array
 *   The outdated information.
 */
function get_composer_outdated_info(string $composer) {
  try {
    exec($composer . ' outdated --direct --format=json', $composer_outdated_info, $result_code);

    if ($result_code === 1) {
      throw new Exception('Error getting composer outdated information.');
    }

    return json_decode(implode('', $composer_outdated_info), TRUE);
  }
  catch (Exception $e) {
    log_error($e->getMessage());
  }
}

/**
 * Gets the outdated information from npm.
 *
 * @param string $npm
 *   The path to the npm executable.
 *
 * @return array
 *   The outdated information.
 */
function get_npm_outdated_info(string $npm) {
  try {
    exec($npm . ' outdated --json', $npm_outdated_info, $result_code);

    return json_decode(implode('', $npm_outdated_info), TRUE);
  }
  catch (Exception $e) {
    log_error('Error getting npm outdated information: ' . $e->getMessage());
  }
}

/**
 * Gets the package information from composer.
 *
 * @param string $composer
 *   The path to the composer executable.
 * @param string $package_name
 *   The name of the package to get information for.
 *
 * @return array
 *   The package information.
 */
function get_composer_package_info(string $composer, string $package_name) {
  try {
    exec($composer . ' show ' . $package_name . ' --format=json', $package, $result_code);

    if ($result_code === 1) {
      throw new Exception('Could not get package information from composer');
    }

    return json_decode(implode('', $package), TRUE);
  }
  catch (Exception $e) {
    log_error('Error getting package information for "' . $package_name . '": ' . $e->getMessage());
  }
}

/**
 * Gets the package information from npm.
 *
 * @param string $npm
 *   The path to the npm executable.
 * @param string $package_name
 *   The name of the package to get information for.
 *
 * @return array
 *   The package information.
 */
function get_npm_package_info(string $npm, string $package_name) {
  try {
    exec($npm . ' list ' . $package_name . ' --json', $package, $result_code);

    $package = json_decode(implode('', $package), TRUE);

    return $package['dependencies'][$package_name];
  }
  catch (Exception $e) {
    log_error('Error getting package information for "' . $package_name . '": ' . $e->getMessage());
  }
}

/**
 * Gets the composer packages to update.
 *
 * @param string $composer
 *   The path to the composer executable.
 * @param string $type
 *   The type of updates to get.
 *
 * @return array
 *   The packages to update.
 */
function get_composer_packages(string $composer, string $type) {
  try {
    $packages = [];

    if ($type === 'security') {
      // Get composer's information.
      $composer_audit_info = get_composer_audit_info($composer);

      // Get the version info for each vulnerable package.
      foreach ($composer_audit_info['advisories'] as $advisory) {
        $advisory = reset($advisory);
        $package = get_composer_package_info($composer, $advisory['packageName']);
        $package_name = $package['name'];
        $package_version = reset($package['versions']);

        $packages[$package_name] = [
          'name' => $package_name,
          'current_version' => $package_version,
        ];

        // If drupal core is vulnerable, get all `drupal/core-*` packages.
        if ($package_name === 'drupal/core') {
          $composer_outdated_info = get_composer_outdated_info($composer);

          if (!empty($composer_outdated_info['installed'])) {
            $core_packages = array_filter($composer_outdated_info['installed'], function ($package) {
              return strpos($package['name'], 'drupal/core-') === 0;
            });

            foreach ($core_packages as $core_package) {
              $packages[$core_package['name']] = [
                'name' => $core_package['name'],
                'current_version' => $core_package['version'],
              ];
            }
          }
        }
      }
    }
    else {
      // Get composer's information.
      $composer_outdated_info = get_composer_outdated_info($composer);

      // Get the version info for each vulnerable package.
      foreach ($composer_outdated_info['installed'] as $installed) {
        $package = get_composer_package_info($composer, $installed['name']);
        $package_name = $package['name'];
        $package_version = reset($package['versions']);

        $packages[$package_name] = [
          'name' => $package_name,
          'current_version' => $package_version,
        ];
      }
    }

    return $packages;

  }
  catch (Exception $e) {
    log_error('Error getting composer packages to update: ' . $e->getMessage());
  }
}

/**
 * Gets the npm packages to update.
 *
 * @param string $npm
 *   The path to the npm executable.
 * @param string $type
 *   The type of updates to get.
 *
 * @return array
 *   The packages to update.
 */
function get_npm_packages(string $npm, string $type) {
  try {
    $packages = [];

    if ($type === 'security') {
      // Get npm's information.
      $npm_audit_info = get_npm_audit_info($npm);

      // Get the version info for each vulnerable package.
      foreach ($npm_audit_info['vulnerabilities'] as $package_name => $package) {
        $package_info = get_npm_package_info($npm, $package_name);
        $updated_version = $package_info['version'];

        if (!empty($package['fixAvailable']) && !$package['fixAvailable']['isSemVerMajor']) {
          $updated_version = $package['fixAvailable']['version'];
        }

        $packages[$package_name] = [
          'name' => $package_name,
          'current_version' => $package_info['version'],
          'updated_version' => $updated_version,
        ];
      }
    }
    else {
      // Get npm's information.
      $npm_outdated_info = get_npm_outdated_info($npm);

      // Get the version info for each outdated.
      foreach ($npm_outdated_info as $package_name => $package) {
        $packages[$package_name] = [
          'name' => $package_name,
          'current_version' => $package['current'],
          'updated_version' => $package['wanted'],
        ];
      }
    }

    return $packages;

  }
  catch (Exception $e) {
    log_error('Error getting npm packages to update: ' . $e->getMessage());
  }

}

// Get args passed to the script.
$raw_args = array_splice($argv, 1);
$args = [];
foreach ($raw_args as $arg) {
  $arg_parts = explode('=', $arg);
  $args[$arg_parts[0]] = !empty($arg_parts[1]) ? $arg_parts[1] : TRUE;
}

$type = !empty($args['--type']) ? $args['--type'] : 'general';
$composer_executable = !empty($args['--composer']) ? $args['--composer'] : 'composer';
$npm_executable = !empty($args['--npm']) ? $args['--npm'] : 'npm';
$drupal_config_dir = !empty($args['--drupal-config-dir']) ? $args['--drupal-config-dir'] : 'config';
$update_drupal = array_key_exists('--update-drupal', $args);
$package_managers = !empty($args['--package-managers']) ? $args['--package-managers'] : 'composer,npm';
$package_managers = explode(',', $package_managers);
$no_commit = array_key_exists('--no-commit', $args);


// Check versions before anything else. If the versions aren't correct,
// there's no point in proceeding.
if (in_array('composer', $package_managers)) {
  // Get the version of composer.
  $composer_version = exec($composer_executable . ' --version');
  preg_match('/\d+\.\d+\.\d+/', $composer_version, $matches);
  $composer_version = $matches[0];

  // If the version of composer is not _at least_ 2.4.0, exit.
  if (version_compare($composer_version, '2.4.0', '<')) {
    log_error('The composer audit command does not exist in composer versions less than 2.4.0. You are using version ' . $composer_version . '.');
  }
}

if (in_array('npm', $package_managers)) {
  // Get the version of npm.
  $npm_version = exec($npm_executable . ' --version');

  // If the version of npm is not _at least_ 6.0.0, exit.
  if (version_compare($npm_version, '6.0.0', '<')) {
    log_error('The npm audit command does not exist in npm versions less than 6.0.0. You are using version ' . $npm_version . '.');
  }
}

if (in_array('composer', $package_managers)) {
  // An array to hold information about the vulnerable packages.
  $packages = get_composer_packages($composer_executable, $type);

  // Remove composer/composer from the list of packages to update.
  // Attempting to update composer in this way can cause major issues.
  $packages_to_update = array_filter($packages, function ($info) {
    return $info['name'] !== 'composer/composer';
  });

  // Only run the update command if there are packages to update.
  if (!empty($packages_to_update)) {
    try {
      exec($composer_executable . ' update ' . implode(' ', array_keys($packages_to_update)) . ' -W -q', $output, $result_code);

      if ($result_code !== 0) {
        throw new Exception('Composer failed to update packages.');
      }
    }
    catch (Exception $e) {
      log_error('Error updating packages: ' . $e->getMessage());
    }
  }

  // Get the updated version info for the vulnerable packages.
  foreach ($packages as $package_name => $package_info) {
    try {
      $package = get_composer_package_info($composer_executable, $package_name);
      $package_version = reset($package['versions']);

      $packages[$package_name]['updated_version'] = $package_version;
    }
    catch (Exception $e) {
      log_error('Error getting updated package information for "' . $package_name . '": ' . $e->getMessage());
    }
  }

  try {
    $updated_packages = array_filter($packages, function ($info) {
      return $info['current_version'] !== $info['updated_version'];
    });
    $updated_packages_info = '';
    if (!empty($updated_packages)) {
      foreach ($updated_packages as $package_name => $package_info) {
        $updated_packages_info .= '- ' . $package_name . ' (' . $package_info['current_version'] . ' -> ' . $package_info['updated_version'] . ")\n";
      }
    }
  }
  catch (Exception $e) {
    log_error('Error filtering updated packages: ' . $e->getMessage());
  }

  try {
    $still_outdated = array_filter($packages, function ($info) {
      return $info['current_version'] === $info['updated_version'];
    });
    $still_outdated_info = '';
    if (!empty($still_outdated)) {
      foreach ($still_outdated as $package_name => $package_info) {
        $still_outdated_info .= '- ' . $package_name . ' (' . $package_info['current_version'] . ")\n";
      }
    }
  }
  catch (Exception $e) {
    log_error('Error filtering still outdated packages: ' . $e->getMessage());
  }

  // Create a git commit message with the updated packages.
  if (!empty($updated_packages) && !$no_commit) {
    try {
      exec('git add composer.lock');
      exec('git commit -m "build(composer): apply ' . $type . ' updates" -m "The following packages were updated" -m "' . $updated_packages_info . '" --no-verify');
    }
    catch (Exception $e) {
      log_error('Error committing composer updates: ' . $e->getMessage());
    }
  }

  // Run drupal updates and capture the config.
  if ($update_drupal) {
    try {
      exec('cp $DF_CONTAINER_ROOT/dev/config/drupal/default.settings.php $DF_DRUPAL_WEBROOT/sites/default/settings.php', $output, $result_code);

      if ($result_code !== 0) {
        throw new Exception('Error copying settings.php. ' . implode("\n", $output));
      }

      exec('drush $DRUSH_SITE_ALIAS_OPTIONS sql:sync @$DRUSH_ALIAS_GROUP.$DF_ENV_TYPE @self', $output, $result_code);

      if ($result_code !== 0) {
        throw new Exception('Error syncing database. ' . implode("\n", $output));
      }

      exec('drush cr', $output, $result_code);

      if ($result_code !== 0) {
        throw new Exception('Error clearing cache. ' . implode("\n", $output));
      }

      exec('drush cim -y', $output, $result_code);

      if ($result_code !== 0) {
        throw new Exception('Error importing config. ' . implode("\n", $output));
      }

      exec('drush updb -y', $output, $result_code);

      if ($result_code !== 0) {
        throw new Exception('Error updating database. ' . implode("\n", $output));
      }

      exec('drush cex -y', $output, $result_code);

      if ($result_code !== 0) {
        throw new Exception('Error exporting config. ' . implode("\n", $output));
      }

      if (!$no_commit) {
        exec('git add ' . $drupal_config_dir);
        exec('git commit -m "build(drupal): export config changes" --no-verify || true');
      }
    }
    catch (Exception $e) {
      log_error('Error updating Drupal: ' . $e->getMessage());
    }
  }

  // Commit remaining changes as dropfort_build scaffold updates.
  if (!$no_commit) {
    exec('git add .');
    exec('git commit -m "build(dropfort): update scaffolded files" --no-verify || true');
  }

  try {
    $merge_request_message = "## Composer updates\n\n";

    if (!empty($updated_packages)) {
      $merge_request_message .= "### The following packages were updated\n\n";
      $merge_request_message .= $updated_packages_info;
      $merge_request_message .= "\n\n";
    }
    else {
      $merge_request_message .= "No packages were updated.\n\n";
    }

    if (!empty($still_outdated)) {
      if ($type === 'security') {
        $merge_request_message .= "### The following packages could _not_ be updated and are _still vulnerable_\n\n";
      }
      else {
        $merge_request_message .= "### The following packages could _not_ be updated\n\n";
      }
      $merge_request_message .= $still_outdated_info;
      $merge_request_message .= "\n\n";
    }

    // Write the merge request message to a file.
    file_put_contents('auto-updater.txt', $merge_request_message, FILE_APPEND);
  }
  catch (Exception $e) {
    log_error('Error writing composer section of merge request message: ' . $e->getMessage());
  }
}

if (in_array('npm', $package_managers)) {
  $packages = get_npm_packages($npm_executable, $type);

  // Only run the update command if there are packages to update.
  if (!empty($packages)) {
    try {
      $result_code = 0;

      if ($type === 'security') {
        exec($npm_executable . ' audit fix --quiet --no-progress --omit dev', $output, $result_code);
      }
      else {
        exec($npm_executable . ' update --quiet --no-progress', $output, $result_code);
      }

      if ($result_code !== 0) {
        throw new Exception('NPM failed to update packages.');
      }
    }
    catch (Exception $e) {
      log_error('Error updating packages: ' . $e->getMessage());
    }
  }

  try {
    $updated_packages = array_filter($packages, function ($info) {
      return $info['current_version'] !== $info['updated_version'];
    });
    $updated_packages_info = '';

    if (!empty($updated_packages)) {
      foreach ($updated_packages as $package_name => $package_info) {
        $updated_packages_info .= '- ' . $package_name . ' (' . $package_info['current_version'] . ' -> ' . $package_info['updated_version'] . ")\n";
      }
    }
  }
  catch (Exception $e) {
    log_error('Error filtering updated packages: ' . $e->getMessage());
  }

  try {
    $still_outdated = array_filter($packages, function ($info) {
      return $info['current_version'] === $info['updated_version'];
    });
    $still_outdated_info = '';

    if (!empty($still_outdated)) {
      foreach ($still_outdated as $package_name => $package_info) {
        $still_outdated_info .= '- ' . $package_name . ' (' . $package_info['current_version'] . ")\n";
      }
    }
  }
  catch (Exception $e) {
    log_error('Error filtering still outdated packages: ' . $e->getMessage());
  }

  if (!empty($updated_packages) && !$no_commit) {
    try {
      exec('git add package-lock.json');
      exec('git commit -m "build(npm): apply ' . $type . ' updates" -m "The following packages were updated" -m "' . $updated_packages_info . '" --no-verify');
    }
    catch (Exception $e) {
      log_error('Error committing npm updates: ' . $e->getMessage());
    }
  }

  try {
    $merge_request_message = "## NPM updates\n\n";

    if (!empty($updated_packages)) {
      $merge_request_message .= "### The following packages were updated\n\n";
      $merge_request_message .= $updated_packages_info;
      $merge_request_message .= "\n\n";
    }
    else {
      $merge_request_message .= "No packages were updated.\n\n";
    }

    if (!empty($still_outdated)) {
      if ($type === 'security') {
        $merge_request_message .= "### The following packages could _not_ be updated and are _still vulnerable_\n\n";
      }
      else {
        $merge_request_message .= "### The following packages could _not_ be updated\n\n";
      }
      $merge_request_message .= $still_outdated_info;
      $merge_request_message .= "\n\n";
    }

    // Write the merge request message to a file.
    file_put_contents('auto-updater.txt', $merge_request_message, FILE_APPEND);
  }
  catch (Exception $e) {
    log_error('Error writing npm section of merge request message: ' . $e->getMessage());
  }
}

exit(0);
