<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of block_smsforums_test
 *
 * @author User
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/smsforums/block_smsforums.php');
global $DB;
 Mock::generate(get_class($DB), 'mockDB');

 /**
 * Test subclass that makes all the protected methods we want to test public.
 */
class testable_block_smsforums extends block_smsforums {

    public function __construct() {
        $this->id       = 16;
        $this->cm       = new stdclass();
        $this->course   = new stdclass();
        $this->context  = new stdclass();
    }

    public function smsforum_service_subscribe($userid,$telno) {
        parent:: smsforum_service_subscribe($userid,$telno);
    }

//    public function aggregate_grading_grades_process(array $assessments, $timegraded = null) {
//        parent::aggregate_grading_grades_process($assessments, $timegraded);
//    }

}


class blocksmsforumstest extends UnitTestCase{
    protected $smsforum;
    protected $sms;
    var $realDB;

     function setUp() {
         global $DB;
         $this->realDB = $DB;
         $DB           = new mockDB();
         $this->smsforum = new block_smsforums();
     }

     function tearDown() {
         global $DB;
         $DB = $this->realDB;
         $this->smsforum = null;
     }

    public function test_smsforum_service_subscribe(){
     global $DB;
     $smsforum=new block_smsforums();
     $expected = new stdClass();
     $expected->userid = '1';
     $expected->telno = '94712393027';
     $DB->expectOnce('insert_record', array('block_smsforums_subcriptions', $expected));

     $smsforum->smsforum_service_subscribe('1','94712393027');
    }

    public function test_smsforum_service_subscribe1(){
     global $DB;
     $smsforum=new block_smsforums();
     $expected = new stdClass();
     $expected->userid = '7';
     $expected->telno = '94712393674';
     $DB->expectOnce('insert_record', array('block_smsforums_subcriptions', $expected));

     $smsforum->smsforum_service_subscribe('7','94712393674');
    }

     public function test_smsforum_service_subscribe2(){
     global $DB;
     $smsforum=new block_smsforums();
     $expected = new stdClass();
     $expected->userid = null;
     $expected->telno = '94712393674';
     $DB->expectNever('insert_record', array('block_smsforums_subcriptions', $expected));

     $smsforum->smsforum_service_subscribe(null,'94712393674');
    }


    public function test_smsforum_service_subscribe3(){
     global $DB;
     $smsforum=new block_smsforums();
     $expected = new stdClass();
     $expected->userid = '7';
     $expected->telno = null;
     $DB->expectNever('insert_record', array('block_smsforums_subcriptions', $expected));

     $smsforum->smsforum_service_subscribe('7',null);
    }

    function test_smsforum_service_unsubscribe(){
      global $DB;
     $smsforum=new block_smsforums();
     $expected = new stdClass();
     $expected->userid = '5';
     $DB->expectNever('delete_records', array('block_smsforums_subcriptions', $expected));

     $smsforum->smsforum_service_unsubscribe('5');

    }

    function test_smsforum_service_unsubscribe1(){
      global $DB;
     $smsforum=new block_smsforums();
     $expected = new stdClass();
     $expected->userid = '3';
     $DB->expectNever('delete_records', array('block_smsforums_subcriptions', $expected));

     $smsforum->smsforum_service_unsubscribe('3');

    }

     public function test_smsforum_unsubscribe(){
     global $DB;
     $smsforum=new block_smsforums();
     $sub = new stdClass();
     $sub->userid = '4';
     $sub->discussion_id = '6';
     $DB->expectOnce('insert_record', array('block_smsforums_forum_subs', $sub));

     $smsforum->smsforum_unsubscribe('4','6');
    }

     public function test_smsforum_unsubscribe1(){
     global $DB;
     $smsforum=new block_smsforums();
     $sub = new stdClass();
     $sub->userid = '1';
     $sub->discussion_id = '5';
     $DB->expectOnce('insert_record', array('block_smsforums_forum_subs', $sub));

     $smsforum->smsforum_unsubscribe('1','5');
    }

    public function test_smsforum_unsubscribe2(){
     global $DB;
     $smsforum=new block_smsforums();
     $sub = new stdClass();
     $sub->userid = '4';
     $sub->discussion_id = null;
     $DB->expectNever('insert_record', array('block_smsforums_forum_subs', $sub));

     $smsforum->smsforum_unsubscribe('4',null);
    }


    function test_visibility() {
    $new_block=new block_smsforums();
    $temp=$new_block->applicable_formats();
    $this->assertEqual($temp, array('course-view' => true,'course-view-social' => false));
    }
    
    
}
?>
