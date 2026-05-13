<?php
/**
 * Application Constants
 */

define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost');
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Roles
define('ROLE_USER', 'user');
define('ROLE_ADMIN', 'admin');

// Difficulty Levels
define('DIFFICULTY_BEGINNER', 'beginner');
define('DIFFICULTY_INTERMEDIATE', 'intermediate');
define('DIFFICULTY_ADVANCED', 'advanced');
define('DIFFICULTY_SENIOR', 'senior');

// Level Types
define('LEVEL_TYPE_CODE_CHALLENGE', 'code_challenge');
define('LEVEL_TYPE_PAGE_RECREATION', 'page_recreation');

// Point Formula
define('BASE_POINTS', 1000);
define('TIME_PENALTY_DIVISOR', 30);
define('TIME_PENALTY_UNIT', 10);
define('TRY_PENALTY_UNIT', 50);
define('HINT_PENALTY', 100);
define('MIN_POINTS', 100);

// Execution Limits
define('PHP_EXECUTION_TIMEOUT', 5);
define('MAX_RUN_PHP_REQUESTS_PER_MINUTE', 10);

// Security
define('BCRYPT_COST', 12);
