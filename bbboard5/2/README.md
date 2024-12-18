

![4shout](https://user-images.githubusercontent.com/125363297/220911207-69c30c7c-d1e4-4f1c-a7f2-ddc2ab502e12.png)

<br>

Here is a fork of 4shout. In this version, only one php 
file is needed, and a css file. Thats it. It makes the json db the first time this is run, and stores files in the 
json db. The posts are not displayed with a number of this version to customize it for my needs. Also, i implemented error checking, for example sending a blank message will not work. Also I added additional sanitization against XSS attacks. Works on php 8.2.x  


This is a PHP script that displays a simple message board, allowing users to post messages and displaying all existing messages. The script reads and writes message data from a file named database.json.

When the script is loaded, it checks if the database.json file exists and creates it if it doesn't. It then loads all the existing posts from the file.

When a user submits a message using the form, the script first sanitizes the input by converting special characters to their HTML entities using the htmlspecialchars() function. The script then validates the input, checking if the name is 14 characters or less and if the message is 14000 characters or less.

If the input is valid, the script adds the new message to the beginning of the list of posts and updates the database.json file. It then redirects the user back to the homepage using the header() function.

If the input is invalid, the script sets an error message that will be displayed on the homepage.

The homepage is a simple HTML file with a form for submitting new messages and a list of existing messages. The list of messages is generated using a foreach loop that iterates over the $posts array and generates the HTML code for each message.

The HTML code for each message includes the sanitized name, date, and message text, wrapped in appropriate HTML tags.


