<?php
// Telegram bot setup
$botToken = "8068485946:AAE-gDHxm6M4juSYuuzvlrVTwFmn3yXpQ7M";
$apiURL = "https://api.telegram.org/bot$botToken";

// GitHub repo setup
$owner = "tradingrobots-web";
$repo2 = "ex5s";
$token = getenv("SHARE_FILE");

// --- Helper: download file from GitHub private repo ---
function github_download_filex($owner, $repo2, $path, $destination, $token) {
    $url = "https://api.github.com/repos/$owner/$repo2/contents/" . rawurlencode($path);

    $headers = [
        "Authorization: token $token",
        "User-Agent: PHP-App",
        "Accept: application/vnd.github.v3.raw"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $data = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status == 200 && !empty($data)) {
        file_put_contents($destination, $data, LOCK_EX);
        return true;
    }
    return false;
}

// --- Telegram Webhook Handler ---
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$text = strtolower(trim($update["message"]["text"] ?? $update["callback_query"]["data"] ?? ''));

// --- Start command ---
if ($text === "/start") {
    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "📦 Download KingDiv Files", "callback_data" => "deliver_files"]
            ]
        ]
    ];

    $reply = [
        'chat_id' => $chat_id,
        'text' => "👋 Karibu!\n\nBonyeza button hapa chini kudownload files zako za KingDiv.",
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($keyboard)
    ];

    file_get_contents("$apiURL/sendMessage?" . http_build_query($reply));
    exit;
}

// --- Handle file delivery ---
if ($text === "deliver_files") {
    $file1 = sys_get_temp_dir() . "/Kingdiv V1 2025.ex5";
    $file2 = sys_get_temp_dir() . "/KingDiv_Activator_Script 2.ex5";
    $zipPath = sys_get_temp_dir() . "/KingDiv_Package.zip";

    $ok1 = github_download_filex($owner, $repo2, "Kingdiv V1 2025.ex5", $file1, $token);
    $ok2 = github_download_filex($owner, $repo2, "KingDiv_Activator_Script 2.ex5", $file2, $token);

    if ($ok1 && $ok2) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($file1, basename($file1));
            $zip->addFile($file2, basename($file2));
            $zip->close();
        }

        $postFields = [
            'chat_id' => $chat_id,
            'document' => new CURLFile($zipPath),
            'caption' => "✅ KingDiv files zako ziko tayari!\n\nAsante kwa kutumia Jikimu Bot.",
        ];

        $ch = curl_init("$apiURL/sendDocument");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    } else {
        file_get_contents("$apiURL/sendMessage?" . http_build_query([
            'chat_id' => $chat_id,
            'text' => "⚠️ Imeshindwa kupata files kutoka GitHub. Tafadhali jaribu tena.",
        ]));
    }
}
?>
