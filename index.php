<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>상재의 노래주머니</title>
    <link rel="shortcut icon" href="/favicon.png" type="image/png">
    <link rel="stylesheet" href="CSS/music.css?r=2" />
    <link rel="stylesheet" href="CSS/bootstrap.css?r=2" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
          integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
          integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script src="CSS/jquery-3.6.4.js"></script>
    <?php
// 사용자 식별을 위한 쿠키 이름 지정
$cookieName = 'userSession';

// 쿠키가 이미 존재한다면 삭제
if (isset($_COOKIE[$cookieName])) {
    // 쿠키 삭제를 위해 만료 시간을 과거로 설정
    setcookie($cookieName, '', time() - 3600, "/");
}

// 새로운 쿠키 생성
// 이 쿠키는 현재 시간을 기반으로 한 어떤 값(예를 들어 유니크 ID)을 저장할 것입니다.
// 쿠키 유효 시간을 현재 시간에서 +3600초(1시간)으로 설정
$newValue = uniqid();
setcookie($cookieName, $newValue, time() + 3600, "/"); // SSL이 활성화된 경우, secure 파라미터를 true로 설정하고, HttpOnly를 true로 설정하는 것이 좋습니다.

// 이제 쿠키는 설정되었으며, 페이지의 다른 부분에서 $_COOKIE['userSession']을 통해 접근할 수 있습니다.
?>

</head>

<body>
<div id="playlistContainer">
<?php
// 사용자의 IP 주소를 가져옵니다.
$userIP = $_SERVER['REMOTE_ADDR'];

// 사용자의 플레이리스트 파일 경로를 설정합니다.
$userPlaylistFile = 'playlist/' . str_replace(':', '_', $userIP) . '.json';

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
$sql = "UPDATE songs 
        SET avg_play_count = (
            SELECT AVG(play_count) 
            FROM songs
        )";
$result = $conn->query($sql);

if ($result) {

} else {
    echo "평균 재생 횟수 업데이트에 실패했습니다.";
}


// 랜덤 곡 가져오기 쿼리
$sql = "SELECT *,
(SELECT AVG(play_count) FROM songs) AS avg_play_count
FROM songs
ORDER BY 
CASE 
    WHEN play_count <= (SELECT AVG(play_count) FROM songs) THEN RAND()
    ELSE RAND() + 1 
END,
play_count ASC; 
";
$result = $conn->query($sql);

$shuffleplaylist = array();
$playNum = 1;

while ($row = $result->fetch_assoc()) {
    $shuffleplaylist[] = array(
        'play_num' => $playNum++,
        'id' => $row['index_number'],
        'title' => $row['title'],
        'index' => $row['index_number'],
        'play_now' => $row['play_now'],
        'play_count' => $row['play_count'],
        'avg_play' => $row['avg_play_count']
    );
    $avg_play_count = $row['avg_play_count'];
}

file_put_contents($userPlaylistFile, json_encode($shuffleplaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$playlist = json_decode(file_get_contents($userPlaylistFile), true);

echo '<div class="d-flex justify-content-between align-items-center">';
echo '<h3>상재의 노래주머니</h3>';
echo '<div class="do_btn btn-outline-dark float-right" id="byebye">무한 퇴근곡</div>';
echo '<div class="do_btn btn-outline-dark float-right" id="updateButton">재생목록 업데이트</div>';
echo '</div>';
echo '<div id="totalSongs"><small>전체 곡 개수: ' . count($playlist) . '곡, [개발자 확인용] 평균 재생 횟수: ' . $avg_play_count . '</small></div>';
echo '<ul>';

foreach ($playlist as $index => $song) {
    $class = ($index === 0) ? 'current-song alert alert-danger' : 'alert alert-light';
    echo '<li class="' . $class . '"><span data-index="' . $index . '" data-file="' . htmlspecialchars($song['title']) . '">' . htmlspecialchars($song['title']) . '</span></li>';
}

echo '</ul>';
$conn->close();
?>


</div>

<div id="audioPlayerContainer">
    <div id="audioInfo">
        <p id="songTitle"></p>
    </div>
    <audio id="audioPlayer" controls onended="playNext()">
        Your browser does not support the audio element.
    </audio>
</div>

<script>
    var $userIP = "<?php echo $_SERVER['REMOTE_ADDR'];?>";
var playlist = <?php echo json_encode($playlist); ?> || [];
const byeByeSongPath = 'bye_song/또 만나요 (See you again).mp3';
const audioPlayer = document.getElementById('audioPlayer');
const songSpans = document.querySelectorAll('ul li span');
const songTitle = document.getElementById('songTitle');
let currentSongIndex = 0;
let isByeByePlaying = false;
playSong(0);
document.getElementById("updateButton").addEventListener("click", function () {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "update_total_songs_and_playlist.php", true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var response = xhr.responseText;
            alert(response);
            console.log(response);

            updatePlaylist();
        }
    };
    xhr.send();
});

