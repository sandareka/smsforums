
<?php
require_once($CFG->dirroot.'/mod/forum/lib.php');
/**
 * The project is to develop a plug-in to send sms when a forum post is posted
 * and reply via sms to forum posts.
 */
class block_smsforums extends block_base {

function init() {
    $this->title = get_string('blockname','block_smsforums');
    $this->version = 2012031117;
}
/**
 *
 * @global <type> $USER
 * @global <type> $CFG
 * @global <type> $COURSE
 * @global <type> $DB
 * @return <type>
 * To create the block
 */
function get_content() {	//To get the content of the block

    global $USER, $CFG, $COURSE,$DB;
    $user = $USER->username;	//username of the current user
    $prf  = $CFG->prefix;		//perfix of the moodle tables

    if ($this->content !== NULL) {
        return $this->content;
    }

    if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
    }

    //content of the block which is to be displayed
    $this->content         =  new stdClass;
    $this->content->text   = get_string('wantservice','block_smsforums');
    $this->content->text   .= '<form id="form1" name="form1" method="post" action="">';
    $this->content->text	.= '<table width="180" border="0"><tr>';
    $this->content->text	.= '<td width="60"><input type="submit" name="ok" id="button" value="'.get_string('yes' , 'block_smsforums').'" a align="left"/></td>';
    $this->content->text	.= '<td width="60"><input type="submit" name="no" id="button" value="'.get_string('no' , 'block_smsforums').'" a align="right"/></td>';
    $this->content->text	.= '</tr> </table>';
    $this->content->text	.= '</form>';

    if(isset($_POST['ok'])){		//if someone wants to subscribe for the SMS Forums Service


    $userid= $USER->id;
    $telno= $USER->phone2;
       
    if($DB->record_exists('block_smsforums_subcriptions',array('userid'=>$userid))){ //Check whether the user has already subscribed
        $this->content->text   .= get_string('have_subscribed','block_smsforums');
    }
    else {
        if(strlen($telno)!= 0){ //User should have enter his/her mobile phone no
            $prefix_telno = get_string('prefix_telno','block_smsforums');
            if(strpos($telno,$prefix_telno)!== false){ //The mobile phone no should be in the international format
                $this->smsforum_service_subscribe($userid, $telno);
                $this->content->text .= get_string('enabled', 'block_smsforums');
            }
            else {
            $this->content->text   .= get_string('error_wrong_format','block_smsforums');

            }
       }

        else {
             $this->content->text   .= get_string('error_no_telno','block_smsforums');
        }
    }

  }
    if(isset($_POST['no'])){		//if someone doesn't want subcribe for the SMS Forums Service
        $userid= $USER->id;
        $this->smsforum_service_unsubscribe($userid);
        $this->content->text	.= get_string('disabled' , 'block_smsforums');
  }

    $this->content->footer = '' ;
    return $this->content;

}

/**
 *
 * @global <type> $CFG
 * @global <type> $USER
 * @global <type> $DB
 * @return <type>
 * Periodically run. Put send post to the ozekioutmessage and get received sms from ozekiinmessage
 */

