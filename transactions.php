<?php
// =================== CONFIG ===================
$owner = "Bestyfx";
$repo  = "majsons";
$paymentsPath = "payments.json";
$revenuesPath = "revenues.json";
$token = getenv("GITHUB_TOKEN"); // must be set in Render env vars

$headers = [
    "Authorization: token $token",
    "User-Agent: PHP-App"
];

// =================== GITHUB HELPERS ===================
function fetchJson($path, &$sha) {
    global $owner, $repo, $headers;
    $url = "https://api.github.com/repos/$owner/$repo/contents/$path";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($res, true);
    $sha = $data['sha'] ?? null;
    if (isset($data['content'])) {
        return json_decode(base64_decode($data['content']), true) ?: [];
    }
    return [];
}

function pushJson($path, $data, $sha, $message) {
    global $owner, $repo, $headers;
    $url = "https://api.github.com/repos/$owner/$repo/contents/$path";

    $payload = json_encode([
        "message" => $message,
        "content" => base64_encode(json_encode($data, JSON_PRETTY_PRINT)),
        "sha"     => $sha
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => array_merge($headers, ["Content-Type: application/json"]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => $payload
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code;
}

// =================== FETCH DATA ===================
$payments = fetchJson($paymentsPath, $paymentsSha);
$revenues = fetchJson($revenuesPath, $revenuesSha);

// =================== GMAIL PARSING LOGIC ===================
$rawInput = file_get_contents("php://input");
if ($rawInput) {
    $gmailData = json_decode($rawInput, true);

    if ($gmailData) {
        $addedCount = 0;
        foreach ($gmailData as $email) {
            // Only process Binance payment emails
            if (
                stripos($email['subject'] ?? '', 'Payment Receive Successful') === false ||
                stripos($email['from'] ?? '', 'do-not-reply@ses.binance.com') === false
            ) continue;

            $lines = array_values(array_filter(array_map('trim', explode("\n", $email['body'] ?? ''))));
            $time = $sender = $amount = 'N/A';

            for ($i = 0; $i < count($lines); $i++) {
                if ($time === 'N/A' && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $lines[$i])) {
                    $time = $lines[$i];
                    if (isset($lines[$i + 1])) $sender = $lines[$i + 1];
                }
                if ($amount === 'N/A' && preg_match('/\b\d+(\.\d+)?\s*USDT\b/i', $lines[$i])) {
                    $amount = preg_replace('/\s*USDT$/i', '', $lines[$i]);
                    $amount = floatval($amount);
                }
            }

            if ($amount <= 0) continue;

            $new = [
                "user_id" => trim($sender),
                "email"   => $email['to'] ?? 'N/A',
                "date"    => $email['date'] ?? date('Y-m-d'),
                "time"    => $time,
                "amount"  => $amount,
                "created" => date("Y-m-d H:i:s")
            ];

            // Check for duplicate
            $exists = false;
            foreach ($payments as $r) {
                if (
                    $r['user_id'] === $new['user_id'] &&
                    $r['date'] === $new['date'] &&
                    $r['time'] === $new['time']
                ) { $exists = true; break; }
            }

            if (!$exists) {
                $payments[] = $new;
                $addedCount++;

                // Update revenues
                $found = false;
                foreach ($revenues as &$r) {
                    if ($r['user_id'] === $new['user_id']) {
                        $r['total_amount'] += $new['amount'];
                        $r['last_updated'] = date("Y-m-d H:i:s");
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $revenues[] = [
                        "user_id" => $new['user_id'],
                        "total_amount" => $new['amount'],
                        "last_updated" => date("Y-m-d H:i:s")
                    ];
                }
            }
        }

        if ($addedCount > 0) {
            $payCode = pushJson($paymentsPath, $payments, $paymentsSha, "Add Gmail payments");
            $revCode = pushJson($revenuesPath, $revenues, $revenuesSha, "Update revenues via Gmail");
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "added" => $addedCount,
                "payments_code" => $payCode,
                "revenues_code" => $revCode
            ]);
            exit;
        } else {
            http_response_code(204);
            echo json_encode(["status" => "no_new_payments"]);
            exit;
        }
    }
}

// =================== LIVE SEARCH ===================
if (isset($_GET['search'])) {
    $q = strtolower(trim($_GET['search']));
    $filtered = array_filter($payments, function($r) use ($q) {
        return strpos(strtolower(json_encode($r)), $q) !== false;
    });
    header("Content-Type: application/json");
    echo json_encode(array_values($filtered));
    exit;
}

// =================== HANDLE FORM SUBMISSION ===================
$statusMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    if ($amount > 0) {
        $newPayment = [
            "user_id" => trim($_POST['user_id']),
            "date"    => $_POST['date'],
            "time"    => $_POST['time'],
            "amount"  => $amount,
            "created" => date("Y-m-d H:i:s")
        ];

        $exists = false;
        foreach ($payments as $p) {
            if (
                $p['user_id'] === $newPayment['user_id'] &&
                $p['date'] === $newPayment['date'] &&
                $p['time'] === $newPayment['time']
            ) { $exists = true; break; }
        }

        if (!$exists) {
            $payments[] = $newPayment;
            $payCode = pushJson($paymentsPath, $payments, $paymentsSha, "Add payment");

            $found = false;
            foreach ($revenues as &$r) {
                if ($r['user_id'] === $newPayment['user_id']) {
                    $r['total_amount'] += $newPayment['amount'];
                    $r['last_updated'] = date("Y-m-d H:i:s");
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $revenues[] = [
                    "user_id" => $newPayment['user_id'],
                    "total_amount" => $newPayment['amount'],
                    "last_updated" => date("Y-m-d H:i:s")
                ];
            }
            $revCode = pushJson($revenuesPath, $revenues, $revenuesSha, "Update revenues");

            $statusMsg = ($payCode == 200 || $payCode == 201)
                ? "<p class='text-green-600 font-semibold'>✅ Payment added and revenue updated!</p>"
                : "<p class='text-red-600 font-semibold'>🚫 GitHub update failed ($payCode)</p>";
        } else {
            $statusMsg = "<p class='text-orange-500 font-semibold'>⚠️ Duplicate entry — not saved.</p>";
        }
    } else {
        $statusMsg = "<p class='text-red-600 font-semibold'>⚠️ Cannot save 0 or negative amount.</p>";
    }
}
?>