document.querySelectorAll('ul li span').forEach((span, index) => {
    span.addEventListener('click', function() {
        isByeByePlaying = false; // 특별한 곡의 재생을 중단
        onOtherSongSelected(); // 다른 곡 선택 시 호출되는 함수
        playSong(index); // 선택된 곡 재생
    });
});

document.getElementById("byebye").addEventListener("click", function () {
    playSpecialSong("또 만나요 (See you again)");
    isByeByePlaying = true; // '또 만나요' 재생 플래그 설정
});

// 다른 곡 선택시 이벤트 핸들러
function onOtherSongSelected() {
    isByeByePlaying = false; // 다른 곡이 선택되면 플래그 해제
    playNext(); // 다음 곡을 재생
}



function playSpecialSong(songTitle) {
    audioPlayer.src = byeByeSongPath;
    audioPlayer.play();
    console.log(`${songTitle} 재생 중...`);
    document.getElementById('songTitle').innerText = songTitle;
}

// 플레이어의 onended 이벤트 핸들러 설정
audioPlayer.onended = function() {
    if (isByeByePlaying) {
        // '또 만나요'가 재생 중인 상태라면 다시 재생
        playSpecialSong("또 만나요 (See you again)");
    } else {
        // '또 만나요'가 아니라면 다음 곡 재생
        playNext();
    }
};
function total_songs_update(index) {
    // 재생된 횟수
    $.ajax({
        url: 'update_play.php',
        type: 'POST',
        data: {
            index: playlist[currentSongIndex].index
        },
        success: function (response) {
            console.log(response);
        },
        error: function () {
            console.error('Error while making the AJAX request.');
        }
    });
}

function updateCurrentSongTitle() {
    const currentSong = playlist[currentSongIndex];

    if (currentSong) {
        const currentSongTitle = currentSong.title;
        const songid = currentSong.index;
        const index = currentSong.play_num;
        const playnow = currentSong.play_now;
        // play_now(index);
    }
}

function playSong(index) {

    // 현재 재생 중인 노래에 대한 클래스 제거
    if (songSpans && songSpans[currentSongIndex] && songSpans[currentSongIndex].parentNode) {
        songSpans[currentSongIndex].parentNode.classList.remove('current-song', 'alert', 'alert-light', 'alert-danger');
    }

    // 노래 정보 가져오기
    const song = playlist[index];
    const musicFile = 'music/' + song.title + '.mp3'; // 전체 노래 파일 경로
    audioPlayer.src = musicFile;
    console.log('현재 재생중인 곡:', song.title, '/', `${song.play_num}`, '/', `${song.avg_play_count}`);
    // 현재 재생 중인 노래 표시 업데이트
    if (songSpans && songSpans[index] && songSpans[index].parentNode) {
        // 클래스 추가
        songSpans.forEach(span => span.parentNode.classList.remove('current-song', 'alert', 'alert-light', 'alert-danger'));
        songSpans[index].parentNode.classList.add('current-song', 'alert', 'alert-light', 'alert-danger');
    }

    // 플레이리스트의 각 li 엘리먼트에 대해 현재 재생 중인 곡을 시각적으로 표시
    document.querySelectorAll('ul li').forEach((li, i) => {
        li.classList.remove('current-song', 'alert', 'alert-light', 'alert-danger');
        if (i === index) {
            li.classList.add('current-song', 'alert', 'alert-danger');
        }
    });

    // 노래 제목 업데이트
    songTitle.innerText = song.title;
    currentSongIndex = index;

    updateCurrentSongTitle();


    // 자동으로 재생 시도
    audioPlayer.play().catch(error => {
        console.error("Autoplay was prevented. Please interact with the page to enable autoplay.");
    });

    // 재생 중인 노래일 때만 재생 횟수 업데이트
    total_songs_update(currentSongIndex);
}

