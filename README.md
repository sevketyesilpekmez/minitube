# MiniTube

A PHP and MySQL video-platform coursework project for CSE348 Database Management Systems (Spring 2026), by Şevket Yeşilpekmez.

## Features

- Login with hashed passwords and PHP sessions
- Subscription feed with filtering, sorting and pagination
- Channels and subscribe/unsubscribe actions
- Video pages with view counts, likes and threaded comments
- User profiles, watch history and liked videos
- Seven related MySQL tables with foreign keys and uniqueness constraints
- Text-file-based demo data generator: 120 users, 70 channels and 350 videos
- SQL query playground for the database course

Videos are embedded from external URLs; this project does not host uploaded video files.

## Run locally

1. Install XAMPP with PHP and MySQL/MariaDB, then start Apache and the database server.
2. Put this folder under `htdocs/minitube`.
3. Check the local database settings in `db.php` and `install.php`. Both use the `sevket_yesilpekmez` database, local root account and an empty password by default.
4. Open `http://localhost/minitube/index.html` and choose **Initialize Database**. This generates `seed.sql` and runs the installer.
5. Use demo login `ahmet1` / `pass1`.

**Initialization replaces the local `sevket_yesilpekmez` database.** Use a disposable database for this demo. The SQL playground accepts arbitrary queries and the initialization endpoints are intended for a local coursework environment, not a public deployment.

The generated seed data is synthetic. Original file-by-file instructions are in [README.txt](README.txt).
