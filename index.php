<?
// ==== KingDiv Premium Bot — Binance Alias + GitHub Storage ====

// =================== CONFIG ===================
$owner = "Bestyfx";
$repo  = "majsons";
$repo2  = "ex5s";
$userDataPath = "user_data.json";
$revenuesPath = "revenues.json";
$photo_path     = __DIR__ . "/binance_id.png";
$token = getenv("GITHUB_TOKEN"); // Render environment variable

$headers = [
    "Authorization: token $token",
    "User-Agent: PHP-App",
    "Accept: application/vnd.github+json"
];

// =================== TELEGRAM CONFIG ===================
$botToken = "8311328395:AAFZn0ljyLwZu1mTOkMJMMgcsppufJ5g_JE";
$apiURL   = "https://api.telegram.org/bot$botToken/";
$webhookURL = "https://teletestgrrrrrrrrrrrrrr-1.onrender.com/bot.php";

$binancePayPage = "https://www.binance.com/en/my/wallet/account/payment/send?hl=en";
$binanceID = "826083690";

// ====== GITHUB HELPERS ======
function github_fetch($owner, $repo, $path, $headers) {
    $url = "https://api.github.com/repos/$owner/$repo/contents/$path";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return [[], null];
    $data = json_decode($res, true);
    if (isset($data["content"])) {
        return [json_decode(base64_decode($data["content"]), true), $data["sha"]];
    }
    return [[], null];
}

