<?php 
/**
 * BeAuth test data 
 * 
 * @author ste@channelweb.it
 * 
 */

class BeAuthTestData extends BeditaTestData {
	var $data =  array(
		'user1'	=> array('userid' 	=> 'giangi', 'passwd' => 'giangi'),
		'user2'	=> array('userid' 	=> 'giangi', 'passwd' => 'giungggg'),
		'user3'	=> array('userid' 	=> 'nuovoutente', 'passwd' => 'nuovapass'),
		'new.user'	=> array('User' => array('userid' => 'nuovoutente', 'passwd' => 'nuovapass')),
		'policy'  => array(
			'maxLoginAttempts' => 1,
			'maxNumDaysInactivity' => 30,
			'maxNumDaysValidity' => 4,
			'authorizedGroups' => 'administrator')
		) ;
	}

?> 