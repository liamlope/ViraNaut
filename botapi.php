<?php
require_once 'config.php';
function telegram($method, $datas = [], $token = null)
{
    global $APIKEY;

    $token = $token === null ? $APIKEY : $token;
    $url = "https://api.telegram.org/bot" . $token . "/" . $method;

    if (isset($datas['message_thread_id']) && intval($datas['message_thread_id']) <= 0) {
        unset($datas['message_thread_id']);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        error_log('Unable to initialise cURL for Telegram request.');
        return [
            'ok' => false,
            'description' => 'Unable to initialise cURL for Telegram request.'
        ];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);

    $rawResponse = curl_exec($ch);
    if ($rawResponse === false) {
        $curlError = curl_error($ch);

        if ($curlError !== '') {
            error_log('Telegram request failed: ' . $curlError);
        }

        return [
            'ok' => false,
            'description' => $curlError !== '' ? $curlError : 'Telegram request failed.'
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $decodedResponse = json_decode($rawResponse, true);
    if (!is_array($decodedResponse)) {
        $logSnippet = substr($rawResponse, 0, 200);
        error_log(sprintf('Invalid response from Telegram API (HTTP %d): %s', $httpCode, $logSnippet));

        return [
            'ok' => false,
            'error_code' => $httpCode,
            'description' => 'Invalid response received from Telegram.'
        ];
    }

    if (isset($decodedResponse['ok']) && !$decodedResponse['ok']) {
        $desc = (string) ($decodedResponse['description'] ?? '');
        $ignored = [
            'message is not modified',
            'message to delete not found',
            'message can\'t be deleted',
            'message to edit not found',
            'query is too old',
            'query id is invalid',
            'query is too old and response timeout expired',
            'query has already been answered',
            'message text is empty',
        ];
        $skipLog = false;
        foreach ($ignored as $needle) {
            if (stripos($desc, $needle) !== false) {
                $skipLog = true;
                break;
            }
        }
        if (!$skipLog) {
            error_log(json_encode($decodedResponse));
        }
    }

    return $decodedResponse;
}
function sendmessage($chat_id, $text, $keyboard, $parse_mode, $bot_token = null, $entities = null)
{
    if (intval($chat_id) == 0) {
        return ['ok' => false];
    }
    if ($entities === null && is_string($text) && strpos($text, '{emoji:') !== false && function_exists('mirza_prepare_outgoing_text')) {
        $prepared = mirza_prepare_outgoing_text($text, $parse_mode);
        $text = $prepared['text'];
        $parse_mode = $prepared['parse_mode'];
        $entities = $prepared['entities'];
    }
    if (!is_string($text)) {
        $text = (string) $text;
    }
    if (trim($text) === '') {
        return ['ok' => false, 'description' => 'message text is empty'];
    }
    $limit = 4096;
    if (mb_strlen($text, 'UTF-8') <= $limit) {
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'reply_markup' => $keyboard,
        ];
        if ($entities !== null && $entities !== '') {
            $data['entities'] = is_string($entities) ? $entities : json_encode($entities, JSON_UNESCAPED_UNICODE);
        }
        if ($parse_mode !== null && $parse_mode !== '') {
            $data['parse_mode'] = $parse_mode;
        }
        return telegram('sendmessage', $data, $bot_token);
    }

    $chunks = [];
    $remaining = $text;
    while (mb_strlen($remaining, 'UTF-8') > $limit) {
        $slice = mb_substr($remaining, 0, $limit, 'UTF-8');
        $breakAt = max(
            (int) mb_strrpos($slice, "\n\n", 0, 'UTF-8'),
            (int) mb_strrpos($slice, "\n", 0, 'UTF-8'),
            (int) mb_strrpos($slice, ' ', 0, 'UTF-8')
        );
        if ($breakAt > (int) ($limit * 0.5)) {
            $part = mb_substr($remaining, 0, $breakAt, 'UTF-8');
            $remaining = mb_substr($remaining, $breakAt, null, 'UTF-8');
        } else {
            $part = $slice;
            $remaining = mb_substr($remaining, $limit, null, 'UTF-8');
        }
        $chunks[] = trim($part);
    }
    if (trim($remaining) !== '') {
        $chunks[] = trim($remaining);
    }

    $lastResponse = ['ok' => false];
    $chunkCount = count($chunks);
    foreach ($chunks as $index => $chunk) {
        $markup = ($index === $chunkCount - 1) ? $keyboard : null;
        $chunkEntities = ($index === 0) ? $entities : null;
        $chunkParse = ($chunkEntities !== null || $parse_mode === null || $parse_mode === '') ? $parse_mode : null;
        $data = [
            'chat_id' => $chat_id,
            'text' => $chunk,
            'reply_markup' => $markup,
        ];
        if ($chunkEntities !== null && $chunkEntities !== '') {
            $data['entities'] = is_string($chunkEntities) ? $chunkEntities : json_encode($chunkEntities, JSON_UNESCAPED_UNICODE);
        } elseif ($chunkParse !== null && $chunkParse !== '') {
            $data['parse_mode'] = $chunkParse;
        }
        $lastResponse = telegram('sendmessage', $data, $bot_token);
        if (empty($lastResponse['ok'])) {
            break;
        }
    }
    return $lastResponse;
}
function sendDocument($chat_id, $documentPath, $caption) {
        return telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document' => new CURLFile($documentPath),
        'caption' => $caption,
        ]);
}

