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

// --- PROMOTIONAL MESSAGES ---
$promoMessages = [
    "👑 *KingDiv Price Action System* — engineered for traders who demand precision.  
Each plotted level is a reflection of real price memory, and the *arrow buffers* reveal momentum shifts. 🔥",

    "📈 *The KingDiv Indicator* doesn’t guess — it calculates.  
Reaction zones and *arrow buffers* expose hidden institutional flow. 💎",

    "🎯 Forget noise. Forget delay. KingDiv reads history and projects forward with precision. 🚀",

    "💹 *Smart money doesn’t chase candles — it anticipates reactions.*  
Plotted lines show where price *will* respect; arrows confirm *when* to act. ⚡",

    "🔥 *Price reacts — KingDiv predicts.*  
Draws high-confidence reaction lines and directional arrows. 📊",

    "🧠 KingDiv studies market behavior to identify future reaction zones.  
Plotted arrows mark *smart entries*, horizontal buffers double as take-profit zones. 💰",

    "💼 *Built for traders who treat trading like a business.*  
Arrow buffers provide confirmation signals integrating with price action. 👁️",

    "🚀 *Precision isn’t optional — it’s essential.*  
Every arrow and line is statistically weighted from past market reactions. 💎"
];

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

            // After files, send activation directions
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

            // --- POST TO CHANNELS ---
            $channels = [
                "@kingdivforexking",
                "@KingDivScalpersDen",
                "@KingDivChartMasters"
            ];

            $promo = $promoMessages[array_rand($promoMessages)] . "\n\n👉 Download and activate your indicators via @PFTDeliverybot";

            foreach ($channels as $channel) {
                apiRequest("sendMessage", [
                    "chat_id" => $channel,
                    "text" => $promo,
                    "parse_mode" => "Markdown"
                ]);
                sleep(3); // small delay between channels
            }

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
