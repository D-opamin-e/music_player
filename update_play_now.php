<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST 데이터에서 전달받은 index 값 가져오기
    $index = isset($_POST['index']) ? $_POST['index'] : null;

    if ($index !== null) {
        // 데이터베이스 연결 정보
        $host = 'localhost';
        $user = 'root';
        $password = 'password';
        $database = 'music_player';

        // 데이터베이스 연결
        $conn = new mysqli($host, $user, $password, $database);

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // 현재 재생 중인 곡을 제외하고 모든 곡의 play_now를 0으로 설정
        $sqlUpdate = "UPDATE songs SET play_now = 0";
        $conn->query($sqlUpdate);

        // 현재 재생 중인 곡의 play_now를 1로 설정
        $sqlUpdateCurrent = "UPDATE songs SET play_now = 1 WHERE index_number = ?";
        $stmt = $conn->prepare($sqlUpdateCurrent);
        $stmt->bind_param("i", $index);
        $stmt->execute();
        $stmt->close();

        $conn->close();

        // 사용자별 재생 목록 파일 이름 생성
        $userIP = $_SERVER['REMOTE_ADDR'];
        $playlistFile = 'playlist/' + userIP + '.json'; // 파일 이름에 사용자 IP 포함

        // 파일이 존재하는지 확인
        if (file_exists($playlistFile)) {
            $playlistData = json_decode(file_get_contents($playlistFile), true);

            foreach ($playlistData['playlist'] as &$song) {
                if ($song['index'] == $index) {
                    $song['play_now'] = 1;
                } else {
                    $song['play_now'] = 0;
                }
            }

            $updatedPlaylistJson = json_encode($playlistData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            file_put_contents($playlistFile, $updatedPlaylistJson);
        } else {
            echo "Playlist file does not exist.";
        }
    } else {
        echo "Invalid index value.";
    }
} else {
    echo "Invalid request method.";
}
?>