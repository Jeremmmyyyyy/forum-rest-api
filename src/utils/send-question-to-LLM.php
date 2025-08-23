<?php
/*
 * Copyright (c) 2024. Hadrien Sevel
 * Project: forum-rest-api
 * File: send-question-to-LLM.php
 */

function sendQuestionToLLM(string $id_page, string $id_notes_div, string $question)
{
    $url = 'https://botafogo.epfl.ch/llm/chat/completions';

    // Load API key from .env file
    $env = parse_ini_file(__DIR__ . '/../../.env');
    $apiKey = $env['API_KEY'];

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey,
        "model: CaLlm-course",
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
            'timeout' => 1200, // 20 minutes
        ],
    ];

    $context = stream_context_create($options);
    $handle = fopen($url, 'r', false, $context);
    if ($handle === false) {
        throw new Exception('Failed to open stream to LLM');
    }

    $response = '';
    $logFile = __DIR__ . '/../../logs/error.log';
    $log = function($msg) use ($logFile) {
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
    };

    $log("Opened stream to LLM");

    while (!feof($handle)) {
        $chunk = fgets($handle);
        $log("Read chunk: " . var_export($chunk, true));
        if ($chunk === false) {
            $log("Chunk is false, breaking loop");
            break;
        }
        // Stop reading if we reach the [DONE] marker
        if (strpos($chunk, 'data: [DONE]') !== false) {
            $log("Found [DONE] marker, breaking loop");
            break;
        }
        // Extract JSON after "data: "
        if (strpos($chunk, 'data: ') === 0) {
            $json = trim(substr($chunk, strlen('data: ')));
            $log("Extracted JSON: " . $json);
            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $log("JSON decode error: " . json_last_error_msg());
            }
            if (isset($data['choices'][0]['delta']['content'])) {
                $response .= $data['choices'][0]['delta']['content'];
                $log("Appended content: " . $data['choices'][0]['delta']['content']);
            }
        }
    }
    fclose($handle);
    $log("Closed stream to LLM");

    $result = ['answer' => $response];

    if (empty($result['answer'])) {
        $log("Result['answer'] is empty, throwing exception");
        throw new Exception('Failed to get answer from LLM');
    }
    $log("Returning result: " . var_export($result, true));

    return json_encode($result);
}
