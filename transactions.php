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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>💳 Payments Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
  async function liveSearch(q) {
      const tbody = document.querySelector("#resultsBody");
      if (q.trim() === "") {
          tbody.innerHTML = window.allRowsHTML;
          return;
      }
      const res = await fetch("?search=" + encodeURIComponent(q));
      const data = await res.json();
      if (!data.length) {
          tbody.innerHTML = `<tr><td colspan="5" class="text-center text-gray-500 py-3">No matches found</td></tr>`;
          return;
      }
      tbody.innerHTML = data.map(r => `
        <tr class="hover:bg-gray-50 border-b">
          <td class="py-3 px-4">${r.user_id}</td>
          <td class="py-3 px-4">${r.date}</td>
          <td class="py-3 px-4">${r.time}</td>
          <td class="py-3 px-4 font-semibold text-green-600">${r.amount}</td>
          <td class="py-3 px-4 text-gray-500">${r.created}</td>
        </tr>`).join("");
  }
  window.addEventListener("DOMContentLoaded", () => {
      window.allRowsHTML = document.querySelector("#resultsBody").innerHTML;
  });
  </script>
</head>
<body class="bg-gray-100 text-gray-900">
<header class="bg-indigo-700 text-white py-6 shadow-md">
  <div class="max-w-5xl mx-auto px-6 text-center">
    <h1 class="text-3xl font-bold">💳 Payments Dashboard</h1>
    <p class="text-indigo-200">Live Search + Gmail Parsing + GitHub JSON Sync</p>
  </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-10 space-y-8">
  <div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-semibold text-indigo-700 mb-4">📝 Add Manual Payment</h2>
    <?= $statusMsg ?>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <input type="text" name="user_id" required placeholder="User ID" class="border rounded-lg px-3 py-2 w-full">
      <input type="number" step="0.01" name="amount" required placeholder="Amount (USDT)" class="border rounded-lg px-3 py-2 w-full">
      <input type="text" name="date" required placeholder="YYYY-MM-DD" class="border rounded-lg px-3 py-2 w-full">
      <input type="text" name="time" required placeholder="HH:MM:SS" class="border rounded-lg px-3 py-2 w-full">
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg">➕ Add</button>
    </form>
  </div>

  <div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="flex justify-between items-center bg-indigo-600 text-white px-6 py-3">
      <h3 class="text-2xl font-semibold">📜 Payments Table</h3>
      <input id="search" onkeyup="liveSearch(this.value)" type="text" placeholder="🔍 Live Search..." class="rounded-lg px-3 py-1 text-gray-800 text-sm w-48">
    </div>
    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-left text-sm">
        <thead class="bg-indigo-50">
          <tr class="text-indigo-700">
            <th class="py-3 px-4 border-b">User</th>
            <th class="py-3 px-4 border-b">Date</th>
            <th class="py-3 px-4 border-b">Time</th>
            <th class="py-3 px-4 border-b">Amount (USDT)</th>
            <th class="py-3 px-4 border-b">Created</th>
          </tr>
        </thead>
        <tbody id="resultsBody">
        <?php foreach ($payments as $r): ?>
          <tr class="hover:bg-gray-50 border-b">
            <td class="py-3 px-4"><?= htmlspecialchars($r['user_id']) ?></td>
            <td class="py-3 px-4"><?= htmlspecialchars($r['date']) ?></td>
            <td class="py-3 px-4"><?= htmlspecialchars($r['time']) ?></td>
            <td class="py-3 px-4 font-semibold text-green-600"><?= htmlspecialchars($r['amount']) ?></td>
            <td class="py-3 px-4 text-gray-500"><?= htmlspecialchars($r['created']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-white rounded-2xl shadow overflow-hidden mt-6">
    <div class="flex justify-between items-center bg-indigo-600 text-white px-6 py-3">
      <h3 class="text-2xl font-semibold">💰 Revenues Table</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-left text-sm">
        <thead class="bg-indigo-50">
          <tr class="text-indigo-700">
            <th class="py-3 px-4 border-b">User</th>
            <th class="py-3 px-4 border-b">Total Revenue (USDT)</th>
            <th class="py-3 px-4 border-b">Last Updated</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($revenues as $r): ?>
          <tr class="hover:bg-gray-50 border-b">
            <td class="py-3 px-4"><?= htmlspecialchars($r['user_id']) ?></td>
            <td class="py-3 px-4 font-semibold text-green-600"><?= htmlspecialchars($r['total_amount']) ?></td>
            <td class="py-3 px-4 text-gray-500"><?= htmlspecialchars($r['last_updated']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<footer class="text-center py-6 text-gray-500 text-sm">
  © <?= date('Y') ?> Payments Dashboard — Gmail + Live Search + Revenue Sync
</footer>
</body>
</html>
