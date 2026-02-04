<?php
error_reporting(0);
date_default_timezone_set('Asia/Kolkata');

$config = [
    'user' => 'Sujay2005', 
    'pass' => 'Sujay2005',
    'base_url' => 'http://139.99.208.63',
    'cookie_file' => '/tmp/cookie.txt', // Railway te /tmp folder use kora better
    'api_token' => 'hYuwoskkkaw28kss'
];

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$req_token = isset($_GET['token']) ? $_GET['token'] : '';

if ($req_token !== $config['api_token']) {
    echo json_encode(["status" => "error", "msg" => "Not Authorized"]);
    exit;
}

$dt1 = isset($_GET['dt1']) ? $_GET['dt1'] : date('Y-m-d 00:00:00');
$dt2 = isset($_GET['dt2']) ? $_GET['dt2'] : date('Y-m-d 23:59:59');
$limit = isset($_GET['records']) ? intval($_GET['records']) : 25;
$filter_num = isset($_GET['filternum']) ? $_GET['filternum'] : '';
$filter_cli = isset($_GET['filtercli']) ? $_GET['filtercli'] : '';

if ($limit > 200) $limit = 200;

$fetched_data = fetch_sms_data($config, $dt1, $dt2, $filter_num, $filter_cli, $limit);

if ($fetched_data !== null) {
    $response = [
        "status" => "success",
        "total" => count($fetched_data),
        "data" => $fetched_data
    ];
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(["status" => "error", "msg" => "Failed to fetch data"]);
}

function fetch_sms_data($cfg, $d1, $d2, $fnum, $fcli, $lim) {
    $login_html = curl_req($cfg['base_url'] . "/ints/login", 'GET', [], $cfg);
    
    $math_ans = 15;
    if (preg_match('/(\d+)\s*[\+\*]\s*(\d+)/', $login_html, $matches)) {
        $math_ans = intval($matches[1]) + intval($matches[2]);
    }

    $postData = [
        'username' => $cfg['user'],
        'password' => $cfg['pass'],
        'capt' => $math_ans
    ];
    curl_req($cfg['base_url'] . "/ints/signin", 'POST', $postData, $cfg, ['Referer: ' . $cfg['base_url'] . '/ints/login']);

    $params = [
        'fdate1' => $d1,
        'fdate2' => $d2,
        'frange' => '', 'fclient' => '', 'fnum' => $fnum, 'fcli' => $fcli,
        'fgdate' => '', 'fgmonth' => '', 'fgrange' => '', 'fgclient' => '', 'fgnumber' => '', 'fgcli' => '', 'fg' => '0',
        'sEcho' => 1,
        'iDisplayLength' => $lim,
        'iSortCol_0' => 0,
        'sSortDir_0' => 'desc'
    ];
    
    $query = http_build_query($params);
    $target_url = $cfg['base_url'] . "/ints/agent/res/data_smscdr.php?" . $query;
    
    $headers = [
        'X-Requested-With: XMLHttpRequest',
        'Referer: ' . $cfg['base_url'] . '/ints/agent/SMSCDRStats'
    ];
    
    $json = curl_req($target_url, 'GET', [], $cfg, $headers);
    $data = json_decode($json, true);

    if (isset($data['aaData'])) {
        $formatted = [];
        foreach ($data['aaData'] as $row) {
            $formatted[] = [
                "dt" => $row[0],
                "num" => $row[2],
                "cli" => $row[3] ? $row[3] : $row[1],
                "message" => strip_tags($row[5]),
                "payout" => $row[7]
            ];
        }
        // Sorting (Latest First)
        usort($formatted, function($a, $b) {
            return strtotime($b['dt']) - strtotime($a['dt']);
        });
        return $formatted;
    }
    return null;
}

function curl_req($url, $method, $data, $cfg, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cfg['cookie_file']);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cfg['cookie_file']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36");
    
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $res = curl_exec($ch);
    return $res;
}
?>
