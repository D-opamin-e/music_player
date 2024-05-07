<?php
// DB 연결 정보
$servername = "localhost";
$username = "root";
$password = "password";
$dbname = "music_player";

// POST로부터 전달된 데이터 가져오기
$index = $_POST['index'];

// DB 연결
$conn = new mysqli($servername, $username, $password, $dbname);

// DB 연결 확인
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 해당 곡의 재생 횟수를 업데이트하기 위한 쿼리 작성
$sql = "UPDATE songs SET play_count = play_count + 1 WHERE index_number = '$index'";

// 쿼리 실행
if ($conn->query($sql) === TRUE) {
    echo "Record updated successfully";
} else {
    echo "Error updating record: " . $conn->error;
}

// DB 연결 종료
$conn->close();
?>