function playNext() {
    if (isByeByePlaying) {
        playSpecialSong("또 만나요 (See you again)");
    } else {
        if (currentSongIndex < (playlist.length - 1)) {
            playSong(currentSongIndex + 1);
        } else {
            playSong(0); // 플레이리스트 처음으로 돌아가기
        }
    }
}


function updatePlaylist() {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "get_playlist.php", true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var updatedPlaylist = JSON.parse(xhr.responseText);

            // 업데이트된 플레이리스트로 현재 재생 중인 곡 갱신
            // 배열이 비어있지 않은 경우에만 findIndex 메서드를 사용합니다.
            if (updatedPlaylist.length > 0) {
                const currentIndex = updatedPlaylist.findIndex(song => song.title === playlist[currentSongIndex].title);

                // 노래 리스트 업데이트
                updateTotalSongs();

                // 현재 재생 중인 노래 표시 업데이트
                updateCurrentSongTitle();

                // 플레이어 업데이트
                updatePlaylistUI(updatedPlaylist);

                // 현재 재생 중인 노래에 alert-danger 클래스 추가
                document.querySelectorAll('ul li').forEach((li, i) => {
                    li.classList.remove('current-song', 'alert', 'alert-light', 'alert-danger');
                    if (i === currentIndex) {
                        li.classList.add('current-song', 'alert', 'alert-danger');
                    }
                });
            }
        }
    };
    xhr.send();
}


// 두 번째 updatePlaylist 함수 이름을 updatePlaylistUI로 변경합니다.
function updatePlaylistUI(songs) {
    const ul = document.querySelector('ul');
    const currentIndex = songs.findIndex(song => song.title === playlist[currentSongIndex]?.title);
    playlist = songs;
    ul.innerHTML = '';

    playlist.forEach((song, index) => {
        const li = document.createElement('li');
        const className = (index === currentIndex) ? 'current-song' : '';
        li.className = className;
        li.innerHTML = `<span data-index="${index}" data-file="${song.title}">${song.title}</span>`;
        ul.appendChild(li);
    });

    const newSpans = document.querySelectorAll('ul li span');
    newSpans.forEach((span, index) => {
        span.addEventListener('click', function () {
            playSong(index);
        });
    });
}


    const newSpans = document.querySelectorAll('ul li span');
    newSpans.forEach((span, index) => {
        span.addEventListener('click', function () {
            playSong(index);
        });
    });

    function updateTotalSongs() {
    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (this.readyState === 4 && this.status === 200) {
            const totalSongsElement = document.getElementById('totalSongs');
            totalSongsElement.innerHTML = '<small>전체 곡 개수: ' + this.responseText + '곡, 평균 재생 횟수: ' + avg_play_count + '</small>';
            // 평균 재생 횟수를 서버에서 가져오도록 수정되어야 합니다.
        }
    };
    xhr.open('GET', 'update_total_songs.php', true);
    xhr.send();
}


setInterval(function () {
    var xhr = new XMLHttpRequest();
    var userIP = "<?php echo $_SERVER['REMOTE_ADDR']; ?>";
    var userPlaylistFile = 'playlist/' + userIP + '.json'; // 수정된 부분: 파일명에 점을 유지

    xhr.onreadystatechange = function () {
        if (this.readyState === 4) {
            if (this.status === 200) {
                try {
                    var updatedPlaylist = JSON.parse(this.responseText);
                    if (JSON.stringify(playlist) === JSON.stringify(updatedPlaylist)) {
                        console.log('플레이리스트가 유지되고 있습니다. JSON 파일명: ' + userPlaylistFile);
                    } else {
                        updatePlaylist(updatedPlaylist);
                        console.log('플레이리스트가 업데이트 되었습니다. JSON 파일명: ' + userPlaylistFile);
                    }
                } catch (error) {
                    console.error('Error parsing JSON:', error);
                }
            } else {
                console.error('Error fetching ' + userPlaylistFile + '. Status:', this.status);
            }
        }
    };

    xhr.onerror = function () {
        console.error('Network error occurred while fetching ' + userPlaylistFile + '.');
    };

    xhr.open('GET', userPlaylistFile, true);
    xhr.send();
}, 1000);


    setInterval(updateTotalSongs, 5000);
</script>

</body>
</html>