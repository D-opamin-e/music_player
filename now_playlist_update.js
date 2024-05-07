const ytpl = require('ytpl');
const ytdl = require('ytdl-core');
const fs = require('fs').promises;
const { updateDatabase } = require('./dbModule'); // 경로에 맞게 수정

// IP 주소를 환경 변수로 설정
process.env.CLIENT_IP = process.argv[2];
const clientIp = process.env.CLIENT_IP;

const playlistUrl = 'https://www.youtube.com/playlist?list=PLFOfE4MKodHeVgf0WyzOMc-Zfj0nKtfhx';
const musicFolder = './music/';

async function getCurrentSongIndex() {
  // 여기에 현재 재생 중인 곡의 인덱스를 가져오는 로직을 추가
  // 예: 특정 API를 호출하여 현재 재생 중인 곡의 정보를 받아와서 인덱스를 추출
  // 또는 다른 방법으로 현재 재생 중인 곡의 인덱스를 결정할 수 있습니다.
  // 이 예제에서는 임의로 0을 반환하도록 작성했습니다.
  return 0;
}

async function downloadMissingSongs() {
  let hasNewSongs = false; // 새로운 곡이 있는지 여부를 나타내는 변수
  const resultArray = [];

  try {
    const playlistInfo = await ytpl(playlistUrl, { limit: Infinity });
    const videos = playlistInfo.items;

    for (const video of videos) {
      const videoId = video.id;
      const videoTitle = video.title;

      const sanitizedVideoTitle = videoTitle.replace(/[\/\?<>\\:\*\|":]/g, '');
      const filePath = `${musicFolder}${sanitizedVideoTitle}.mp3`;

      try {
        await fs.access(filePath);
      } catch (error) {
        const readableStream = ytdl(videoId, { quality: 'highestaudio' });
        const fileContent = await streamToBuffer(readableStream);

        await fs.writeFile(filePath, fileContent);

        // 신규 추가된 곡 정보를 개별 라인으로 출력
        console.log(`신규 추가된 곡: ${videoTitle}`);
        hasNewSongs = true; // 새로운 곡이 추가되었음을 표시
        resultArray.push(videoTitle); // 새로운 곡의 정보를 배열에 추가
      }
    }

    if (!hasNewSongs) {
      console.log(`최신 재생목록입니다! / 요청 IP : ${clientIp}`);
    }
  } catch (error) {
    console.error('Error:', error.message);
  }
}

async function streamToBuffer(stream) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    stream.on('data', chunk => chunks.push(chunk));
    stream.on('end', () => resolve(Buffer.concat(chunks)));
    stream.on('error', error => reject(error));
  });
}

async function updateDatabaseAndPlaylist() {
  await updateDatabase();

  const currentSongIndex = await getCurrentSongIndex();
  await insertNewSongsToPlaylist(currentSongIndex);

  console.log(`now_playlist_update.js - DB 업데이트 성공 | ${clientIp} `);
}

async function insertNewSongsToPlaylist(currentSongIndex) {
  try {
    const playlistInfo = await ytpl(playlistUrl, { limit: Infinity });
    const videos = playlistInfo.items;

    for (const video of videos) {
      const videoId = video.id;
      const videoTitle = video.title;

      const sanitizedVideoTitle = videoTitle.replace(/[\/\?<>\\:\*\|":]/g, '');

      try {
        await fs.access(`${musicFolder}${sanitizedVideoTitle}.mp3`);

        const songIndex = await getSongIndexInPlaylist(videoTitle);

        if (songIndex === -1) {
          const newIndex = currentSongIndex + 1;
          await insertSongToPlaylist(newIndex, videoTitle);
          const updatedPlaylist = await getUpdatedPlaylist();
          await updatePlaylist(updatedPlaylist);
        }

        console.log(`다운 스킵: ${videoTitle} (Already exists)`);
      } catch (error) {
        // File does not exist, download and save
        // (the rest of the code remains the same)
      }
    }
  } catch (error) {
    console.error('Error:', error.message);
  }
}

async function insertSongToPlaylist(index, title) {
  connectToDatabase();

  try {
    const insertQuery = `INSERT INTO songs (title, index_number) VALUES (?, ?)`;
    const values = [title, index];

    await new Promise((resolve, reject) => {
      connection.query(insertQuery, values, (err, results) => {
        if (err) {
          console.error('Error inserting song into playlist: ' + err);
          reject(err);
        } else {
          console.log(`Inserted song "${title}" at index ${index}`);
          resolve(results);
        }
      });
    });
  } finally {
    disconnectFromDatabase();
  }
}

async function getSongIndexInPlaylist(title) {
  connectToDatabase();

  try {
    const selectQuery = 'SELECT index_number FROM songs WHERE title = ?';
    const values = [title];

    return await new Promise((resolve, reject) => {
      connection.query(selectQuery, values, (err, results) => {
        if (err) {
          console.error('Error getting song index in playlist: ' + err);
          reject(err);
        } else {
          const index = results.length > 0 ? results[0].index_number : -1;
          resolve(index);
        }
      });
    });
  } finally {
    disconnectFromDatabase();
  }
}

async function main() {
  await downloadMissingSongs();
  await updateDatabaseAndPlaylist();
}

main();