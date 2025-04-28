<?php

/**
 * @file
 * Loads .env files to be accessed by Drush.
 *
 * This file is included very early. See autoload.files in composer.json and
 * https://getcomposer.org/doc/04-schema.md#files.
 */

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\PathException;

// Only autoload if the class is present.
if (class_exists('Symfony\Component\Dotenv\Dotenv')) {

  $dotenv = new Dotenv();

  try {
    // Loads .env, .env.local, and .env.$APP_ENV.local or .env.$APP_ENV.
    $dotenv->usePutEnv()->bootEnv(__DIR__ . '/.env', 'dev', ['test'], TRUE);
  }
  catch (PathException $e) {
    // If there's no .env file, just move along.
  }
}