function forwardMessage($chat_id,$message_id,$chat_id_user){
    return telegram('forwardMessage',[
        'from_chat_id'=> $chat_id,
        'message_id'=> $message_id,
        'chat_id'=> $chat_id_user,
    ]);
}
function sendphoto($chat_id,$photoid,$caption){
    telegram('sendphoto',[
        'chat_id' => $chat_id,
        'photo'=> $photoid,
        'caption'=> $caption,
    ]);
}
function sendvideo($chat_id,$videoid,$caption){
    telegram('sendvideo',[
        'chat_id' => $chat_id,
        'video'=> $videoid,
        'caption'=> $caption,
    ]);
}
function senddocumentsid($chat_id,$documentid,$caption){
    telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document'=> $documentid,
        'caption'=> $caption,
    ]);
}
function mirza_answer_callback_query(?string $callback_query_id, string $text = '', bool $showAlert = false): void
{
    static $answered = [];
    if ($callback_query_id === null || $callback_query_id === '') {
        return;
    }
    if (isset($answered[$callback_query_id])) {
        return;
    }
    $answered[$callback_query_id] = true;
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'show_alert' => $showAlert,
        'cache_time' => 5,
    ]);
}

function mirza_telegram_error_description($response): string
{
    if (!is_array($response)) {
        return 'Unknown error';
    }
    if (!empty($response['description'])) {
        return (string) $response['description'];
    }
    if (!empty($response['error_code'])) {
        return 'Telegram error ' . $response['error_code'];
    }
    return 'Unknown error';
}

function Editmessagetext($chat_id, $message_id, $text, $keyboard, $parse_mode = 'HTML')
{
    if ($text === null || trim((string) $text) === '') {
        return ['ok' => false, 'description' => 'message text is empty'];
    }
    $entities = null;
    if (is_string($text) && strpos($text, '{emoji:') !== false && function_exists('mirza_prepare_outgoing_text')) {
        $prepared = mirza_prepare_outgoing_text($text, $parse_mode);
        $text = $prepared['text'];
        $parse_mode = $prepared['parse_mode'];
        $entities = $prepared['entities'];
    }
    if (is_string($text) && mb_strlen($text, 'UTF-8') > 4096) {
        $text = mb_substr($text, 0, 4090, 'UTF-8') . '…';
    }
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => $keyboard,
    ];
    if ($entities !== null && $entities !== '') {
        $data['entities'] = is_string($entities) ? $entities : json_encode($entities, JSON_UNESCAPED_UNICODE);
    }
    if ($parse_mode !== null && $parse_mode !== '') {
        $data['parse_mode'] = $parse_mode;
    }
    return telegram('editmessagetext', $data);
}
 function deletemessage($chat_id, $message_id){
  telegram('deletemessage', [
'chat_id' => $chat_id, 
'message_id' => $message_id,
]);
 }
function getFileddire($photoid){
  return telegram('getFile', [
'file_id' => $photoid, 
]);
 }
function pinmessage($from_id,$message_id){
  return telegram('pinChatMessage', [
'chat_id' => $from_id, 
'message_id' => $message_id, 
]);
 }
 function unpinmessage($from_id){
  return telegram('unpinAllChatMessages', [
'chat_id' => $from_id, 
]);
 }
  function answerInlineQuery($inline_query_id,$results){
  return telegram('answerInlineQuery', [
      "inline_query_id" => $inline_query_id,
        "results" => json_encode($results)
]);
 }
