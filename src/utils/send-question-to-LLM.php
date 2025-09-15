<?php
/*
 * Copyright (c) 2024. Hadrien Sevel
 * Project: forum-rest-api
 * File: send-question-to-LLM.php
 */

function sendQuestionToLLM(string $questionId, string $id_page, string $id_notes_div, string $question)
{
    $url = 'https://botafogo.epfl.ch/llm/chat/completions';

    // Load API key from .env file
    $env = parse_ini_file(__DIR__ . '/../../.env');
    $apiKey = $env['API_KEY'];
    $timeout = isset($env['LLM_TIMEOUT_SECONDS']) ? (int)$env['LLM_TIMEOUT_SECONDS'] : 1200; // Default 20 minutes

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey,
        "model: CaLlm-course",
        "id: $questionId",
        "idpage: $id_page",
        "idnotesdiv: $id_notes_div"
    ];

    $data = [
        "model" => "CaLlm-course",
        "messages" => [
            [
            "role" => "user",
            "content" => $question
            ]
        ]
    ];

    $options = [
        'http' => [
            'header' => implode("\r\n", $headers) . "\r\n",
            'method' => 'POST',
            'content' => json_encode($data),
            'timeout' => $timeout,
            'ignore_errors' => true, // Don't throw on HTTP errors, we'll handle them
        ],
    ];

    $logFile = __DIR__ . '/../../logs/error.log';
    $log = function($msg) use ($logFile) {
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
    };

    $context = stream_context_create($options);
    $handle = fopen($url, 'r', false, $context);
    if ($handle === false) {
        $error = error_get_last();
        $log("Failed to open stream: " . ($error ? $error['message'] : 'Unknown error'));
        throw new Exception('Failed to connect to LLM service: ' . ($error ? $error['message'] : 'Unknown error'));
    }

    $response = '';
    $log("Opened stream to LLM");
    $totalWaitTime = 0;
    $startTime = microtime(true);
    $lastReadTime = $startTime;

    while (!feof($handle)) {
        // Check for timeout
        $currentTime = microtime(true);
        if ($currentTime - $startTime > $timeout) {
            $log("Request timed out after $timeout seconds");
            fclose($handle);
            throw new Exception("LLM request timed out after $timeout seconds");
        }

        $chunk = fgets($handle);
        $lastReadTime = $currentTime;
        
        // Skip empty chunks (heartbeats)
        if ($chunk === false || trim($chunk) === "") {
            continue;
        }
        
        $log("Read chunk: " . substr(var_export($chunk, true), 0, 200) . (strlen($chunk) > 200 ? '...' : ''));
        
        // Extract JSON after "data: "
        if (strpos($chunk, 'data: ') === 0) {
            $json = trim(substr($chunk, strlen('data: ')));
            
            // Handle [DONE] marker
            if ($json === '[DONE]') {
                $log("Found [DONE] marker, breaking loop");
                break;
            }
            
            try {
                $data = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $log("JSON decode error: " . json_last_error_msg());
                    continue;
                }
                
                if (isset($data['choices'][0]['delta']['content'])) {
                    $content = $data['choices'][0]['delta']['content'];
                    $response .= $content;
                    $log("Appended content: " . substr($content, 0, 50) . (strlen($content) > 50 ? '...' : ''));
                }
                
                // Check for finish_reason to detect end of stream
                if (isset($data['choices'][0]['finish_reason']) && $data['choices'][0]['finish_reason'] == 'stop') {
                    $log("Found finish_reason: " . $data['choices'][0]['finish_reason']);
                    break;
                }
            } catch (Exception $e) {
                $log("Error processing JSON: " . $e->getMessage());
            }
        }
    }
    
    fclose($handle);
    $log("Closed stream to LLM, total time: " . round(microtime(true) - $startTime, 2) . " seconds");

    // Clean up response text
    $response = trim($response);
    
    if (empty($response)) {
        $log("Empty response from LLM");
        throw new Exception('Failed to get answer from LLM (empty response)');
    }

    $result = ['answer' => $response];
    $log("Returning result length: " . strlen($response) . " characters");

    return json_encode($result);
}
