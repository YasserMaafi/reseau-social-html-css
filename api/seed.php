<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Achievement.php';

// Optional token-based access for seeding (useful for setup)
$token = $_GET['token'] ?? '';
if ($token === 'seed123' || APP_ENV === 'development') {
    // Allow in development mode or with valid token
} else {
    Auth::init();
    Auth::requireAdmin();
}

// Seed initial levels
$levels = [
    // Beginner JavaScript
    [
        'title' => 'Hello World',
        'language' => 'javascript',
        'difficulty' => 'beginner',
        'order_index' => 1,
        'type' => 'code_challenge',
        'description' => 'Print "Hello, World!" to the console.',
        'expected_output' => 'hello, world!'
    ],
    [
        'title' => 'Sum Two Numbers',
        'language' => 'javascript',
        'difficulty' => 'beginner',
        'order_index' => 2,
        'type' => 'code_challenge',
        'description' => 'Create a function that adds two numbers: 5 + 3. Print the result.',
        'expected_output' => '8'
    ],
    [
        'title' => 'String Reversal',
        'language' => 'javascript',
        'difficulty' => 'beginner',
        'order_index' => 3,
        'type' => 'code_challenge',
        'description' => 'Reverse the string "JavaScript" and print it.',
        'expected_output' => 'tpircSavaJ'
    ],
    [
        'title' => 'Array Length',
        'language' => 'javascript',
        'difficulty' => 'beginner',
        'order_index' => 4,
        'type' => 'code_challenge',
        'description' => 'Create an array [1, 2, 3, 4, 5] and print its length.',
        'expected_output' => '5'
    ],
    [
        'title' => 'Even or Odd',
        'language' => 'javascript',
        'difficulty' => 'beginner',
        'order_index' => 5,
        'type' => 'code_challenge',
        'description' => 'Check if 42 is even or odd. Print "even" or "odd".',
        'expected_output' => 'even'
    ],
    
    // Beginner PHP
    [
        'title' => 'Hello World',
        'language' => 'php',
        'difficulty' => 'beginner',
        'order_index' => 1,
        'type' => 'code_challenge',
        'description' => 'Echo "Hello, World!" to the screen.',
        'expected_output' => 'hello, world!'
    ],
    [
        'title' => 'Sum Two Numbers',
        'language' => 'php',
        'difficulty' => 'beginner',
        'order_index' => 2,
        'type' => 'code_challenge',
        'description' => 'Add two numbers: 10 + 20. Echo the result.',
        'expected_output' => '30'
    ],
    [
        'title' => 'String Length',
        'language' => 'php',
        'difficulty' => 'beginner',
        'order_index' => 3,
        'type' => 'code_challenge',
        'description' => 'Get the length of the string "PHP" and echo it.',
        'expected_output' => '3'
    ],
    [
        'title' => 'Array Count',
        'language' => 'php',
        'difficulty' => 'beginner',
        'order_index' => 4,
        'type' => 'code_challenge',
        'description' => 'Create an array ["a", "b", "c"] and echo its count.',
        'expected_output' => '3'
    ],
    [
        'title' => 'Uppercase String',
        'language' => 'php',
        'difficulty' => 'beginner',
        'order_index' => 5,
        'type' => 'code_challenge',
        'description' => 'Convert "hello" to uppercase and echo it.',
        'expected_output' => 'HELLO'
    ],

    // Intermediate JavaScript
    [
        'title' => 'Factorial Calculator',
        'language' => 'javascript',
        'difficulty' => 'intermediate',
        'order_index' => 1,
        'type' => 'code_challenge',
        'description' => 'Calculate the factorial of 5. Print the result.',
        'expected_output' => '120'
    ],
    [
        'title' => 'Palindrome Checker',
        'language' => 'javascript',
        'difficulty' => 'intermediate',
        'order_index' => 2,
        'type' => 'code_challenge',
        'description' => 'Check if "racecar" is a palindrome. Print "yes" or "no".',
        'expected_output' => 'yes'
    ],
    
    // Intermediate PHP
    [
        'title' => 'Prime Number Checker',
        'language' => 'php',
        'difficulty' => 'intermediate',
        'order_index' => 1,
        'type' => 'code_challenge',
        'description' => 'Check if 17 is a prime number. Echo "prime" or "not prime".',
        'expected_output' => 'prime'
    ],
    [
        'title' => 'Sort Array',
        'language' => 'php',
        'difficulty' => 'intermediate',
        'order_index' => 2,
        'type' => 'code_challenge',
        'description' => 'Sort the array [5, 2, 8, 1] in ascending order and echo it as: 1,2,5,8',
        'expected_output' => '1,2,5,8'
    ],
];

$count = 0;
foreach ($levels as $level) {
    try {
        // Check if level already exists
        $existing = DB::queryOne(
            'SELECT id FROM levels WHERE title = ? AND language = ? AND difficulty = ?',
            [$level['title'], $level['language'], $level['difficulty']]
        );
        
        if (!$existing) {
            DB::execute(
                'INSERT INTO levels (title, language, difficulty, order_index, type, description, expected_output, image_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $level['title'],
                    $level['language'],
                    $level['difficulty'],
                    $level['order_index'],
                    $level['type'],
                    $level['description'],
                    $level['expected_output'],
                    null
                ]
            );
            $count++;
        }
    } catch (Exception $e) {
        // Level already exists, skip
    }
}

// Initialize achievements
try {
    Achievement::initializeAchievements();
} catch (Exception $e) {
    // Achievements already initialized
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => "Initialized $count new levels and achievements"
]);
