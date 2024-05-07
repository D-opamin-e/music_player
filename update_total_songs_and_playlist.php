<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// UTF-8 BOM 제거
ob_start("mb_output_handler");

$userIP = $_SERVER['REMOTE_ADDR']; // 사용자 IP 주소 가져오기
$filename = 'playlist/' . $userIP . '.json';

// A 리스트 불러오기 (사용자 IP 기반으로)
if (file_exists($filename)) {
    $playlistJson = file_get_contents($filename);
    $playlistData = json_decode($playlistJson, true);
    
    // 플레이리스트 데이터에서 'playlist' 및 'totalSongs' 키 확인
    $playlist = isset($playlistData['playlist']) ? $playlistData['playlist'] : [];
    $totalSongs = isset($playlistData['totalSongs']) ? $playlistData['totalSongs'] : 0;
} else {
    // 해당 IP의 파일이 없을 경우 기본 데이터 설정
    $playlist = [];
    $totalSongs = 0;
}

$output = shell_exec("node now_playlist_update.js $userIP"); // Node.js 스크립트 실행
$time = date('m월 d일 H시 i분 s초');
$outputLines = explode("\n", $output);
$firstLine = $outputLines[0];

// 출력된 문자열에서 JSON 부분을 추출하여 배열로 변환
$jsonStart = strpos($output, '{"');
$jsonEnd = strrpos($output, '}');
$jsonString = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
$resultArray = json_decode($jsonString, true);

if (isset($resultArray['log'])) {
    $logArray = $resultArray['log'];
    $outputString = '';

    foreach ($logArray as $log) {
        $outputString .= "신규 추가된 곡: $log\n";
    }

    if (!empty($outputString)) {
        echo $outputString;
        echo json_encode($playlist); // JSON 형식으로 출력 수정
    } else {
        echo "$time / $firstLine\n";
    }
} else {
    echo "$time / $firstLine\n";
}

// UTF-8 BOM 제거 후 출력
ob_end_flush();
?>
