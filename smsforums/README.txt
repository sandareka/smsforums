*****************************************************************Sun Apirl 01 12:45:20 2012
*Sandareka Wickramanayake . sw0900@gmail.com
*Department of Computer Science and Engineering, University of 	Moratuwa
****************************************************************
Thank you for using smsforums plugin
About:
This plug-in send a text message to the mobile phones of all the subscribers of particular forum post once this service is enables. As well subscribers can reply via text messages. They have send the reply in the follwing format.
"discussion_id: reply" (Discussion_id is included in the text message they receive)
On the other hand if anyone don't want to receive replies for a particular forum post he/she can be unsubscribed for that via sending an sms saying "discussion_is: unsub".  
Note: 
SMS functionality depends on the sms gateway you use. Here as an example ozeki sms gate way has been used. smsforum_send and smsforum_get_received_sms methods have to be edited according to your sms gateway. 
Once you install the plug-in excute the following sql query to make a feild called "texted" in the "forum_post" table 
		" ALTER TABLE `mdl_forum_posts`  ADD `texted` INT(2) 			UNSIGNED NOT NULL DEFAULT '0' COMMENT 'To check 			whether a particular forum post has been 					texted' AFTER `mailnow` "
Installation:
Before you start the installation please read the instructions. 
	
	1.	rename the plugin folder to moodle_notifications if you have chosen a different name during the repository cloning

	2.	move the folder to the blocks directory of your Moodle installation

	3.	Login in your Moodle platform as Administrator and
		then Site Administration ->Notifications 

	4.	Upgrade

At this point the tables should be created and the plugins should be available in the Blocks list.


