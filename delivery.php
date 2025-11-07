<?php
// --- Telegram Configuration ---
$botToken = "8068485946:AAE-gDHxm6M4juSYuuzvlrVTwFmn3yXpQ7M";
$apiURL = "https://api.telegram.org/bot$botToken";

// --- Telegram Webhook Handler ---
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$text = strtolower(trim($update["message"]["text"] ?? $update["callback_query"]["data"] ?? ''));

// --- Start Command ---
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
        'reply_markup' => json_encode($keyboard)
    ];

    file_get_contents("$apiURL/sendMessage?" . http_build_query($reply));
    exit;
}

// --- Deliver Local Files ---
if ($text === "deliver_files") {
    $basePath = __DIR__; // folder ya sasa
    $file1 = "$basePath/Kingdiv V1 2025.ex5";
    $file2 = "$basePath/KingDiv_Activator_Script 2.ex5";

    // Check kama files zipo
    if (file_exists($file1) && file_exists($file2)) {
        // Tuma file 1
        $post1 = [
            'chat_id' => $chat_id,
            'document' => new CURLFile($file1),
            'caption' => "✅ Kingdiv V1 2025.ex5 imewasilishwa kikamilifu!"
        ];
        $ch = curl_init("$apiURL/sendDocument");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        // Tuma file 2
        $post2 = [
            'chat_id' => $chat_id,
            'document' => new CURLFile($file2),
            'caption' => "✅ KingDiv_Activator_Script 2.ex5 imewasilishwa kikamilifu!"
        ];
        $ch2 = curl_init("$apiURL/sendDocument");
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, $post2);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch2);
        curl_close($ch2);
    } else {
        file_get_contents("$apiURL/sendMessage?" . http_build_query([
            'chat_id' => $chat_id,
            'text' => "⚠️ File(s) hazionekani kwenye server. Hakikisha ziko kwenye folder sawa na delivery.php"
        ]));
    }
}
?>
