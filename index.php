<?php
// === CONFIG ===
$botToken  = "8311328395:AAFZn0ljyLwZu1mTOkMJMMgcsppufJ5g_JE";
$apiURL    = "https://api.telegram.org/bot$botToken/";
$owner     = "tradingrobots-web";
$repo2     = "ex5s"; // repo containing EX5 files

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

function github_download_file($owner, $repo, $path, $destination) {
    $url = "https://raw.githubusercontent.com/$owner/$repo/main/" . urlencode($path);
    $ch = curl_init($url);
    $fp = fopen($destination, 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return file_exists($destination);
}

// === MAIN ===
$content = file_get_contents("php://input");
$update  = json_decode($content, true);
if (!$update) exit;

$chat_id = $update["message"]["chat"]["id"];
$text    = trim($update["message"]["text"] ?? "");

// === MAIN MENU KEYBOARD ===
$mainKeyboard = [
    "keyboard" => [
        [["text" => "⬇️ Download Indicator & Script"]],
        [["text" => "⬅️ Back to Menu"]],
    ],
    "resize_keyboard" => true
];

// === START / MENU HANDLER ===
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

    // Download from GitHub if missing
    if (!file_exists($indicator_file)) {
        github_download_file($owner, $repo2, "Kingdiv V1 2025.ex5", $indicator_file);
    }
    if (!file_exists($script_file)) {
        github_download_file($owner, $repo2, "KingDiv_Activator_Script 2.ex5", $script_file);
    }

    // Send both files
    if (file_exists($indicator_file)) {
        send_document($apiURL, $chat_id, $indicator_file, "📈 *KingDiv Indicator* — attach it to your chart for real-time signals.");
    } else {
        send_msg($apiURL, $chat_id, "⚠️ Indicator file not found.");
    }

    if (file_exists($script_file)) {
        send_document($apiURL, $chat_id, $script_file, "⚙️ *KingDiv Activator Script* — run this to activate your license.");
    } else {
        send_msg($apiURL, $chat_id, "⚠️ Activator script file not found.");
    }

    // Optional: delete after sending to keep server clean
    // unlink($indicator_file);
    // unlink($script_file);

    exit;
}
?>
