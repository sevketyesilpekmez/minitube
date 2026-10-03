MiniTube - CSE348 Project S2026
Student: Şevket Yeşilpekmez

Run instructions:
1. Put the minitube folder under your web server htdocs folder.
   Example for XAMPP on macOS:
   /Applications/XAMPP/xamppfiles/htdocs/minitube

2. Start Apache and MySQL from XAMPP.

3. Open:
   http://localhost/minitube/index.html

4. Click Initialize Database.
   This runs generate_data.php first, creates seed.sql from text input files, then runs install.php.

5. Login example:
   username: ahmet1
   password: pass1

Main files:
- index.html: initial page
- generate_data.php: reads text files and creates seed.sql
- install.php: creates database/tables and imports seed.sql
- login.html / login.php: authentication
- feed.html / feed.php: homepage with subscribed channel videos, filters, sorting, pagination
- channel.html / channel.php: channel details and subscribe/unsubscribe
- watch.html / watch.php: video details, view count, SQL CASE badge, comments, likes
- sql.html / sql.php: arbitrary SQL query page
- profile.php: user profile and watch history
- liked.php: liked videos page

Text input files used by generate_data.php:
- first_names.txt
- last_names.txt
- countries.txt
- categories.txt
- video_titles.txt
- comments.txt
- youtube_links.txt

Notes:
- Image and video fields use online URLs, not local media files.
- Extra tables VIDEO_LIKES and WATCH_HISTORY support optional features.
- The required table names and attributes are preserved.
