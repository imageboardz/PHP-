
# CAT4- Simple php8.4.1 imageboard with decent security. 



***This is designed as ANTI METADATA- meaning if someone gets the db, it would not reveal anything more than the front facing website shows.*** 
If anyone wants to help dev this that would be cool. As long as your input is extremely simple and anti metadata. I will not be working on this much more as im more interested in converting this to Rust and postgres and things like that. (which chatGPT already did, due to this board being so simple! Just feed this code to chatgpt and tell it to change to any lang+db you want)



This is taking vichan, getting rid of literally 99 percent of the bloated codebase, making it work for php 8.4.1, implementing lots of security measures, and making it work with sqlite3. It serves static files like vichan for reduced server load and increased speed (for single server use, that is). Quite incredible, any vichan css or js is super easy to drop into this- so you have a lean unique version. This is WAY MORE tiny than tinyib- this is modernized and is not full of very old code like tinyib is. Classic php apps had thier day where they shined. Objectively- php apps that do not keep up and update they code are garbage and full of security holes. 




Yes, every time you feed this code to ai it will point out more security issues. That is the nature of php. This framework / engine is intended as an app that is 90 percent done and requires 10 percent effort for you to make sure it is secure- which includes your server settings and feeding this to ai every so often and telling it to audit security for you. STILL tho- vichan (and similar) never wanted you to do that... which says a lot about how little the others value your security. There are obvious things like encrypting the db / placing it outside of web browser reach, but this is a dev area not a production showcase. 

 

Here is what vichan should have done about 4 years ago. Make a small modular framework that can easily keep up with the latest php versions. Then make it modular, so if one wants any of the features/looks of vichan, it is implemented with seperate files. Being ultra modular like that the main code can keep up with the latest php versions and never gets halted like vichan is halted with each new php version update. 



Quick start- php 8.4.1, sqlite3, nginx (or similar to serve static files).  Upload the css/ js to ur site root, board1 is an example board, simply copy it into other directories. Run install.php in each board, makes the db for you and populates the tables for you. Bam. 

Supports jpg gif png webp mp4 uploads. Headless admin from reply mode pages. No extra metadata such as ip ever collected or stored. No timestamps displayed on posts. Pagination. Generates static files which makes pages load fast for visitors. Very small and flexible codebase. 

![Screenshot 2024-12-13 003913](https://github.com/user-attachments/assets/7bbf22ac-97d6-47c1-bd8a-13117a0218a6)


Note... i'm all about simplicity and security- it is a great way to mitigate some of the inherent problems in php. This app serves static files. That is quite secure by default! The attack surface for static files is very small. The trade off for security is keeping things absurdly simple. 

***there is improved security and power in simplicity***

No ip stored anywhere. No time and date on posts. By keeping things simple, this enhances both privacy and security. A hacker obtaining the db would get no metadata or personal info on any poster. Vichan, for example, has been hacked in the past and all ip's of posters leaked. For the most part, all that is nonsense. Modern apps that store ip for banning people are just silly- most anyone sane comes in from a vpn or tor or other ip masking technology. Some things change over time, some things stay the same. In the end- simplicity is the superior policy. 


# Below is a comprehensive list of the security features implemented in cat4 :

# Prepared Statements (SQL Injection Mitigation):
All database queries involving user input use prepared statements and bound parameters (e.g., $stmt->bindValue()) rather than string concatenation. This approach significantly reduces the risk of SQL injection attacks by ensuring user input is properly handled by the database driver.

# Input Sanitization and Output Encoding (XSS Prevention):

Input Sanitization: Before storing user input (e.g., name, subject, comment), the code strips HTML tags (strip_tags()) and trims whitespace. This reduces the chance of malicious HTML or JavaScript entering the database.
Output Encoding: When rendering user-generated content, the code uses htmlspecialchars() with robust flags (ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) to encode special characters, preventing injected scripts from executing in the user’s browser. This mitigates Cross-Site Scripting (XSS) attacks.
Allowed File Extensions and MIME Verification (RCE and File Upload Safety):
For file uploads, only specific allowed file extensions (jpg, jpeg, png, gif, webp, mp4) are permitted. Before displaying uploaded files, the code checks their MIME types with finfo to ensure they match the expected content type. This prevents users from uploading and executing arbitrary scripts or files that could lead to Remote Code Execution (RCE).

# No Dynamic Includes Based on User Input (File Inclusion Protection):
The code does not rely on user-supplied input for file paths and does not use include() or require() on user inputs. All file operations are predefined and local, mitigating Local/Remote File Inclusion (LFI/RFI) attacks.

# CSRF Protection with a Global Token:
Every form includes a hidden csrf_token that is compared against a persistent, server-stored token. Because the site largely serves static pages, a stable global CSRF token is used. On form submission, verify_csrf_token() ensures the provided token matches the stored one, preventing Cross-Site Request Forgery (CSRF) attacks.

# No Deserialization of Untrusted Data:
The code does not use unserialize() on user-supplied data, removing the attack vector for deserialization vulnerabilities. It relies on simple text and relational database queries, avoiding PHP’s native serialization of untrusted input.

# Strong Error Handling and Logging Configuration:
Errors are logged to a file but not displayed to end users. This prevents information leakage that attackers could use. Sensitive details about the system are kept hidden, ensuring that attackers learn as little as possible from errors.

# No Use of eval() or Dangerous Functions Without Validation:
Functions like eval(), exec(), or other shell execution functions are not used. This prevents attackers from gaining arbitrary code execution if they trick the application into running malicious code.

# Restricted Upload Directory and Threads Directory:
The code expects proper permissions to be set on uploads/ and threads/ directories, ensuring no PHP execution is allowed there. By serving static files only and not allowing scripts in these directories, even if a malicious file were to be uploaded (which is prevented by checks), it could not be executed as PHP code.

# Minimal Dependency on External Libraries:
The code does not rely on external dependencies or frameworks that might introduce additional vulnerabilities. This reduces the attack surface related to unpatched third-party code.

# Production-Grade Best Practices:
Suggestions are made for running over HTTPS, setting secure and httponly cookies, and hardening the server environment (e.g., disabling allow_url_include, proper file permissions). Although some of these are environment-level settings, the code is designed with these best practices in mind.


# These features together create a robust security posture for the imageboard, greatly reducing common PHP application vulnerabilities such as SQL injection, XSS, CSRF, insecure file uploads, and other common web exploits.

# So if you are looking for an imageboard type app, there are a couple things to know. Old versions of php are a joke for security. Code made for old versions of php is a joke for security. Latest version of php and code MADE for the latest version of php is best. If the code you run is not small enough to feed to ai to audit for security, that is a joke for security too. Php code needs CONSTANT auidts for security. Period. Php is easy to work with but hard to secure. Well coded GoLang or best of all Rust lang boards are simply far superior to well coded php imageboards. 








