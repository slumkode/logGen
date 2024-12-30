<?php
date_default_timezone_set('Africa/Nairobi');

// Function to execute system commands
function executeCommand($command)
{
    $output = [];
    $resultCode = 0;
    exec($command, $output, $resultCode);
    return [$output, $resultCode];
}

// Load configuration from the config.json file
function loadConfig($configFile)
{
    if (!file_exists($configFile)) {
        die("Error: Configuration file {$configFile} not found.\n");
    }
    $config = json_decode(file_get_contents($configFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Error: Failed to parse the configuration file.\n");
    }
    return $config;
}

// Read configuration from config.json
$config = loadConfig('config.json');

// Check if Monolog is installed
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "Monolog is not installed. Installing dependencies...\n";

    // Run composer install command to install dependencies
    list($output, $resultCode) = executeCommand('composer install');

    if ($resultCode === 0) {
        echo "Monolog and other dependencies successfully installed.\n";
    } else {
        die("Error: Failed to install dependencies. Please ensure Composer is installed and try again.\n");
    }
}

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Define log levels
$logLevels = [
    Logger::DEBUG => 'DEBUG',
    Logger::INFO => 'INFO',
    Logger::NOTICE => 'NOTICE',
    Logger::WARNING => 'WARNING',
    Logger::ERROR => 'ERROR',
    Logger::CRITICAL => 'CRITICAL',
    Logger::ALERT => 'ALERT',
    Logger::EMERGENCY => 'EMERGENCY'
];

// Function to generate a random log level
function getRandomLogLevel($logLevels)
{
    return array_rand($logLevels);
}

// Function to create log files and log messages
function generateLogs($config)
{
    $log_date_format = 'Y-m-d'; // Define log date format

    // Get the current date in the specified format
    $currentDate = date($log_date_format);

    // Continuous loop for log generation
    while (true) {
        foreach ($config['applications'] as $app) {
            // Fork a new process for each log file generation
            $pid = pcntl_fork();

            if ($pid == -1) {
                die("Could not fork process\n");
            } elseif ($pid) {
                // Parent process: continues to the next iteration (does not block)
                continue;
            } else {
                // Child process: generates logs for a specific app
                $logFileName = "{$app}_{$currentDate}.log";
            
                // Create a new logger instance for each application
                $logger = new Logger("{$logFileName}");
    
                // Set a handler to write logs to the file (appending)
                $handler = new StreamHandler(__DIR__ . '/logs/' . $logFileName, Logger::DEBUG);
                
                // Customize the formatter to control spacing and log format
                $formatter = new LineFormatter(
                    "[%datetime%] %channel% %level_name%: %message%\n", // Custom format
                    "Y-m-d\TH:i:s.uP", // Date format
                    true, // Enable microsecond precision
                    true  // Include extra context
                );
                $handler->setFormatter($formatter);
    
                // Push the handler to the logger
                $logger->pushHandler($handler);
    
                // Generate logs at an increased rate with random intensity
                for ($i = 0; $i < $config['logs_per_file']; $i++) {
                    $logLevel = getRandomLogLevel($GLOBALS['logLevels']);
                    $logger->log($logLevel, "This is a {$GLOBALS['logLevels'][$logLevel]} message.");
    
                    // Randomized sleep time to make the log generation more aggressive but optimized
                    $randomSleep = rand(50000, 150000); // Random sleep between 50ms and 150ms
                    usleep($randomSleep); // sleep between log entries
                }
    
                echo "Generated {$config['logs_per_file']} logs in {$logFileName}\n";
                
                exit(); // End the child process after completing its task
            }
        }

        // Sleep between iterations to avoid continuous overload, adjust as necessary
        usleep($config['interval_between_logs'] * 1000000); // Sleep for the defined interval between iterations (in microseconds)

        // Collect child processes to avoid defunct processes
        while (pcntl_waitpid(0, $status, WNOHANG) > 0) {
            // Parent collects exit status of child processes
        }
    }
}

// Run the log generation with the configuration read from the file
generateLogs($config);
