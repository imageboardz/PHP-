
# PHPIB 

# PHP 8.4.1 imageboard

This is a simple message board application developed using PHP 8.4.1 or higher, with SQLite as the database backend. The application allows users to create posts, reply to existing posts, and upload images. Additionally, there is a moderator panel where moderators can manage posts, delete images, and update content. The application is divided into three main components: `index.php` (the main board), `reply.php` (for replying to posts), and `mod.php` (the moderator panel).

Have an index.html as your main landing page and link to all your boards. Make directories for each board, and put all the files in each directory. chmod the directory and files 777. then when you visit the directory from a web browser, the board creates all the needed files/dir/db.  

If you want to change the looks or use a different db, just feed the code to ChatGPT or similar and tell it what to change. PHP is absurd in comparison to Rust or Go imageboards, but if you do use php make sure you use the latest php version, and make sure Ai audits the code as there are ALWAYS security holes in php. The trade off is very real. PHP is easy to work with but not that great. Rust apps are far superior in security and performance. Don't know how to code in rust? No problem! Just have chatgpt make a rust app for you! You can feed chatgpt all this php code for example, and have it convert the code to a Rust or Go web app. (Rust is objectively superior). Also if you are feeling frisky and want something different, if you feed this code to chatgpt and tell it to convert the PHP to work with Firebird embedded db instead of sqlite3, the app will perform much better. Firebird takes a bit of setting up tho. Of course you can tell chatgpt to use a postgres, mongo, maria db, duck db, rocks db or mysql instead of sqlite3 and that can be done easily too.

The whole point of this app is to keep it simple so it can convert to each new version of php or convert to any other db or lang. For very informal apps php is okay, but it is pure stupidity to not use Rust if you want the most secure app possible. Go is okay too, not as good as Rust is tho. 

And for all those who make php imageboards that have hundreds of files and is impossible to feed to chatgpt and only works with old outdated PHP versions-- my little app here does the same thing, so good luck with that. 

## Features

- **Create Posts**: Users can create new posts on the message board by providing a name, text, and optionally an image.
- **Reply to Posts**: Users can reply to existing posts, with replies appearing below the original post.
- **Image Uploads**: Users can upload images with their posts or replies. Images are displayed alongside the respective posts.
- **Moderator Panel**: Moderators can log in to access `mod.php` to manage posts, including editing or deleting posts, deleting images, and updating the moderator password.
- **Pagination**: Posts are paginated to ensure that the board remains easy to navigate even with many posts.
- **Password Protection**: The moderator panel is protected by a default hardcoded password (`mod123`). The moderator can change this password through the moderator panel itself.

## File Overview

### 1. `index.php` - Main Message Board
This is the main page of the message board where users can:
- View existing posts.
- Create new posts by filling in their name, message text, and optionally uploading an image.
- Posts are displayed in a paginated manner, with 10 posts per page.
- Each post includes a reply link that redirects the user to `reply.php` to add a reply.

### 2. `reply.php` - Reply to a Post
This page allows users to reply to an existing post.
- Users can fill in their name, reply text, and optionally upload an image.
- The original post is displayed at the top, followed by any replies.
- Replies are displayed in the order they are added.

### 3. `mod.php` - Moderator Panel
The moderator panel allows admins to manage the content of the board.
- **Login System**: The panel is protected by a simple password (`mod123` by default). This password can be updated by the moderator after logging in.
- **Edit Posts**: Moderators can edit the name or text of any post.
- **Delete Posts**: Moderators can delete posts, which also deletes all replies to that post.
- **Delete Images**: Moderators can delete the images associated with a post without deleting the entire post.
- **Password Management**: Moderators can change the default password from within the panel.
- **Pagination**: The moderator panel displays posts with pagination to keep it organized and manageable.
- **Optional**: Just delete mod.php if you do not want a web facing mod pannel (more secure without one) you can modify any post with a sqlite editor. 
## Technical Overview

- **Language**: PHP 8.4.1 or higher.
- **Database**: SQLite, which provides a lightweight and serverless database for storing posts and replies.
- **Image Uploads**: Images uploaded by users are stored in an `uploads` directory. The allowed file types are JPG, PNG, and GIF.
- **Session Management**: The moderator panel uses PHP sessions to manage moderator login, allowing password-protected access.
- **Error Handling**: PHP error reporting is enabled in the moderator panel (`mod.php`) to aid in debugging during development.

## Installation Instructions

1. **Requirements**:
   - PHP 8.4.1 or higher.
   - A web server like Apache or Nginx.
   - SQLite extension enabled in PHP.

2. **Setup**:
   - Clone or download the application files.
   - Ensure that the `uploads` directory is writable by the web server (e.g., `chmod 777 uploads` for testing purposes).
   - Make sure PHP has permission to write to the database file (`database.db`) or create it if it does not exist.

3. **Accessing the Application**:
   - Navigate to `index.php` to view and interact with the message board.
   - To access the moderator panel, navigate to `mod.php` and log in with the default password (`mod123`).

## Usage Instructions

- **Creating a Post**:
  1. Go to the main page (`index.php`).
  2. Fill in the "Name" (14 characters max) and "Post" fields (up to 14,000 characters).
  3. Optionally, upload an image (JPG, PNG, or GIF).
  4. Click "Post" to publish your message.

- **Replying to a Post**:
  1. Click the "Reply" link under a post to go to the reply page (`reply.php`).
  2. Fill in the "Name" and "Reply" fields and optionally upload an image.
  3. Click "Reply" to submit your response.

- **Moderator Actions**:
  1. Navigate to `mod.php` and enter the password (`mod123` by default).
  2. After logging in, you can edit or delete posts, delete images, or change the moderator password.

## Security Considerations

- **Default Password**: The application uses a hardcoded default password (`mod123`) for the moderator panel. It is highly recommended to change this password immediately after setup to ensure security.
- **Input Validation**: User input is sanitized using `htmlspecialchars()` to prevent Cross-Site Scripting (XSS) attacks.
- **File Upload Restrictions**: Only images with extensions `.jpg`, `.jpeg`, `.png`, and `.gif` are allowed for upload to prevent the execution of malicious scripts.

## Future Improvements

- **Enhanced Authentication**: Replace the simple password protection with a proper user authentication system, potentially using hashed passwords stored in the database.
- **Rich Text Editing**: Add support for a rich text editor to allow users to format their posts.
- **Improved Moderator Tools**: Add more granular permissions, such as different moderator roles or activity logs.
- **Database Migration**: Migrate from SQLite to a more robust database, such as MySQL, for better scalability.

## Troubleshooting

- **Blank Page Issue**: If you encounter a blank page, ensure that error reporting is enabled (`ini_set('display_errors', '1')`). This will help identify PHP errors.
- **Permission Errors**: Make sure the web server has the appropriate write permissions for the `uploads` directory and the `database.db` file.
- **Session Issues**: If login isn't working, ensure that PHP sessions are enabled and properly configured in your server's `php.ini` file.

## License
People have been stealing my code for a long time, i could care less. ChatGPT does all my coding, and i believe code should be open and shared so do whatever the hell you want with the code. 

## Contact
For issues or feature requests, please contact the project maintainer or open an issue in the project's repository.


