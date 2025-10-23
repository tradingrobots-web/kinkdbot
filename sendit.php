<?php
header('Content-Type: application/json');

// =================== CONFIG ===================
$owner = "Bestyfx";
$repo  = "majsons";
$userDataPath = "user_data.json";
$token = getenv("GITHUB_TOKEN"); // Set this in Render environment variables

$destination_url = "https://bestforextradingsetups.dpdns.org/receivejsons.php";

// =================== FETCH FILE FROM GITHUB ===================
$api_url = "https://api.github.com/repos/$owner/$repo/contents/$userDataPath";

$headers = [
    "Authorization: token $token",
    "User-Agent: PHP-App",
    "Accept: application/vnd.github+json"
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if($err){
    echo json_encode(['status'=>'error','message'=>"GitHub cURL error: $err"]);
    exit;
}

// Decode GitHub API response
$ghData = json_decode($response, true);
if(!isset($ghData['content'])){
    echo json_encode(['status'=>'error','message'=>"Invalid GitHub response"]);
    exit;
}

// GitHub returns base64 content
$json_content = base64_decode($ghData['content']);
$data = json_decode($json_content, true);
if($data === null){
    echo json_encode(['status'=>'error','message'=>"Invalid JSON in GitHub file"]);
    exit;
}

// =================== POST TO DESTINATION ===================
$ch2 = curl_init($destination_url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$result = curl_exec($ch2);
$err2 = curl_error($ch2);
curl_close($ch2);

if($err2){
    echo json_encode(['status'=>'error','message'=>"Destination POST cURL error: $err2"]);
} else {
    echo json_encode(['status'=>'success','message'=>"Data fetched from GitHub and posted successfully","response"=>$result]);
}
?>
