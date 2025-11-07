<?php
// --- CONFIGURATION ---
$botToken = "8068485946:AAE-gDHxm6M4juSYuuzvlrVTwFmn3yXpQ7M";
$owner = "tradingrobots-web";
$repo2 = "ex5s";
$token = getenv("SHARE_FILE"); // your real GitHub Personal Access Token

$requiredGroups = [
    "@kingdivforexking",
    "@KingDivScalpersDen",
    "@KingDivChartMasters"
];

// --- FUNCTIONS ---
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

function github_download_filex($owner, $repo2, $path, $destination, $token) {
    $url = "https://raw.githubusercontent.com/$owner/$repo2/main/" . rawurlencode($path);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $token",
        "Accept: application/vnd.github+json"
    ]);
    $data = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status == 200 && $data) {
        file_put_contents($destination, $data, LOCK_EX);
        return true;
    }
    return false;
}

// --- TELEGRAM WEBHOOK HANDLER ---
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!isset($update["message"])) exit;
$message = $update["message"];
$chat_id = $message["chat"]["id"];
$user_id = $message["from"]["id"];
$first_name = $message["from"]["first_name"];
$text = trim($message["text"] ?? "");

// When user sends /start
if ($text == "/start") {
    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "📈 Join KingDiv Forex King", "url" => "https://t.me/kingdivforexking"]
            ],
            [
                ["text" => "💹 Join KingDiv Scalpers Den", "url" => "https://t.me/KingDivScalpersDen"]
            ],
            [
                ["text" => "📊 Join KingDiv Chart Masters", "url" => "https://t.me/KingDivChartMasters"]
            ],
            [
                ["text" => "✅ I’ve Joined All", "callback_data" => "check_join"]
            ]
        ]
    ];

    apiRequest("sendMessage", [
        "chat_id" => $chat_id,
        "text" => "👋 Hello $first_name!\nJoin all 3 groups below to get your trading tools 👇",
        "reply_markup" => $keyboard
    ]);
}

// --- CHECK IF USER JOINED ALL GROUPS ---
if (isset($update["callback_query"])) {
    $callback = $update["callback_query"];
    $chat_id = $callback["message"]["chat"]["id"];
    $user_id = $callback["from"]["id"];
    $data = $callback["data"];

    if ($data == "check_join") {
        global $requiredGroups;
        $allJoined = true;
        foreach ($requiredGroups as $group) {
            $res = file_get_contents("https://api.telegram.org/bot$botToken/getChatMember?chat_id=$group&user_id=$user_id");
            $info = json_decode($res, true);
            $status = $info["result"]["status"] ?? "left";
            if (!in_array($status, ["member", "administrator", "creator"])) {
                $allJoined = false;
                break;
            }
        }

        if ($allJoined) {
            apiRequest("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "🎉 Verified! Downloading your tools..."
            ]);

            // --- Download and Send Files ---
            $files = [
                "Kingdiv V1 2025.ex5",
                "KingDiv_Activator_Script 2.ex5"
            ];

            foreach ($files as $f) {
                $path = __DIR__ . "/$f";
                if (github_download_filex($owner, $repo2, $f, $path, $token)) {
                    apiRequest("sendDocument", [
                        "chat_id" => $user_id,
                        "document" => new CURLFile($path),
                        "caption" => "📦 $f delivered successfully!"
                    ]);
                }
            }
        } else {
            apiRequest("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "❌ Please make sure you’ve joined **all 3 groups** first!"
            ]);
        }
    }
}
?>