function cron(){
    global $CFG, $USER, $DB;

    $site = get_site();

    // all users that are subscribed to any post that needs sending
    $users = array();

    // caches
    $discussions     = array();
    $forums          = array();
    $courses         = array();
    $coursemodules   = array();
    $subscribedusers = array();

    $timenow   = time();
    $endtime   = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   

     if ($posts = $this->smsforums_get_untexted_posts($starttime, $endtime, $timenow)) {

        if (!$this->smsforum_mark_old_posts_as_texted($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being sent as sms.');
            return false;  // Don't continue trying to text them, in case we are in a cron loop
        }

        foreach ($posts as $pid => $post) {
            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB->get_record('forum_discussions', array('id'=> $post->discussion))) {
                    $discussions[$discussionid] = $discussion;
                } else {
                    mtrace('Could not find discussion '.$discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            $forumid = $discussions[$discussionid]->forum;
            if (!isset($forums[$forumid])) {
                if ($forum = $DB->get_record('forum', array('id' => $forumid))) {
                    $forums[$forumid] = $forum;
                } else {
                    mtrace('Could not find forum '.$forumid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            $courseid = $forums[$forumid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course '.$courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            if (!isset($coursemodules[$forumid])) {
                if ($cm = get_coursemodule_from_instance('forum', $forumid, $courseid)) {
                    $coursemodules[$forumid] = $cm;
                } else {
                    mtrace('Could not find course module for forum '.$forumid);
                    unset($posts[$pid]);
                    continue;
                }
            }


            // caching subscribed users of each forum
            if (!isset($subscribedusers[$forumid])) {
                $modcontext = get_context_instance(CONTEXT_MODULE, $coursemodules[$forumid]->id);
                if ($subusers = forum_subscribed_users($courses[$courseid], $forums[$forumid], 0, $modcontext, "u.*")) {
                   foreach ($subusers as $postuser) {
                        unset($postuser->description);
                        // this user is subscribed to this forum
                        $subscribedusers[$forumid][$postuser->id] = $postuser->id;
                        $users[$postuser->id] = $postuser;
                    }
                    unset($subusers); // release memory
                }
            }

            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }


   if ($users && $posts) {

        $urlinfo = parse_url($CFG->wwwroot);
        $hostname = $urlinfo['host'];

        foreach ($users as $userto) {

            @set_time_limit(120); // terminate if processing of any account takes longer than 2 minutes

            // set this so that the capabilities are cached, and environment matches receiving user
           cron_setup_user($userto);

            mtrace('Processing user '.$userto->id);

            // init caches
            $userto->viewfullnames = array();
            $userto->canpost       = array();
            $userto->markposts     = array();

            // reset the caches
            foreach ($coursemodules as $forumid=>$unused) {
                $coursemodules[$forumid]->cache       = new stdClass();
                $coursemodules[$forumid]->cache->caps = array();
                unset($coursemodules[$forumid]->uservisible);
            }

            foreach ($posts as $pid => $post) {

                // Set up the environment for the post, discussion, forum, course
                $discussion = $discussions[$post->discussion];
                $forum      = $forums[$discussion->forum];
                $course     = $courses[$forum->course];
                $cm         =& $coursemodules[$forum->id];

                // Only active enrolled users are in the list of subscribers
                if (!isset($subscribedusers[$forum->id][$userto->id])) {
                    continue; // user does not subscribe to this forum
                }

                // Don't send sms if the forum is Q&A and the user has not posted
                if ($forum->type == 'qanda' && !forum_get_user_posted_time($discussion->id, $userto->id)) {
                    mtrace('Did not email '.$userto->id.' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user
                if (array_key_exists($post->userid, $users)) { // we might know him/her already
                    $userfrom = $users[$post->userid];
                } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                    unset($userfrom->description);
                    $users[$userfrom->id] = $userfrom; // fetch only once, we can add it to user list, it will be skipped anyway
                } else {
                    mtrace('Could not find user '.$post->userid);
                    continue;
                }
                if($userfrom->id== $userto->id){ // Don't send the sms to the user who posted the forum post
                    continue; 
                }
               

                // setup global $COURSE properly - needed for roles and languages
                cron_setup_user($userto, $course);

                // Fill caches
                if (!isset($userto->viewfullnames[$forum->id])) {
                    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                    $userto->viewfullnames[$forum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                    $userto->canpost[$discussion->id] = forum_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$forum->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        $users[$userfrom->id]->groups = array();
                    }
                    $userfrom->groups[$forum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    $users[$userfrom->id]->groups[$forum->id] = $userfrom->groups[$forum->id];
                }

                // Make sure groups allow this user to see this email
                if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
                    if (!groups_group_exists($discussion->groupid)) { // Can't find group
                        continue;                           
                    }

                    if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                    continue;
                    }
                }

                // Make sure we're allowed to see it
                if (!forum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
                    mtrace('user '.$userto->id. ' can not see '.$post->id);
                    continue;
                }

                //Well now we ready to send sms

                // Prepare to actually send the post now, and build up the content

                $cleanforumname = str_replace('"', "'", strip_tags(format_string($forum->name)));
                $shortname = format_string($course->shortname, true, array('context' => get_context_instance(CONTEXT_COURSE, $course->id)));

                $postsubject = "$shortname: ".format_string($post->subject,true);
                //Make the message to send
                $posttext = $this->smsforum_make_sms_text($course, $cm, $forum, $discussion, $post, $userfrom, $userto);
                if($DB->record_exists('block_smsforums_forum_subs',array("userid"=>$userto->id,'discussion_id' => $discussion->id))){
                    continue;
                }
                // Get the telephone no the userto
                $telno = $DB->get_record('user',array("id"=>$userto->id),$fields='phone2');
                //Sending the sms
                $this->smsforum_send($posttext,$telno->phone2);
            }

        }
    }

   // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);
   $result_list = array();
   $result_list=$this->smsforum_get_received_sms();
   $this-> smsforums_add_received_sms_to_forums($result_list);


    cron_setup_user();

    $sitetimezone = $CFG->timezone;

return true;
}

/**
 *
 * @global <type> $DB
 * @param <type> $userid
 * @param <type> $telno
 * @return <type>
 * Insert the details of the users to the database who wants to subscribe for the service
 */
function smsforum_service_subscribe($userid,$telno){

    global $DB;
    if($DB->record_exists('block_smsforums_subcriptions',array("userid"=>$userid))){
        return true;
    }

    if($userid != null && $telno != null){

    $sub = new stdClass();
    $sub->userid = $userid;
    $sub->telno = $telno;
    return $DB->insert_record('block_smsforums_subcriptions',$sub);}

    else {
        mtrace("Userid or Telephone no is Null");

    }
}

/**
 *
 * @global <type> $DB
 * @param <type> $userid
 * @return <type>
 * Remove the record of the users who wants unsubscribe from the service
 */
function smsforum_service_unsubscribe($userid) {
    global $DB;
    if($DB->record_exists('block_smsforums_subcriptions',array("userid"=>$userid))){
       $DB->delete_records('block_smsforums_subcriptions', array("userid"=>$userid));
       return true;
    }
     return true;
}

/**
 *
 * @global <type> $DB
 * @param <type> $userid
 * @param <type> $discussion_id
 * @return <type>
 * insert details of the users who want to unsubcribe from a particular forum
 */
function smsforum_unsubscribe($userid, $discussion_id) {
    global $DB;
    if($DB->record_exists('block_smsforums_forum_subs',array("userid"=>$userid,"discussion_id"=>$discussion_id))){
        return true;
    }

    if ($discussion_id != null) {
    $sub = new stdClass();
    $sub->userid = $userid;
    $sub->discussion_id = $discussion_id;
    $result=$DB->insert_record('block_smsforums_forum_subs', $sub);
    if($result) {
    mtrace("Error in inserting the record");
    }

return true;

    }

    else{
    mtrace("Null discussion error");
     return false;
    }
}

/**
 *
 * @global <type> $DB
 * @global <type> $CFG
 * @param <type> $posttext: the message to be sent
 * @param <type> $telno: Mobile phone number of the receiver
 * This function insert the forum post as a message to the database of the sms gateway
 * Here I have used ozeki sms gateway.
 * Edit this function according to your sms gateway API
 */
function smsforum_send($posttext,$telno){
    $con = mysql_connect('127.0.0.1',"root","");
    if (!$con) {
        die('Could not connect: ' . mysql_error());
    }
    $mysql_select_db = mysql_select_db("sms", $con);
    $result=mysql_query("INSERT INTO ozekimessageout (receiver,msg,status)VALUES ('$telno', '$posttext', 'Send')");
    if(!$result)
        {
        mtrace("Error in inserting the record");
        return false;
        }
    mysql_close($con);
    return true;
}


/**
 *
 * @global <type> $CFG
 * @global <type> $USER
 * @param <type> $course
 * @param <type> $cm
 * @param <type> $forum
 * @param <type> $discussion
 * @param <type> $post
 * @param <type> $userfrom
 * @param <type> $userto
 * @param <type> $bare
 * @return <type>
 * Get the details of the forum post and make the message
 */
function smsforum_make_sms_text($course, $cm, $forum, $discussion, $post, $userfrom, $userto, $bare = false) {
    global $CFG, $USER;

    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);

    if (!isset($userto->viewfullnames[$forum->id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$forum->id];
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $canreply = forum_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $by = New stdClass;
    $by->name = fullname($userfrom, $viewfullnames); //Get the name of the person who have posted the forum post

    $canunsubscribe = ! forum_is_forcesubscribed($forum);

    $posttext = '';

    if (!$bare) {
        $shortname = format_string($course->shortname, true, array('context' => get_context_instance(CONTEXT_COURSE, $course->id)));
        $posttext  = "$shortname ->".format_string($forum->name,true);

        if ($discussion->name != $forum->name) {
            $posttext  .= " -> ".format_string($discussion->name,true);
            $posttext  .= " -> ".format_string($discussion->id,true);
        }
    }

    // add absolute file links
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_forum', 'post', $post->id);

    $posttext .= "\n------\n";
    if ($bare) {
        $posttext .= " ($CFG->wwwroot/mod/forum/discuss.php?d=$discussion->id#p$post->id)";
    }
    $posttext .= "by ".fullname($userfrom, $viewfullnames)."\n";
    $posttext .= "------";
    $posttext .= format_text_email($post->message, $post->messageformat);
    return $posttext;
}

/**
 *
 * @return <type>
 * Get the received sms and send them to add to the forum
 * Edit this function according to your sms gateway API
 * Here I have used ozeki sms gateway
 * When a reply comes it should be saved in a database and
 */
function smsforum_get_received_sms(){

    $con = mysql_connect('127.0.0.1',"root","");
    if (!$con)
        {
        die('Could not connect: ' . mysql_error());
        }
    $mysql_select_db = mysql_select_db("sms", $con);
    $result=mysql_query("SELECT id,msg,sender FROM ozekimessagein");
    $result_list = array();
    while($row = mysql_fetch_array($result)) {
        $result_list[] = $row;
    }

    foreach ($result_list as $result){
        $result_id = $result['id'];
        $query = "DELETE FROM ozekimessagein WHERE id = '$result_id'";
        if(!mysql_query($query)){
            mtrace("Error in deleting the record".$result_id);
        }
    }

    mysql_close($con);
    return $result_list;
}

/**
 *
 * @global <type> $DB
 * @param <type> $result_list
 * @return <type>
 * Add the received messages as forum posts
 */
function smsforums_add_received_sms_to_forums($result_list){
    global $DB;
        foreach($result_list as $row) {

            list($discussion_id,$msg) = explode(':', $row['msg']);
            $user = $DB->get_record('user',array('phone2'=>$row['sender']));
            if(strcmp($msg,' unsub') != 0)
                {
                $user = $DB->get_record('user',array('phone2'=>$row['sender']));
                $post->discussion = $discussion_id;
                $discussion = $DB->get_record('forum_discussions', array('id' => $post->discussion));
                $forum      = $DB->get_record('forum', array('id' => $discussion->forum));
                $cm         = get_coursemodule_from_instance('forum', $forum->id);
                $context    = get_context_instance(CONTEXT_MODULE, $cm->id);
                $course     = $DB->get_record('course', array('id' => $forum->course));

                $post = new object();

                $post->subject = "Re:$discussion->name";
                $post->message = $msg;
                $post->format = 1; // HTMLFormat
                $post->suscribe = 0; //no sucbscription
                $post->course = $course->id;
                $post->forum = $forum->id;
                $post->discussion = $discussion->id;
                $post->parent = $discussion->firstpost;
                $post->userid = $user->id;
                $post->modified = time();
                $post->created = time();
                $post->mailed = 0;
                $post->attachement = "";

                if (! $post->id = $DB->insert_record("forum_posts", $post)) {
                    return false;
                }

                // Update discussion modified date
                $DB->set_field("forum_discussions", "timemodified", $post->modified, array("id" => $post->discussion));
                $DB->set_field("forum_discussions", "usermodified", $post->userid, array("id" => $post->discussion));

                if (forum_tp_can_track_forums($forum) && forum_tp_is_tracked($forum)) {
                    forum_tp_mark_post_read($post->userid, $post, $post->forum);    }
         }

         else
             {
            $user = $DB->get_record('user',array('phone2'=>$row['sender']));
            $this->smsforum_unsubscribe($user->id, $discussion_id);
             }
    }

}

/**
 *
 * @global <type> $CFG
 * @global <type> $DB
 * @param <type> $starttime
 * @param <type> $endtime
 * @param <type> $now
 * @return <type>
 * Check and return the forum posts which are not texted
 */
function smsforums_get_untexted_posts($starttime, $endtime, $now=null) {
    global $CFG, $DB;

    $params = array($starttime, $endtime);
    if (!empty($CFG->forum_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    return $DB->get_records_sql("SELECT p.*, d.course, d.forum
                              FROM {forum_posts} p
                                   JOIN {forum_discussions} d ON d.id = p.discussion
                             WHERE p.texted = 0
                                   AND p.created >= ?
                                   AND (p.created < ? OR p.mailnow = 1)
                                   $timedsql
                          ORDER BY p.modified ASC", $params);
}

/**
 *
 * @global <type> $CFG
 * @global <type> $DB
 * @param <type> $endtime
 * @param <type> $now
 * @return <type>
 * This function sets 'texted' feild in the 'forum_post' table to 1
 */
function smsforum_mark_old_posts_as_texted($endtime, $now=null) {
    global $CFG, $DB;
    if (empty($now)) {
        $now = time();
    }

    if (empty($CFG->forum_enabletimedposts)) {
        return $DB->execute("UPDATE {forum_posts}
                               SET texted = '1'
                             WHERE (created < ? OR mailnow = 1)
                                   AND texted = 0", array($endtime));

    } else {
        return $DB->execute("UPDATE {forum_posts}
                               SET texted = '1'
                             WHERE discussion NOT IN (SELECT d.id
                                                        FROM {forum_discussions} d
                                                       WHERE d.timestart > ?)
                                   AND (created < ? OR mailnow = 1)
                                   AND texted = 0", array($now, $endtime));
    }
}

 
  public function applicable_formats() {
  return array(
    'course-view' => true,
    'course-view-social' => false);
}


  }
?>
