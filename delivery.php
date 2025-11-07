<?php
// --- TELEGRAM CONFIGURATION ---
$botToken = "8068485946:AAE-gDHxm6M4juSYuuzvlrVTwFmn3yXpQ7M";
$apiURL = "https://api.telegram.org/bot$botToken";

$requiredGroups = [
    "@kingdivforexking",
    "@KingDivScalpersDen",
    "@KingDivChartMasters"
];

// --- HELPER FUNCTION ---
function apiRequest($method, $data = []) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/$method";
    $options = [
        'http' => [
            'header' => "Content-Type: application/json",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    return json_decode(file_get_contents($url, false, stream_context_create($options)), true);
}

// --- MAIN WEBHOOK HANDLER ---
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) exit;

$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$user_id = $update["message"]["from"]["id"] ?? $update["callback_query"]["from"]["id"];
$text = strtolower(trim($update["message"]["text"] ?? $update["callback_query"]["data"] ?? ''));

// --- AUTO JOIN CHECK FUNCTION ---
function hasJoinedAllGroups($user_id, $requiredGroups, $botToken) {
    foreach ($requiredGroups as $group) {
        $res = file_get_contents("https://api.telegram.org/bot$botToken/getChatMember?chat_id=$group&user_id=$user_id");
        $info = json_decode($res, true);
        $status = $info["result"]["status"] ?? "left";
        if (!in_array($status, ["member", "administrator", "creator"])) {
            return false;
        }
    }
    return true;
}

// --- /START COMMAND ---
if ($text === "/start") {

    $joinedAll = hasJoinedAllGroups($user_id, $requiredGroups, $botToken);

    if ($joinedAll) {
        // ✅ USER ALREADY IN ALL GROUPS
        apiRequest("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "🎉 *Welcome back, trader!* Your membership is verified.\nDelivering your KingDiv tools...",
            "parse_mode" => "Markdown"
        ]);

        $basePath = __DIR__;
        $file1 = "$basePath/Kingdiv V1 2025.ex5";
        $file2 = "$basePath/KingDiv_Activator_Script 2.ex5";

        if (file_exists($file1) && file_exists($file2)) {
            // Send first file
            $ch1 = curl_init("$apiURL/sendDocument");
            curl_setopt($ch1, CURLOPT_POST, true);
            curl_setopt($ch1, CURLOPT_POSTFIELDS, [
                'chat_id' => $chat_id,
                'document' => new CURLFile($file1),
                'caption' => "📦 *KingDiv V1 2025.ex5* delivered successfully!",
                'parse_mode' => 'Markdown'
            ]);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch1);
            curl_close($ch1);

            // Send second file
            $ch2 = curl_init("$apiURL/sendDocument");
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, [
                'chat_id' => $chat_id,
                'document' => new CURLFile($file2),
                'caption' => "📦 *KingDiv_Activator_Script 2.ex5* delivered successfully!",
                'parse_mode' => 'Markdown'
            ]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch2);
            curl_close($ch2);

            // After both files, send activation directions
            $activationKeyboard = [
                "inline_keyboard" => [
                    [["text" => "⚙️ Get Activation Details", "url" => "https://t.me/Kinkdbot"]]
                ]
            ];

            apiRequest("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "✅ *Files successfully delivered!*\n\nTo activate your indicators, please proceed to [@Kinkdbot](https://t.me/Kinkdbot).",
                "parse_mode" => "Markdown",
                "reply_markup" => $activationKeyboard
            ]);
        } else {
            apiRequest("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "⚠️ Files not found on the server. Please contact support for assistance."
            ]);
        }
    } else {
        // ❌ USER HAS NOT JOINED ALL GROUPS
        $keyboard = [
            "inline_keyboard" => [
                [["text" => "📈 Join KingDiv Forex King", "url" => "https://t.me/kingdivforexking"]],
                [["text" => "💹 Join KingDiv Scalpers Den", "url" => "https://t.me/KingDivScalpersDen"]],
                [["text" => "📊 Join KingDiv Chart Masters", "url" => "https://t.me/KingDivChartMasters"]],
            ]
        ];

        apiRequest("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "🚫 You haven’t joined all the required KingDiv groups yet.\n\nPlease join all 3 below, then send /start again to get your downloads.",
            "parse_mode" => "Markdown",
            "reply_markup" => $keyboard
        ]);
    }
}
?>
