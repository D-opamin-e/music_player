const fs = require('fs');
const path = require('path');
const mysql = require('mysql');

function connectDB() {
    return mysql.createConnection({
        host: 'localhost',
        user: 'root',
        password: 'password',
        database: 'music_player'
    });
}

async function updateDatabase() {
    const connection = connectDB();

    connection.connect(async (err) => {
        if (err) {
            console.error('MariaDB connection error: ', err);
            return;
        }

        console.log('Connected to MariaDB');

        const musicDirectory = path.join(__dirname, 'music');
        const musicFiles = fs.readdirSync(musicDirectory);

        // Get existing songs from the database
        const existingSongs = await getExistingSongs(connection);

        // Remove songs from the database that are not in the music folder
        await removeMissingSongs(connection, existingSongs, musicFiles);

        // Insert new songs into the database
        await insertNewSongs(connection, existingSongs, musicFiles);

        connection.end();
    });
}

async function getExistingSongs(connection) {
    return new Promise((resolve, reject) => {
        connection.query('SELECT id, title, index_number FROM songs', (err, results) => {
            if (err) {
                reject(err);
            } else {
                resolve(results);
            }
        });
    });
}

async function removeMissingSongs(connection, existingSongs, musicFiles) {
    for (const existingSong of existingSongs) {
        const isSongMissing = !musicFiles.includes(`${existingSong.title}.mp3`);

        if (isSongMissing) {
            await removeSong(connection, existingSong.id);
        }
    }
}

async function removeSong(connection, songId) {
    return new Promise((resolve, reject) => {
        connection.query('DELETE FROM songs WHERE id = ?', [songId], (err, results) => {
            if (err) {
                reject(err);
            } else {
                console.log(`Removed song not found in music folder: ${songId}`);
                resolve();
            }
        });
    });
}

async function insertNewSongs(connection, existingSongs, musicFiles) {
    let maxIndex = existingSongs.reduce((max, song) => Math.max(max, song.index_number), 0);

    let queriesCount = 0;
    const totalQueries = musicFiles.length;

    for (const musicFile of musicFiles) {
        const title = path.parse(musicFile).name;
        const indexNumber = maxIndex + 1;

        const isSongExists = existingSongs.some((song) => song.title === title);

        if (isSongExists) {
            console.log(`Song already exists in database: ${title}`);
        } else {
            connection.query(
                'INSERT INTO songs (title, index_number) VALUES (?, ?)',
                [title, indexNumber],
                (insertErr, results) => {
                    queriesCount++;

                    if (insertErr) {
                        console.error('Error inserting data into songs table:', insertErr);
                    } else {
                        console.log(`Inserted song into database: ${title}`);
                        updatePlaylist(process.env.CLIENT_IP, title); // Update playlist for the client IP
                    }

                    if (queriesCount === totalQueries) {
                        connection.end();
                    }
                }
            );

            maxIndex++;
        }
    }
}

function updatePlaylist(userIP, title) {
    try {
        const playlistFilePath = path.join(__dirname, 'playlist', `${userIP}.json`);
        let playlistData = [];

        // Check if the playlist file exists
        if (fs.existsSync(playlistFilePath)) {
            // If the playlist file exists, read its contents
            const playlistJson = fs.readFileSync(playlistFilePath, 'utf8');
            playlistData = JSON.parse(playlistJson);
        }

        // Add the new song to the playlist
        playlistData.unshift({
            play_num: 1, // Assuming initial play count is 1
            id: 1, // Assuming an ID for the song
            title: title,
            index: 1, // Assuming the song index in the playlist
            play_now: 0 // Assuming the song is not set to play now initially
        });

        // Write the updated playlist data back to the JSON file
        fs.writeFileSync(playlistFilePath, JSON.stringify(playlistData, null, 2));

        console.log(`${title} has been added to the playlist for ${userIP}`);
    } catch (error) {
        console.error('Error updating playlist:', error.message);
    }
}

module.exports = { updateDatabase };
