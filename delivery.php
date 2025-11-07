<?php
// --- TELEGRAM CONFIGURATION ---
$botToken = "8068485946:AAE-gDHxm6M4juSYuuzvlrVTwFmn3yXpQ7M";
$apiURL = "https://api.telegram.org/bot$botToken";

$requiredGroups = [
    "@kingdivforexking",
    "@KingDivScalpersDen",
    "@KingDivChartMasters"
];

// --- PROMOTIONAL MESSAGES ---
$promoMessages = [
    "👑 *KingDiv Price Action System* — engineered for traders who demand precision.  
This isn’t repaint junk — it’s an adaptive model built from *7 years of algorithmic backtesting*.  
Each plotted level is a reflection of real price memory, and the *arrow buffers* reveal where momentum is shifting before the crowd sees it.  
Watch price *react*, not just move. 🔥",

    "📈 *The KingDiv Indicator* doesn’t guess — it calculates.  
By scanning historical market structure, it plots *reaction zones* and *arrow buffers* that expose hidden institutional flow.  
When those arrows align with your price action bias, entries feel almost unfair.  
Pure logic, zero lag, complete dominance. 💎",

    "🎯 Forget noise. Forget delay. KingDiv reads history and projects forward with precision.  
Each arrow plotted is backed by *confirmed volatility zones*, giving you sniper-level accuracy for entries and exits.  
Combine it with structure-based trading — and you’re playing on another level. 🚀",

    "💹 *Smart money doesn’t chase candles — it anticipates reactions.*  
KingDiv’s algorithm traces past liquidity zones to forecast the next pivot.  
The plotted lines show where price *will* respect; the arrows confirm *when* to act.  
That’s not theory — it’s data in motion. ⚡",

    "🔥 *Price reacts — KingDiv predicts.*  
This indicator combines price action and historical validation to draw high-confidence reaction lines and directional arrows.  
It’s like having a data-trained analyst on your chart, marking your next target and TP before the candle even forms. 📊",

    "🧠 You’ve seen hundreds of tools — but none like this.  
KingDiv studies the market’s behavioral footprint — not random math — to identify future reaction zones.  
The plotted arrows mark *smart entries*, and the horizontal buffers double as *realistic take-profit zones*.  
It’s not hype. It’s consistency. 💰",

    "💼 *Built for traders who treat trading like a business.*  
KingDiv’s arrow buffers aren’t just visuals — they’re engineered confirmation signals that integrate seamlessly with pure price action.  
This is institutional logic simplified into visual clarity.  
You’ll never look at your charts the same way again. 👁️",

    "🚀 *Precision isn’t optional — it’s essential.*  
Every arrow and line KingDiv plots is statistically weighted from past market reactions.  
It gives you the roadmap, the reaction points, and the confidence to hold or exit without emotion.  
That’s how professionals trade — calculated, calm, and profitable. 💎"
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

// --- CHECK USER JOINED ALL GROUPS ---
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

// --- MAIN WEBHOOK HANDLER ---
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) exit;

$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$user_id = $update["message"]["from"]["id"] ?? $update["callback_query"]["from"]["id"];
$text = strtolower(trim($update["message"]["text"] ?? $update["callback_query"]["data"] ?? ''));

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

            // Send activation directions
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

            // --- POST RANDOM PROMO TO ONE RANDOM CHANNEL ---
            $channels = [
                "@kingdivforexking",
                "@KingDivScalpersDen",
                "@KingDivChartMasters"
            ];
            $randomChannel = $channels[array_rand($channels)];
            $randomMessage = $promoMessages[array_rand($promoMessages)] . "\n\n👉 Download and activate your indicators via @PFTDeliverybot";

            apiRequest("sendMessage", [
                "chat_id" => $randomChannel,
                "text" => $randomMessage,
                "parse_mode" => "Markdown"
            ]);

        } else {
            apiRequest("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "⚠️ Files not found on the server. Please contact support."
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
            "text" => "🚫 You haven’t joined all the required KingDiv groups yet.\n\nPlease join all 3 and send /start again to get your downloads.",
            "parse_mode" => "Markdown",
            "reply_markup" => $keyboard
        ]);
    }
}
?>