function convertPersianNumbersToEnglish($string) {
    $persian_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($persian_numbers, $english_numbers, $string);
}

function isDuplicateUpdate($updateId)
{
    if (!is_numeric($updateId) || $updateId <= 0) {
        return false;
    }

    $cacheDir = __DIR__ . '/storage/cache';
    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
        return false;
    }

    $cacheFile = $cacheDir . '/recent_updates.json';
    $handle = fopen($cacheFile, 'c+');
    if ($handle === false) {
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        $recentUpdates = $contents ? json_decode($contents, true) : [];
        if (!is_array($recentUpdates)) {
            $recentUpdates = [];
        }

        $now = time();
        $timeToLive = 120; // seconds

        // Drop expired entries
        foreach ($recentUpdates as $id => $timestamp) {
            if (!is_numeric($timestamp) || ($now - (int)$timestamp) > $timeToLive) {
                unset($recentUpdates[$id]);
            }
        }

        if (array_key_exists($updateId, $recentUpdates)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return true;
        }

        $recentUpdates[$updateId] = $now;

        // keep size reasonable
        if (count($recentUpdates) > 200) {
            asort($recentUpdates);
            $recentUpdates = array_slice($recentUpdates, -200, null, true);
        }

        $encoded = json_encode($recentUpdates);
        if ($encoded !== false) {
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded);
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    } catch (Throwable $e) {
        try {
            flock($handle, LOCK_UN);
        } catch (Throwable $ignored) {
        }
        fclose($handle);
        return false;
    }

    return false;
}
// #-----------------------------#
$rawUpdate = file_get_contents('php://input');
$update = (is_string($rawUpdate) && $rawUpdate !== '') ? json_decode($rawUpdate, true) : null;
if (!is_array($update)) {
    $update = [];
}
$update_id = $update['update_id'] ?? 0;
if (isDuplicateUpdate($update_id)) {
    http_response_code(200);
    exit;
}
$from_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? $update["inline_query"]['from']['id'] ?? 0;
$time_message = $update['message']['date'] ?? $update['callback_query']['date'] ?? $update["inline_query"]['date'] ?? 0;
$is_bot = $update['message']['from']['is_bot'] ?? false;
$chat_member = $update['chat_member'] ?? null;
$language_code = strtolower($update['message']['from']['language_code'] ?? $update['callback_query']['from']['language_code'] ?? "fa");
$Chat_type = $update["message"]["chat"]["type"] ?? $update['callback_query']['message']['chat']['type'] ?? '';
$text = $update["message"]["text"]  ?? '';
if(isset($update['pre_checkout_query'])){
    $Chat_type = "private";
    $from_id = $update['pre_checkout_query']['from']['id'];
}
$text =convertPersianNumbersToEnglish($text);
$text_inline = $update["callback_query"]["message"]['text'] ?? '';
$message_id = $update["message"]["message_id"] ?? $update["callback_query"]["message"]["message_id"] ?? 0;
$time_message = $update["message"]["date"] ?? $update["callback_query"]["date"] ?? 0;
$photo = $update["message"]["photo"] ?? 0;
$document = $update["message"]["document"] ?? 0;
$fileid = $update["message"]["document"]["file_id"] ?? 0;
$photoid = $photo ? end($photo)["file_id"] : '';
$caption = $update["message"]["caption"] ?? '';
$video = $update["message"]["video"] ?? 0;
$videoid = $video ? $video["file_id"] : 0;
$forward_from_id = $update["message"]["reply_to_message"]["forward_from"]["id"] ?? 0;
$datain = $update["callback_query"]["data"] ?? '';
$last_name = $update['message']['from']['last_name']  ?? $update["callback_query"]["from"]["last_name"] ?? $update["inline_query"]['from']['last_name'] ?? '';
$first_name = $update['message']['from']['first_name']  ?? $update["callback_query"]["from"]["first_name"] ?? $update["inline_query"]['from']['first_name'] ?? '';
$username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? $update["callback_query"]["from"]["username"] ?? 'NOT_USERNAME';
$user_phone =$update["message"]["contact"]["phone_number"] ?? 0;
$contact_id = $update["message"]["contact"]["user_id"] ?? 0;
$callback_query_id = $update["callback_query"]["id"] ?? 0;
$inline_query_id = $update["inline_query"]["id"] ?? 0;
$query = $update["inline_query"]["query"] ?? 0;