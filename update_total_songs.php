<?php
// DB 연결 정보
$servername = "localhost";
$username = "root";
$password = "password";
$dbname = "music_player";

// DB 연결
$conn = new mysqli($servername, $username, $password, $dbname);

// DB 연결 확인
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 평균 재생 횟수를 가져오는 쿼리
$sql = "SELECT AVG(play_count) AS avg_play_count FROM songs";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // 결과를 연관 배열로 변환하여 평균 재생 횟수를 가져옴
    $row = $result->fetch_assoc();
    $avg_play_count = $row["avg_play_count"];
    echo $avg_play_count;
} else {
    echo "평균 재생 횟수를 가져오는데 실패했습니다.";
}

$conn->close();
?>
