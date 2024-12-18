# CAT3 - think of this as the json flatfile version of vichan. 




This is literally the best flatfile imageboard on git

put all the files in any directory such as /b /c /d /e all the booards you want. In each directory, edit the top of post.php file where it has options. Chmod ur directory and all files  with proper permissions (just do 777 for testing the board the fist time you run it)  it will make all the directories and json flatfile and index.html in the board for you - bam. done. Delete a post or comment from reply mode and the password found near the top in post.php . (after u delete i did not have it go back to board, this stops bots- just go to /b manually and the comment or post in the reply is gone. i can fix that on request, the way i have it is way more secure it stops automated bots from checking the checkbox and entering in the password to delete posts) If the post returned to the right place after delete, the bot could loop through and delete everything. Oah and make ur own index.html for the home link to go to . for example index.html is your landing page then /b /c /d /e are your boards when you run board.php it makes the index.html for you in the board. Oah and it supports secure tripcodes. Put ##anything in the name field and it generates a trip code when the post is submitted.  











This is by far the best json flatfile imageboard ive ever seen. Writes static files like vichan does so vidsitors have a fast loading site. Tripcodes supported ##whatever in name field generates trip code. Reply functions fully work. css changer. headless admin area to delete any main post or any reply. 




This application provides a simple, flatfile-based imageboard that does not require a traditional database backend. Instead, it stores all posts and threads in a single JSON file and generates static HTML pages for the index and individual threads. This approach makes initial setup simpler and avoids the need to configure a database server, though it’s more suitable for smaller, lower-traffic communities.

How It Works:
All new threads and replies are submitted via a single PHP file named post.php, which handles form submissions, CSRF protection, file uploads, and data persistence. Posts are stored in a data/posts.json file, while uploaded images and videos go into uploads/. Each thread is rendered as threads/thread_X.html, and index pages (index.html, index_2.html, etc.) are automatically regenerated whenever new content is posted. The board also includes a simple admin function for deleting posts or entire threads using a shared password.

Setup and Requirements:

Server Environment:
You will need a server running PHP with the ability to execute PHP scripts, as well as the standard PHP extensions commonly enabled by default. No database is needed.

Directory Structure:
Place post.php in a directory (e.g., /f/), and ensure that uploads/, threads/, and data/ directories exist in the same directory as post.php, and are writable by the web server. Place CSS files in /f/css/ and JavaScript files in /f/js/.

Configuration:
Within post.php, you can adjust $board_name, $admin_password, $secure_salt, and other variables to suit your needs. The $secure_salt should be a unique, random string.

CSRF and Security:
A CSRF token is automatically generated and stored in csrf_secret.txt. The posting forms include this token, ensuring that unauthorized external sites cannot post on your behalf.

Posting and Viewing Threads:
Once you navigate to post.php, it will create index.html if it doesn’t exist. Accessing index.html allows you to start a new thread by filling in the name, subject, comment, and optionally uploading an image or video. When you open a thread’s page, you’ll find a form to reply. Replies are posted back to post.php, and the pages are regenerated.

Alternate Styles:
The board supports multiple stylesheets. A style selector in the footer lets users switch between styles, and their choice is saved in their local browser’s storage. This works for both the index and thread pages.

Administration and Moderation:
To delete posts or entire threads, open the thread page, check the boxes for posts you want to remove, enter the admin password set in post.php, and submit. This updates the JSON file to mark those posts as deleted, and then regenerates the static pages without the deleted content.

Notes and Limitations:
Because this board uses flatfiles instead of a relational database, it may not scale well under high traffic. It’s ideal for small communities or private boards. As traffic grows, consider switching to a relational database approach or implementing caching and more robust moderation tools.

For now, this flatfile-based system provides a straightforward, database-free solution with minimal dependencies, allowing you to quickly deploy a simple imageboard.
