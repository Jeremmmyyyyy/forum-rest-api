<?php

/**
 * Log a message to the error log file
 * @param string $message The message to log
 * @param string $level Log level (INFO, WARNING, ERROR, DEBUG)
 * @return void
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    $logFile = __DIR__ . '/../../logs/error.log';
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message
    ];
    
    $logLine = '[' . $logEntry['timestamp'] . '] [' . $logEntry['level'] . '] ' . $logEntry['message'] . PHP_EOL;
    
    // Append to log file
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    
    // Also log to PHP error log for Docker logs
    error_log('[' . $level . '] ' . $message);
}
