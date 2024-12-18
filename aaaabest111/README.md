
# CAT5 - high performance on a single server. 

i like to f/w rust but if anyone wants to dev this php 8.4.1 app either steal the code and pretend u made it or put a issue or pull request or someting in. 



This imageboard, inspired by classical chan-style boards, provides a simple and efficient environment for users to create threads, post replies, and share images or short video clips. It uses PostgreSQL as the database backend, PHP for server-side processing, and Nginx as the web server, generating static HTML pages to serve threads and indexes for fast delivery. The board is designed with security and maintainability in mind, including features like CSRF protection, tripcodes for user identification, and and headless administrator interface for content moderation.

To get started, you will need a working Ubuntu server (24.04 or later) with Nginx, PHP (version 8.4.1 or compatible), and PostgreSQL installed. After setting up your server environment, run the included installation script to create the necessary database schema, directories, and initial index pages. Once installed, simply point your browser to the board’s index page and begin posting. The application will handle the generation of thread pages and indexes automatically after each new post or moderation action.

If you wish to migrate or restore the board, create a PostgreSQL dump of your database using pg_dump and back up any uploaded files. Restoring involves creating the database and user on the new server, applying the SQL backup, and then uploading your application directory and media files. As the board grows, you can optimize performance by adding indexes, caching layers, or external tools to handle load balancing and content delivery. This README, while not exhaustive, should serve as a starting point for understanding the board’s functionality, configuration, and long-term maintenance strategies.





POSTGRESQL, mp4, jpg, png, gif, webp, tripcodes, replying, anti-metadata, multiple boards and optimized for great performance. It is trying to push php as far as possible- this is a solid app and its simplicity is its strength. You could run over 100 boards easily. 

If ur brand new to postgreSQL its a tiny bit harder to f/w than mysql/mariaDB but WELL worth it. Ask AI how to set up a postgres db it will walk you through it. Open postgreSQL.html from your computer with a web broswer it has a getting started guide.


You will need to put db credentials in config.php for every board you make.


put /js and /css in ur site root. And make an index.html there for your site landing page.  Then /b  is its own board. edit config.php and set a to b, then run install.php

$board_name = "b"; // SET THIS TO MATCH THE DIRECTORY. thats it. 


to make more boards copy b to other directories, edit config.php  then run install.php (install.php then deletes itself)

If you re upload and run install.php again on any board it resets the whole board, erases everything and starts the board from scratch. it clears the db, files on the server-
everything for the board. 

moderation is headless and you moderate from reply mode password in config.php. Current implementation is not hashed, its okay for basic security.


this is kept simple on purpose. PostgreSQL is incredibly powerful and  you can turn this into something easily superior to vichan. I will not be
working on this much, it is meant as a starter kit. (I had chat gpt make me a rust version of this, that is what i want to fully dev instead of this)



