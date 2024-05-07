<?php
// 사용자의 IP 주소 가져오기
$userIP = $_SERVER['REMOTE_ADDR'];

// 사용자의 플레이리스트 파일 경로 설정
$userPlaylistFile = 'playlist/' . $userIP . '.json'; // 수정된 부분: 파일명에 연산자 오류 수정

// 파일이 존재하는지 확인
if (file_exists($userPlaylistFile)) {
    // 파일이 있다면 JSON 파일 내용을 읽어와서 출력
    $playlistJson = file_get_contents($userPlaylistFile);
    echo $playlistJson;
} else {
    // 파일이 없을 경우 에러 메시지 출력
    echo "User's playlist file does not exist.";
}
?>
