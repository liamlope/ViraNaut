<?php
require_once 'config.php';
function telegram($method, $datas = [],$botToken = null)
{
    global $ApiToken;
    if($botToken != null){
        $ApiToken = $botToken;
    }
    $url = "https://api.telegram.org/bot" . $ApiToken . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        error_log('vpnbot telegram curl: ' . curl_error($ch));
        curl_close($ch);
        return ['ok' => false];
    }
    curl_close($ch);
    return json_decode($res, true);
}
function sendmessage($chat_id, $text, $keyboard, $parse_mode, $bot_token = null)
{
    if (!is_string($text)) {
        $text = (string) $text;
    }
    $limit = 4096;
    if (mb_strlen($text, 'UTF-8') <= $limit) {
        return telegram('sendmessage', [
            'chat_id' => $chat_id,
            'text' => $text,
            'reply_markup' => $keyboard,
            'parse_mode' => $parse_mode,
        ], $bot_token);
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
        $lastResponse = telegram('sendmessage', [
            'chat_id' => $chat_id,
            'text' => $chunk,
            'reply_markup' => $markup,
            'parse_mode' => $parse_mode,
        ], $bot_token);
        if (empty($lastResponse['ok'])) {
            break;
        }
    }
    return $lastResponse;
}
function sendDocument($chat_id, $documentPath, $caption) {
        telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document' => new CURLFile($documentPath),
        'caption' => $caption,
        ]);
}

function forwardMessage($chat_id,$message_id,$chat_id_user){
    telegram('forwardMessage',[
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
function Editmessagetext($chat_id, $message_id, $text, $keyboard, $parse_mode = 'HTML')
{
    if (!is_string($text)) {
        $text = (string) $text;
    }
    if (mb_strlen($text, 'UTF-8') > 4096) {
        $text = mb_substr($text, 0, 4095, 'UTF-8') . '…';
    }
    return telegram('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,

    ]);
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
#-----------------------------#
$update = json_decode(file_get_contents("php://input"), true);
$from_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? $update["inline_query"]['from']['id'] ?? 0;
$Chat_type = $update["message"]["chat"]["type"] ?? $update['callback_query']['message']['chat']['type'] ?? '';
$text = $update["message"]["text"]  ?? '';
$text =convertPersianNumbersToEnglish($text);
$text_inline = $update["callback_query"]["message"]['text'] ?? '';
$message_id = $update["message"]["message_id"] ?? $update["callback_query"]["message"]["message_id"] ?? 0;
$photo = $update["message"]["photo"] ?? 0;
$document = $update["message"]["document"] ?? 0;
$fileid = $update["message"]["document"]["file_id"] ?? 0;
$photoid = $photo ? end($photo)["file_id"] : '';
$caption = $update["message"]["caption"] ?? '';
$video = $update["message"]["video"] ?? 0;
$videoid = $video ? $video["file_id"] : 0;
$forward_from_id = $update["message"]["reply_to_message"]["forward_from"]["id"] ?? 0;
$datain = $update["callback_query"]["data"] ?? '';
$first_name = $update['message']['from']['first_name']  ?? $update["callback_query"]["from"]["first_name"] ?? $update["inline_query"]['from']['first_name'] ?? '';
$username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? $update["callback_query"]["from"]["username"] ?? 'NOT_USERNAME';
$user_phone =$update["message"]["contact"]["phone_number"] ?? 0;
$contact_id = $update["message"]["contact"]["user_id"] ?? 0;
$callback_query_id = $update["callback_query"]["id"] ?? 0;
$inline_query_id = $update["inline_query"]["id"] ?? 0;
$query = $update["inline_query"]["query"] ?? 0;