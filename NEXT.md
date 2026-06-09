# Improved logging
- Log every response after sending e-mails (especially SMTP errors)
- Save also the e-mails to the e-mail logs which has been sent by the mass email function from users page
- Add delays between e-mails when sending mass mails, to avoid server errors. Add a progressbar where admin can see, where is the process currently standing (14/25 mail has sent)
- If a server error occurs, wait for 30 sec and try again only those mails, which hasn't been sent due to the error. 
- Add a small text on the user profiles in the admin side, under the last payment: last login (with date and time). If the user has never logged ing, then writhe N/A

# Improved admin user features
- Add a button on the user profiles for admins: Generate new password and send the profile to the user with username password, and login link
- Add TinyMCE to Ado1%

# Improved content
- Add free text to Pénzügyek page before the financial details
- Modify the file size limit of GPX uploads to 1 MB

# Tour notifications
- Add a switch (checkbox) to the user profile, where they can manage if they want to receive new tour announcments/notifications or not. Put it under the other consents
- Add a tour notification button at the edit page of a future tour. It will send the announcment of that tour to everybody who has opt in for Tour notifications