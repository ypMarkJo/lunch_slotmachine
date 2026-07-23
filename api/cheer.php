<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

function load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);
        if ($line === "" || str_starts_with($line, "#") || !str_contains($line, "=")) {
            continue;
        }

        [$key, $value] = explode("=", $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        if ($key !== "" && getenv($key) === false) {
            putenv($key . "=" . $value);
            $_ENV[$key] = $value;
        }
    }
}

load_env(__DIR__ . "/../.env");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "허용되지 않은 요청 방식입니다."]);
    exit;
}

$message = trim((string)($_POST["message"] ?? ""));
$gifticon = $_FILES["gifticon"] ?? null;

if ($message === "" && ($gifticon === null || $gifticon["error"] !== UPLOAD_ERR_OK)) {
    echo json_encode(["success" => false, "error" => "응원 메시지나 기프티콘 이미지를 첨부해 주세요."]);
    exit;
}

$botToken = getenv("TELEGRAM_BOT_TOKEN") ?: ($_ENV["TELEGRAM_BOT_TOKEN"] ?: $_SERVER["TELEGRAM_BOT_TOKEN"] ?? "");
$chatId = getenv("TELEGRAM_CHAT_ID") ?: ($_ENV["TELEGRAM_CHAT_ID"] ?: $_SERVER["TELEGRAM_CHAT_ID"] ?? "");

if (!$botToken || !$chatId) {
    echo json_encode(["success" => false, "error" => ".env 파일에서 TELEGRAM_BOT_TOKEN 또는 TELEGRAM_CHAT_ID를 읽지 못했습니다."]);
    exit;
}

$caption = "🎁 [점심뭐묵칭코 개발자 응원]\n\n" . ($message !== "" ? $message : "(메시지 없음)");

if ($gifticon !== null && $gifticon["error"] === UPLOAD_ERR_OK && is_uploaded_file($gifticon["tmp_name"])) {
    $url = "https://api.telegram.org/bot" . $botToken . "/sendPhoto";
    $cfile = new CURLFile($gifticon["tmp_name"], $gifticon["type"] ?: "image/jpeg", $gifticon["name"] ?: "gifticon.jpg");
    $postFields = [
        "chat_id" => $chatId,
        "caption" => $caption,
        "photo"   => $cfile,
    ];
} else {
    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
    $postFields = [
        "chat_id" => $chatId,
        "text"    => $caption,
    ];
}

$responseErr = "";
if (function_exists("curl_init")) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($result !== false && $status === 200) {
        echo json_encode(["success" => true]);
        exit;
    }
    $responseErr = "HTTP " . $status . ($curlErr ? " (" . $curlErr . ")" : "") . " Res: " . (string)$result;
} else if ($gifticon === null) {
    $options = [
        "http" => [
            "header"  => "Content-Type: application/x-www-form-urlencoded\r\n",
            "method"  => "POST",
            "content" => http_build_query(["chat_id" => $chatId, "text" => $caption]),
            "timeout" => 15,
        ]
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result !== false) {
        echo json_encode(["success" => true]);
        exit;
    }
    $responseErr = "file_get_contents 실패";
}

echo json_encode(["success" => false, "error" => "텔레그램 전송 실패: " . $responseErr]);

