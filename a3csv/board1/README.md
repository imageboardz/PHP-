
# board1 

How It Works-
Automatic Setup:

If the uploads directory doesn't exist, it is created.
If the posts.csv file doesn't exist, it is created with a header row.
Form Submission:

The username, message, and optionally an image are uploaded via the form.
The image is saved in the uploads directory with a timestamped name to avoid conflicts.
The post is appended to posts.csv with a unique ID.
Displaying Posts:

Posts are read from the CSV file, reversed (to show newest first), and displayed on the page.
Each post includes the username, timestamp, message, and image (if provided).
Requirements
PHP must have file upload permissions enabled.
The server must allow writing to the script's directory.
Usage
Upload this file to your server and access it via your browser.
Users can post text and images, which will be saved in the uploads directory and logged in posts.csv.
This is a simple and minimal implementation that can be expanded with features like threading, editing, or moderation.
