<?php
declare(strict_types=1);

function httpRequest(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 20): array
{
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('Unable to initialize HTTP client.');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_ENCODING => '',
        CURLOPT_POSTFIELDS => $body,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('HTTP request failed.');
    $json = json_decode($raw, true);
    return ['status'=>$status,'body'=>$raw,'json'=>is_array($json)?$json:null,'error'=>$error];
}
