# adelia2









Simple Imageboard
A minimalist imageboard application designed to run on PHP 8.4.1 and above, using SQLite for storage. This application provides a basic, yet functional imageboard with a focus on simplicity and robust security measures.

Features
Thread Creation and Replies:
Users can create new threads with a name (supporting tripcodes), a title, and a message, optionally including an image or video. Other users can reply to these threads.

Tripcodes:
Users can create a unique signature (tripcode) for their name by using # or ## in their name fields to create identity-like hashes.

File Uploads:
Supports uploading images in JPEG, PNG, GIF, WEBP formats, as well as MP4 videos. File types and sizes are validated, and images are checked for type consistency.

Pagination:
The board’s main page supports pagination, displaying threads in a user-friendly manner even as the board grows.

Reply Numbering:
Replies are consistently numbered per thread. The first reply is always "Reply #1," the second is "Reply #2," and so on, ensuring clarity and consistency between the thread view and the main board’s last-reply snippet.

CSRF Protection:
All form submissions are protected by Cross-Site Request Forgery (CSRF) tokens.

XSS Prevention:
User-supplied content is sanitized with htmlspecialchars(), preventing malicious HTML/JS injection.

SQL Injection Mitigation:
Prepared statements are used for database inserts and updates. Direct queries involving user input are type-cast to integers. The database connection uses SQLite in a controlled environment, minimizing injection risk.

Graceful Error Handling:
Database and file operations are wrapped in try/catch blocks with meaningful error messages. Upload failures and database issues are cleanly handled.

Secure Tripcodes:
Though primarily a display feature, tripcode generation uses salts and hashing to provide a simple, secure way for users to prove identity-like signatures on posts.

Dark-Themed Responsive Layout:
A simple CSS-based dark theme ensures the board is easy to read and can be used comfortably on both desktop and mobile devices.

Security Features (Detailed)
CSRF Protection:
Every POST form includes a CSRF token generated on the server side and stored in the user’s session. On form submission, the token is verified, ensuring that requests originate from the site’s pages and not malicious third-party domains.

XSS Mitigation:
All user-generated content (name, title, message) is passed through htmlspecialchars() before output. This prevents the injection of HTML or JavaScript that could compromise user sessions or manipulate the page.

SQL Injection Prevention:

Parameterized queries: All insert and update statements use prepared statements with bound parameters, eliminating the risk of SQL injection.
Integer casting on IDs: Thread and reply IDs are cast to integers before being used in SQL queries, ensuring no malicious SQL fragments can be injected.
Type-Checked File Uploads:

MIME type validation: The file’s MIME type is checked against a whitelist of allowed types (jpeg, png, gif, webp, mp4).
Image type consistency check: Images are verified using exif_imagetype() to ensure that a .png file is actually a PNG image, and so forth.
Secure random filenames: Uploaded files are stored with random, non-guessable filenames, preventing overwrites and guessing attacks.
Error Logging and Handling:

Logging: PHP errors are logged to a file instead of being displayed to users, preventing the disclosure of sensitive information.
Graceful handling: Database and file operation failures are caught, and the user is shown a safe, sanitized error message rather than raw PHP errors or SQL exceptions.
Tripcodes with Salts:
While tripcodes are a legacy feature and not meant to be cryptographic identifiers, using a secure salt and hashing ensures no straightforward reverse lookups of tripcodes. This provides a stable pseudonymous identity for users.

Requirements
PHP 8.4.1+:
The codebase takes advantage of modern PHP features available in PHP 8.x, such as match expressions, typed properties, and strict typing.

SQLite3 Extension:
SQLite is used for data storage. The sqlite3 extension must be enabled.

Writable Directories:
The /uploads directory must be writable by the server process to store uploaded images and videos.

Basic Web Server Setup:
The application consists of index.php (main board), reply.php (thread view and reply posting), and style.css. It can run on any PHP-enabled web server without additional configurations.

Installation and Setup
Clone or Copy Files:
Place index.php, reply.php, style.css, and the uploads/ folder on your server.

Permissions:
Ensure uploads/ is writable by your web server:

bash
Copy code
chmod 755 uploads/
Run the Board:
Simply navigate to the URL where index.php is located. The board will create board.db automatically if it doesn’t exist.

Start Posting:
Create new threads, reply to existing ones, and upload images or videos as needed
