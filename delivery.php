<?php
// -------------------------------
// Telegram + GitHub Configuration
// -------------------------------
$botToken = "8068485946:AAE-gDHxm6M4juSYuuzvlrVTwFmn3yXpQ7M"; // your bot token
$apiURL   = "https://api.telegram.org/bot$botToken/";

$owner = "Bestyfx";          // your GitHub username
$repo2 = "ex5s";             // your private repo
$token = "ghp_yourPrivateTokenHere"; // <-- replace with your real GitHub PAT

// -------------------------------------
// Helper: Fetch file from private GitHub
// -------------------------------------
function github_download_filex($owner, $repo2, $path, $destination, $token) {
    $url = "https://raw.githubusercontent.com/$owner/$repo2/main/" . rawurlencode($path);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $token",
        "Accept: application/vnd.github.v3.raw"
    ]);
    $data = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status == 200 && !empty($data)) {
        file_put_contents($destination, $data, LOCK_EX);
        return true;
    }
    return false;
}

// -----------------------------
// Handle Telegram Webhook Input
// -----------------------------
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

$chatId  = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"] ?? null;
$message = $update["message"]["text"] ?? $update["callback_query"]["data"] ?? "";

// --------------------------------
// Step 1: /start → Ask to join all
// --------------------------------
if ($message == "/start") {
    $text = "👋 *Welcome to KingDiv Delivery Bot!*\n\n"
          . "Before you can download your indicators, please join these 3 groups:\n\n"
          . "1️⃣ [KingDiv Forex King](https://t.me/kingdivforexking)\n"
          . "2️⃣ [KingDiv Scalpers Den](https://t.me/KingDivScalpersDen)\n"
          . "3️⃣ [KingDiv Chart Masters](https://t.me/KingDivChartMasters)\n\n"
          . "Once done, tap the button below 👇";
    
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "✅ I’ve Joined All", "callback_data" => "joined_all"]]
        ]
    ];

    file_get_contents($apiURL . "sendMessage?" . http_build_query([
        "chat_id" => $chatId,
        "text" => $text,
        "parse_mode" => "Markdown",
        "reply_markup" => json_encode($keyboard)
    ]));
    exit;
}

// ----------------------------------
// Step 2: After user taps "Joined All"
// ----------------------------------
if ($message == "joined_all") {

    // Step 2.1 – Inform user
    file_get_contents($apiURL . "sendMessage?" . http_build_query([
        "chat_id" => $chatId,
        "text" => "🔍 Verifying and preparing your files, please wait..."
    ]));

    // Step 2.2 – Download files from private GitHub
    $file1 = sys_get_temp_dir() . "/Kingdiv_V1_2025.ex5";
    $file2 = sys_get_temp_dir() . "/KingDiv_Activator_Script_2.ex5";
    $zipPath = sys_get_temp_dir() . "/KingDiv_Indicators.zip";

    $ok1 = github_download_filex($owner, $repo2, "Kingdiv V1 2025.ex5", $file1, $token);
    $ok2 = github_download_filex($owner, $repo2, "KingDiv_Activator_Script 2.ex5", $file2, $token);

    if ($ok1 && $ok2) {
        // Step 2.3 – Create ZIP bundle
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFile($file1, "Kingdiv V1 2025.ex5");
            $zip->addFile($file2, "KingDiv_Activator_Script 2.ex5");
            $zip->close();
        }

        // Step 2.4 – Send ZIP directly to user
        $curlFile = new CURLFile($zipPath);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiURL . "sendDocument");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            "chat_id" => $chatId,
            "document" => $curlFile,
            "caption" => "📦 Here are your indicators from KingDiv!\n\nThank you for joining all our channels 👑"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        // Cleanup
        @unlink($file1);
        @unlink($file2);
        @unlink($zipPath);

    } else {
        // Step 2.5 – Send failure message
        file_get_contents($apiURL . "sendMessage?" . http_build_query([
            "chat_id" => $chatId,
            "text" => "⚠️ Sorry, failed to fetch files. Please contact admin."
        ]));
    }

    exit;
}
?>