function github_save($owner, $repo, $path, $data, $sha, $headers) {
    $url = "https://api.github.com/repos/$owner/$repo/contents/$path";
    $payload = json_encode([
        "message" => "update " . $path,
        "content" => base64_encode(json_encode($data, JSON_PRETTY_PRINT)),
        "sha" => $sha
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function find_balance_by_alias($revenues, $alias) {
    foreach ($revenues as $entry) {
        if (strcasecmp($entry["user_id"], $alias) == 0) {
            return floatval($entry["total_amount"]);
        }
    }
    return 0;
}
function send_photo($apiURL, $chat_id, $photo_path, $caption) {
    $post_fields = [
        'chat_id' => $chat_id,
        'photo' => new CURLFile($photo_path),
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    $ch = curl_init($apiURL . "sendPhoto");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_fields,
        CURLOPT_RETURNTRANSFER => true
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function update_balance_by_alias(&$revenues, $alias, $amount) {
    foreach ($revenues as &$entry) {
        if (strcasecmp($entry["user_id"], $alias) == 0) {
            $entry["total_amount"] = max(0, floatval($entry["total_amount"]) - $amount);
            return true;
        }
    }
    return false;
}

function send_msg($apiURL, $chat_id, $text, $keyboard = null) {
    $payload = [
        "chat_id" => $chat_id,
        "text" => $text,
        "parse_mode" => "Markdown",
        "disable_web_page_preview" => false
    ];
    if ($keyboard) $payload["reply_markup"] = json_encode($keyboard);
    file_get_contents($apiURL . "sendMessage?" . http_build_query($payload));
}

///////////////////////////////////////////////////////////////////////////
function send_msgx($apiURL, $chat_id, $text, $keyboard = null) {
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

function send_documentx($apiURL, $chat_id, $file_path, $caption = "") {
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
function github_download_filex($owner, $repo2, $path, $destination) {
    $url = "https://raw.githubusercontent.com/$owner/$repo2/main/" . rawurlencode($path);

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


// ===== FETCH DATA FROM GITHUB =====
list($storage, $sha_user) = github_fetch($owner, $repo, $userDataPath, $headers);
list($revenues, $sha_rev) = github_fetch($owner, $repo, $revenuesPath, $headers);

if (!$storage) $storage = [];
if (!$revenues) $revenues = [];

// ============= BOT LOGIC =============
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $user_id = $chat_id;
    $text    = trim($update["message"]["text"]);
    $first   = $update["message"]["from"]["first_name"] ?? "Trader";

    $plans = [
        "💫 1 Week — 8 USDT | Quick Profits 🔥" => 8,
        "🚀 1 Month — 29 USDT | Build Momentum 📈" => 29,
        "💎 3 Months — 80 USDT | Consistent Growth 💰" => 80,
        "🏆 1 Year — 300 USDT | Premium + Big Savings 👑" => 300
    ];

    $mainKeyboard = [
    "keyboard" => [
        [
            ["text" => "🛒 Buy Product"], 
            ["text" => "📊 My Details"]
        ],
        [
            ["text" => "🛠 Install Help"], 
            ["text" => "📥 Download Indicator & Script ⬇️"]
        ]
    ],
    "resize_keyboard" => true
];


    $planKeyboard = [
        "keyboard" => [
            [["text" => "💫 1 Week — 8 USDT | Quick Profits 🔥"]],
            [["text" => "🚀 1 Month — 29 USDT | Build Momentum 📈"]],
            [["text" => "💎 3 Months — 80 USDT | Consistent Growth 💰"]],
            [["text" => "🏆 1 Year — 300 USDT | Premium + Big Savings 👑"]],
            [["text" => "⬅️ Back to Menu"]],
        ],
        "resize_keyboard" => true
    ];

   // === START ===
if ($text == "/start") {

    // Paths for the entry images
    $photo_buy  = __DIR__ . "/buyentry.png";
    $photo_sell = __DIR__ . "/sellentry.jpg";

    // 1️⃣ — Welcome & introduction
    $msg1 = "👋 *Welcome $first!*\n\n"
        . "💎 *KingDiv Trading Bot* — trade smarter, earn faster.\n\n"
        . "Your exclusive access to *high-precision trading tools* begins now. Each plan unlocks *full access, updates, and premium support.*\n\n"
        . "Each plan gives you *full access* to the official *KingDiv Indicator* + the *Activation Script* — the same system powering top-performing private traders globally.";
    send_msg($apiURL, $chat_id, $msg1);

    // 2️⃣ — First visual (Buy Entry Example)
    send_photo($apiURL, $chat_id, $photo_buy, "🟢 Example of a *Buy Entry Zone*");

    // 3️⃣ — Key features
    $msg2 = "📊 *What You’re Getting:*\n"
        . "• Professional-grade entry & exit precision zones 🎯\n"
        . "• Smart bias detector for trend confirmation 📈\n"
        . "• Real-time algorithmic market mapping ⚙️\n"
        . "• Real-time Buy/Sell Alerts 🔔\n"
        . "• Auto Support & Resistance Channels 🛠\n"
        . "• Activation Key Security Layer 🔐\n"
        . "• Plug-and-play activation — no coding required 🧩";
    send_msg($apiURL, $chat_id, $msg2);

    // 4️⃣ — Second visual (Sell Entry Example)
    send_photo($apiURL, $chat_id, $photo_sell, "🔴 Example of a *Sell Entry Zone*");

    // 5️⃣ — Performance, trust, and CTA
    $msg3 = "💎 *Why It Matters:*\n"
        . "Because timing is everything. KingDiv helps you spot *institutional footprints* before retail traders even react — giving you a *massive edge* every session.\n\n"
        . "🔥 *Performance Snapshot (2025):*\n"
        . "✔️ Average trade accuracy: *82–91%*\n"
        . "✔️ Backtested on *200+ instruments* (including Gold, BTC, NAS100)\n"
        . "✔️ Top users generated *$3,000–$18,000 monthly* in verified accounts.\n\n"
        . "💼 *Steak on the Table:* KingDiv isn’t a toy — it’s a professional tool used by analysts, prop traders, and institutional scalpers who demand precision and results.\n\n"
        . "🕒 *Note:* Each license is unique and linked to your Binance alias for verification. Slots are limited per batch — once filled, access closes temporarily.\n\n"
        . "📊 *Click on Buy Product below to begin your profitable journey*";

    send_msg($apiURL, $chat_id, $msg3, $mainKeyboard);

    exit;
}


    // === BUY PRODUCT ===
   


   if ($text == "🛒 Buy Product") {
    // Paths for your local image/GIF files
    $photo_path1 = __DIR__ . "/donear.gif";
    $photo_path2 = __DIR__ . "/growthProfit.gif";

    // 1️⃣ — First message: the intro and features
    $description1 = "💎 *KingDiv Analysis Indicator* — Upgrade your trading IQ NOW.\n\n"
        . "Get instant access to *high-precision market insights* that top traders rely on. Each plan unlocks *full indicator access, real-time updates, and VIP support* — everything you need to read the markets like a pro.\n\n"
        . "With your subscription, you get the *KingDiv Indicator* + the *Activation Script* — the exact system that spots key trends, support/resistance zones, and signals before the crowd reacts.";

    send_msg($apiURL, $chat_id, $description1);

    // 2️⃣ — Send first GIF (visual reinforcement)
    send_photo($apiURL, $chat_id, $photo_path1, "🎯 Smart signals in real-time");

    // 3️⃣ — Second block of descriptive text
    $description2 = "🚀 *What You’re Getting:*\n"
        . "• Smart entry & exit zones 🎯\n"
        . "• Real-time trend bias detection 📈\n"
        . "• Live support & resistance channels 🛠\n"
        . "• Buy/Sell alerts directly on your chart ⚡\n"
        . "• Activation Key Security 🔐\n"
        . "• Plug-and-play setup — no coding required 🧩\n\n"
        . "🔥 *Why You Can’t Wait:*\n"
        . "Timing is everything. KingDiv helps you spot *institutional moves* before retail traders react — giving YOU the edge.";

    send_msg($apiURL, $chat_id, $description2);

    // 4️⃣ — Second GIF (growth/profit visual)
    send_photo($apiURL, $chat_id, $photo_path2, "💰 Growth and profit potential");

    // 5️⃣ — Final info and call-to-action
    $description3 = "💥 *Performance Snapshot (2025):*\n"
        . "✔️ Average trade accuracy: *82–91%*\n"
        . "✔️ Tested on *200+ instruments* including Gold, BTC, NAS100\n"
        . "✔️ Users earning *$3k–$18k monthly*\n\n"
        . "⚡ *Limited Access:* Each license is unique and linked to your Binance alias — secure yours before the batch fills.\n\n"
        . "👇 *Tap your desired plan below and start analyzing like a pro today!*";

    send_msg($apiURL, $chat_id, $description3, $planKeyboard);

    exit;
}



    // === PLAN SELECTION ===
    // === PLAN SELECTION ===
if (isset($plans[$text])) {
    $amount = $plans[$text];
    $planName = $text;
    $storage[$user_id]["plan"] = "💎 KingDiv " . $planName;
    $storage[$user_id]["amount"] = $amount;

    // Check if alias already exists
    if (isset($storage[$user_id]["alias"])) {
        $alias = $storage[$user_id]["alias"];
        $balance = find_balance_by_alias($revenues, $alias);

        if ($balance >= $amount) {
             $reply .= "\n\n✅ *Awesome!* You have enough balance to activate your  plan.\n"
            . "⚡ This is your moment — tap below to confirm and activate your access to the *KingDiv Analysis Indicator*.\n"
            . "🔥 Don’t hesitate — every second you wait, you’re missing high-precision trade setups!";

            $keyboard = [
                "keyboard" => [
                    [["text" => "💸 Confirm Now and Activate with {$amount} USDT"]],
                    [["text" => "⬅️ Back to Menu"]],
                ],
                "resize_keyboard" => true
            ];
        } else {
            $reply = "💎 Binance Username: *$alias*\n💰 Balance: *$balance USDT*\n\n"
                . "⚠️ You don’t have enough balance to unlock *$planName* for *$amount USDT*.\n"
                . "⏳ Every moment without KingDiv Analysis Indicator is a missed opportunity to trade smarter and catch high-precision moves.\n"
                . "💡 Top up now and put this powerful tool to work for you!";
            $keyboard = $mainKeyboard;
        }

        $storage[$user_id]["state"] = "ready_to_pay";
        github_save($owner, $repo, $userDataPath, $storage, $sha_user, $headers);
        send_msg($apiURL, $chat_id, $reply, $keyboard);
        exit;

    } else {
        // No alias yet — ask for it
        $storage[$user_id]["state"] = "awaiting_alias";
        github_save($owner, $repo, $userDataPath, $storage, $sha_user, $headers);

  // Send photo with short intro caption
// === PLAN CONFIRMATION WITH BINANCE PAYMENT FLOW ===

$binanceID  = "826083690";

// Send short caption with photo (within 1024-char Telegram limit)
 send_photo($apiURL, $chat_id, $photo_path, "🔥 *You’ve selected:* {$planName}\n\n" 
  . "Before activating your *KingDiv Premium Access*, we must securely link your *Binance Alias* 🔐. - 💡 *What is a Binance Alias?*\n" 
  . "It’s your *unique public username* (used in Binance Pay) — *not* your email or ID."); 
  
$removeKeyboard = ["remove_keyboard" => true];
// Send detailed message separately (unlimited text length)
send_msg($apiURL, $chat_id,
    "🔥 *You’ve selected:* {$planName}\n\n"
  . "💎 *KingDiv Premium Activation*\n"
  . "Let’s finalize your setup — follow the steps below carefully 👇\n\n"
  
  . "I️⃣ *Step 1: Make Payment*\n"
  . "💰 Send *{$amount} USDT* via *Binance Pay* to this ID: `{$binanceID}`\n"
  . "📝 Add note: *KingDiv Activation*\n"
  . "⚡ This payment unlocks your personal trading system instantly after confirmation.\n\n"

  . "II️⃣ *Step 2: Wait for Confirmation*\n"
  . "⏱️ Binance takes less than *2 minutes* to confirm your transfer.\n"
  . "Once confirmed, your license is *activated automatically* — no waiting, no manual approval.\n\n"

  . "III️⃣ *Step 3: Verify Ownership*\n"
  . "After sending your payment, *reply here with your Binance Alias (username)* so the system can securely link your license.\n\n"

  . "💡 *Why Use Binance Pay?*\n"
  . "• 0% transaction fees — pay exactly what’s shown.\n"
  . "• Global reach — works in 100+ countries.\n"
  . "• Instant delivery — activation begins the moment payment confirms.\n\n"

  . "⚙️ *How to Find Your Binance Username (Alias):*\n"
  . "1️⃣ Open the *Binance App* or go to [Binance Pay Dashboard](https://www.binance.com/en/my/wallet/account/payment/dashboard?hl=en)\n"
  . "2️⃣ Tap your *Profile Icon (top-left)* or check your dashboard.\n"
  . "3️⃣ Copy your *Username (Alias)* — it’s your public handle (see picture above).\n\n"

  . "✍️ *Once done, type and send your Binance Alias below to complete verification and activate instantly.*\n\n"
  . "🚀 *KingDiv × Binance Pay = Instant. Secure. Global.*",
  $removeKeyboard
);

         
        exit;
    }
}


    // === ALIAS INPUT ===
   if (isset($storage[$user_id]["state"]) && $storage[$user_id]["state"] === "awaiting_alias") {
    $alias = trim($text);

    // ✅ Validate Binance Alias format
    if (!preg_match('/^[A-Za-z0-9._-]{3,20}$/', $alias)) {
        send_msg($apiURL, $chat_id,
            "⚠️ *Invalid Binance Alias.*\n\n"
          . "Your alias should only contain *letters, numbers, dots (.)*, *underscores (_)*, or *hyphens (-)*, and be *3–20 characters long*.\n\n"
          . "💡 *Example:* `king.div`, `besty_fx`, `Trader-001`, or `Alpha123`.\n\n"
          . "👉 Please type your correct Binance alias again."
        );
        exit;
    }

    foreach ($storage as $uid => $info) {
        if ($uid != $user_id && isset($info["alias"]) && strcasecmp($info["alias"], $alias) == 0) {
            send_msg($apiURL, $chat_id,
                "⚠️ This Binance Username *$alias* is already linked to another user. Please use your own Binance alias.");
            exit;
        }
    }


        $storage[$user_id]["alias"] = $alias;
        $storage[$user_id]["state"] = "ready_to_pay";
        $storage[$user_id]["products"] = $storage[$user_id]["products"] ?? [];
        github_save($owner, $repo, $userDataPath, $storage, $sha_user, $headers);

        $amount = $storage[$user_id]["amount"];
        $balance = find_balance_by_alias($revenues, $alias);

        $reply = "💎 Binance Username: *$alias*\n💰 Balance: *$balance USDT*";

        if ($balance >= $amount) {
    $reply .= "\n\n✅ *Awesome!* You have enough balance to activate your  plan.\n"
            . "⚡ This is your moment — tap below to confirm and activate your access to the *KingDiv Analysis Indicator*.\n"
            . "🔥 Don’t hesitate — every second you wait, you’re missing high-precision trade setups!";
    
    $keyboard = [
        "keyboard" => [
            [["text" => "💸 Confirm Now and Activate with {$amount} USDT"]],
            [["text" => "⬅️ Back to Menu"]],
        ],
        "resize_keyboard" => true
    ];
} else {
    $reply .= "\n\n⚠️ *Insufficient balance for the the plan selected.*\n"
            . "💡 Don’t let this opportunity pass — KingDiv is built to work *for you*, not wait on you.\n"
            . "💰 Top up now and turn your analysis power back ON!";
    
    $keyboard = $mainKeyboard;
}


        send_msg($apiURL, $chat_id, $reply, $keyboard);
        exit;
    }

    // === CONFIRM PAYMENT ===
  if (preg_match("/💸\s*Confirm\s*Now\s*and\s*Activate\s*with\s*(\d+(?:\.\d+)?)\s*USDT/i", $text, $m)) {
        $amount = $storage[$user_id]["amount"];
        $alias = $storage[$user_id]["alias"] ?? "Unknown";
        $plan  = $storage[$user_id]["plan"] ?? "Unknown Plan";
        $balance = find_balance_by_alias($revenues, $alias);

        if ($balance < $amount) {
    send_msg($apiURL, $chat_id,
        "⚠️ *Insufficient balance to complete payment.*\n"
      . "💰 Your current balance: *$balance USDT* — required: *$amount USDT*.\n\n"
      . "⏳ Don’t let your trading edge sit idle. *Top up now* and come back to activate your KingDiv Analysis Indicator!");
    exit;
}


        // Deduct from revenues.json
        update_balance_by_alias($revenues, $alias, $amount);
        github_save($owner, $repo, $revenuesPath, $revenues, $sha_rev, $headers);

        // === EXPIRY KEY GENERATION ===
      // === EXPIRY KEY GENERATION (with hours) ===
$order_description = $plan;
$key_expiry = 0;

if ($order_description && preg_match('/(\d+)\s*(Hour|Day|Week|Month|Year)s?/i', $order_description, $matches)) {
    $duration = (int)$matches[1];
    $unitRaw  = strtolower($matches[2]);

    // Make unit safe for DateTime->modify (use plural)
    $unit = $unitRaw;
    if (substr($unit, -1) !== 's') $unit .= 's';

    $now = new DateTime();
    $now->modify("+{$duration} {$unit}");

    // Extract components
    $day   = (int) $now->format('d'); // 01-31
    $month = (int) $now->format('m'); // 01-12
    $year  = (int) $now->format('Y'); // full year, e.g. 2025
    $hour  = (int) $now->format('H'); // 00-23

    // Apply offsets as requested
    $part1 = $day + 23;
    $part2 = $month + 415;
    $part3 = $year + 4391;
    $part4 = $hour + 40;

    // Concatenate parts into a single integer expiry key
    // Use string concatenation to avoid arithmetic mixing; cast to int at the end.
    $key_expiry = (int)("{$part1}{$part2}{$part3}{$part4}");
}


        // Save product record
        // === Generate 13-digit Activation Key ===
$activation_key = "";
for ($i = 0; $i < 13; $i++) {
    $activation_key .= mt_rand(1, 9);
}

// Save product record with key_expiry and activation_key
$storage[$user_id]["products"][] = [
    "plan" => $plan,
    "amount" => $amount,
    "date" => date("Y-m-d H:i:s"),
    "key" => $key_expiry,
    "activation_key" => $activation_key
];

// Persist to user_data.json before sending confirmation
github_save($owner, $repo, $userDataPath, $storage, $sha_user, $headers);


        send_msg($apiURL, $chat_id,
            "✅ *Payment Confirmed!*\n\n"
          . "Plan: *$plan*\n"
          . "Amount: *$amount USDT*\n"
          . "Alias: *$alias*\n\n"
          . "🔑 Your License Key: `$activation_key`\n"
          . "💰 Your balance has been updated.\n"
          . "🚀 Enjoy your premium KingDiv access!",
            $mainKeyboard
        );
        exit;
    }

   // === MY DETAILS ===
if ($text == "📊 My Details") {
    $alias = $storage[$user_id]["alias"] ?? "Not set";
    $balance = $alias != "Not set" ? find_balance_by_alias($revenues, $alias) : 0;
    $products = $storage[$user_id]["products"] ?? [];

    $prodText = "";
    if (!empty($products)) {
        foreach ($products as $p) {
            $plan = $p["plan"] ?? "N/A";
            $date = $p["date"] ?? "Unknown";
            $activationKey = $p["activation_key"] ?? "N/A";

            // Calculate expiry date from plan duration
            $expiry = "Unknown";
            if ($date != "Unknown") {
                $expiryDate = new DateTime($date);
                if (preg_match('/(\d+)\s*(Hour|Day|Week|Month|Year)/i', $plan, $matches)) {
                    $duration = (int)$matches[1];
                    $unit = strtolower($matches[2]);
                    $expiryDate->modify("+$duration $unit");
                    $expiry = $expiryDate->format("Y-m-d H:i:s");
                }
            }

            $prodText .= "🔹 *$plan*\n"
                       . "📅 *From:* $date\n"
                       . "📅 *To:* $expiry\n"
                       . "🔑 *Activation Key:* `$activationKey`\n\n";
        }
    } else {
        $prodText = "💡 *No products purchased yet.*\n\n"
                  . "Tap 🛒 *Buy Product* to unlock premium access, real-time signals, and your personal trading edge!";
    }

    send_msg($apiURL, $chat_id,
        "ℹ️ *Your Details:*\n"
      . "👤 Name: $first\n"
      . "🪪 Alias: $alias\n"
      . "💰 Balance: *$balance USDT*\n\n"
      . "🎁 *Your Products:*\n$prodText",
      $mainKeyboard
    );
    exit;
}




    
// === HELP ===
if ($text == "🛠 Install Help") {
    $helpMessage = "📘 *MT5 Indicator Activation Guide*\n\n"

    . "I. *Allow Activation Server in MT5*\n"
    . "1. Open your MetaTrader 5 terminal.\n"
    . "2. Go to *Tools → Options → Expert Advisors*.\n"
    . "3. Check the box: 'Allow WebRequest for listed URL'.\n"
    . "4. Add this URL:\nhttps://bestforextradingsetups.dpdns.org\n\n"

    . "II. *Install the Indicator and Script Files*\n"
    . "1. In MT5, click *File → Open Data Folder*.\n"
    . "2. Go to *MQL5 → Indicators* and place the file: `INDICATOR.ex5`\n"
    . "3. Go to *MQL5 → Scripts* and place the file: `INDICATOR_ACTIVATOR_SCRIPT.ex5`\n"
    . "• Ensure both files are in their correct folders.\n\n"

    . "III. *Restart MetaTrader 5*\n"
    . "• Close and reopen your MT5 terminal to load the new files properly.\n\n"

    . "IV. *Load the Indicator on a Chart*\n"
    . "1. Open the *Navigator* panel (Ctrl + N).\n"
    . "2. Under *Indicators*, find `INDICATOR.ex5`.\n"
    . "3. Drag and drop it onto any chart.\n"
    . "• A settings window will appear.\n\n"

    . "V. *Enter Activation Details*\n"
    . "Fill in the input fields as follows:\n"
    . "`activation_key = [your 14-digit code]`\n"
    . "`payment_id = [your transaction/payment ID]`\n"
    . "• Click *OK* after entering your details.\n\n"

    . "VI. *Run the Activator Script*\n"
    . "1. In the *Navigator* panel, open *Scripts*.\n"
    . "2. Drag `INDICATOR_ACTIVATOR_SCRIPT.ex5` onto the same chart.\n"
    . "• Ensure your computer is online — the script will automatically validate your license.\n\n"

    . "VII. *Confirmation*\n"
    . "• If activation is successful, the indicator will start working immediately.\n"
    . "• If not, restart MT5 and it should activate properly.\n\n"

    . "⚠️ *Important Notes:*\n"
    . "• Always load the indicator *before* running the activator script.\n"
    . "• Activation is tied to your device/IP.\n"
    . "• Do not share your key or use it on multiple devices.\n"
    . "• Keep your activation key *safe and private.*";

    send_msg($apiURL, $chat_id, $helpMessage, $mainKeyboard);
    exit;
}


// === DOWNLOAD HANDLER ===
// === DOWNLOAD HANDLER ===
if ($text == "📥 Download Indicator & Script ⬇️") {
    $indicator_file = __DIR__ . "/Kingdiv V1 2025.ex5";
    $script_file    = __DIR__ . "/KingDiv_Activator_Script 2.ex5";

    if (!file_exists($indicator_file)) {
        github_download_filex($owner, $repo2, "Kingdiv V1 2025.ex5", $indicator_file);
    }
    if (!file_exists($script_file)) {
        github_download_filex($owner, $repo2, "KingDiv_Activator_Script 2.ex5", $script_file);
    }

    if (filesize($indicator_file) < 1000) {
        send_msgx($apiURL, $chat_id,
            "⚠️ *Download Interrupted*\n\n"
          . "It seems your *Indicator file* didn’t download correctly.\n"
          . "Please tap again or retry in a few moments — your premium tools are standing by."
        );
    } else {
        send_documentx($apiURL, $chat_id, $indicator_file,
            "📈 *KingDiv V1 2025 Indicator* — your *High-Precision Chart Analysis Engine.*\n\n"
          . "Built for *Forex*, *Volatility Indices*, and *Step Index* — where milliseconds and micro-trends define the edge.\n\n"
          . "💎 *What Makes It Special:*\n"
          . "• Institutional-grade chart analytics powered by adaptive logic ⚙️\n"
          . "• Real-time bias detection for *trend confidence* 📊\n"
          . "• Dynamic Support & Resistance channels auto-generated by market structure 🔍\n"
          . "• *No hidden settings. No coding. Plug, activate, and analyze instantly.*\n\n"
          . "When precision meets simplicity — trading stops being guessing and becomes *execution.* 🚀"
        );
    }

    if (filesize($script_file) < 1000) {
        send_msgx($apiURL, $chat_id,
            "⚠️ *Activator Script Missing*\n\n"
          . "Your *Activation Script* didn’t finish downloading.\n"
          . "Please retry — this script securely unlocks your KingDiv license."
        );
    } else {
        send_documentx($apiURL, $chat_id, $script_file,
            "⚙️ *KingDiv Activator Script 2* — *Activate and Analyze Instantly.*\n\n"
          . "🔐 Run it once in *MetaTrader → Scripts* to bind your premium license to your verified Binance alias.\n\n"
          . "💡 After activation, your indicator automatically syncs — ready to decode market structure and deliver precise signals in real time.\n\n"
          . "Designed for serious traders mastering *Forex*, *Volatility 75*, and *Step Index* movements — where accuracy means profit.\n\n"
          . "Activate. Analyze. Dominate. 💼"
        );
    }

    // ✅ Optional — exit to stop script after download
     exit;
}

    // === DEFAULT ===
    // === DEFAULT ===
send_msg($apiURL, $chat_id,
    "⚡ You said: *$text*\nPlease tap one of the options below 👇 to continue your KingDiv journey.",
    $mainKeyboard
);
}

?> 
