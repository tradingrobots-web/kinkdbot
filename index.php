<?php
// === CONFIG ===
$botToken  = "8311328395:AAFZn0ljyLwZu1mTOkMJMMgcsppufJ5g_JE";
$apiURL    = "https://api.telegram.org/bot$botToken/";
$owner     = "tradingrobots-web";
$repo2     = "ex5s"; // repo containin

// === FUNCTIONS ===
function send_msg($apiURL, $chat_id, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL . "sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function send_document($apiURL, $chat_id, $file_path, $caption = "") {
    $ch = curl_init();
    $cfile = new CURLFile(realpath($file_path));
    $data = [
        'chat_id' => $chat_id,
        'document' => $cfile,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    curl_setopt($ch, CURLOPT_URL, $apiURL . "sendDocument");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// ✅ Fixed version for large binary files
function github_download_file($owner, $repo, $path, $destination) {
    $url = "https://raw.githubusercontent.com/$owner/$repo/main/" . rawurlencode($path);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    curl_setopt($ch, CURLOPT_HEADER, false);

    $data = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($status == 200 && strpos($contentType, 'text/html') === false) {
        file_put_contents($destination, $data, LOCK_EX);
        return true;
    }
    return false;
}

// === MAIN ===
$content = file_get_contents("php://input");
$update  = json_decode($content, true);
if (!$update) exit;

$chat_id = $update["message"]["chat"]["id"];
$text    = trim($update["message"]["text"] ?? "");

// === KEYBOARD ===
$mainKeyboard = [
    "keyboard" => [
        [["text" => "⬇️ Download Indicator & Script"]],
        [["text" => "⬅️ Back to Menu"]],
    ],
    "resize_keyboard" => true
];

// === START HANDLER ===
if ($text == "/start" || $text == "⬅️ Back to Menu") {
    send_msg($apiURL, $chat_id,
        "👋 *Welcome to KingDiv Tools!*\n\n"
      . "Tap the button below to download your latest *Indicator* and *Activator Script* files 📦👇",
        $mainKeyboard
    );
    exit;
}

// === DOWNLOAD HANDLER ===
if ($text == "⬇️ Download Indicator & Script") {
    $indicator_file = __DIR__ . "/Kingdiv V1 2025.ex5";
    $script_file    = __DIR__ . "/KingDiv_Activator_Script 2.ex5";

    if (!file_exists($indicator_file)) {
        github_download_file($owner, $repo2, "Kingdiv V1 2025.ex5", $indicator_file);
    }
    if (!file_exists($script_file)) {
        github_download_file($owner, $repo2, "KingDiv_Activator_Script 2.ex5", $script_file);
    }

    if (filesize($indicator_file) < 1000) {
        send_msg($apiURL, $chat_id, "⚠️ Indicator download failed or incomplete.");
    } else {
        send_document($apiURL, $chat_id, $indicator_file, "📈 *KingDiv Indicator* — attach it to your chart for real-time signals.");
    }

    if (filesize($script_file) < 1000) {
        send_msg($apiURL, $chat_id, "⚠️ Activator Script download failed or incomplete.");
    } else {
        send_document($apiURL, $chat_id, $script_file, "⚙️ *KingDiv Activator Script* — run this to activate your license.");
    }

    exit;
}
?>
