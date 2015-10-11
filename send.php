<?php
ini_set('max_execution_time', 30000);
    /*
	 * Dengue SMS Reminders
	 * Developed by SEACO IT team
	 * Revision: 1.0 (21.09.2015)
	 * Todo: Build an SMS based token authentication to access / execute this page..
	 *		 Allow researchers to setup their own accounts to send SMSes out
     */
	 
	#Connect to the database -- need a better way for this + use mysqli
	$mysql_user = ""; //Username
	$mysql_password = ""; //Password
	
	$smsdb = mysql_connect('localhost', $mysql_user, $mysql_password); 
	mysql_select_db('', $smsdb); //Enter database name here
	mysql_set_charset('UTF8',$smsdb);
 
	#Set twilio number here (Required)
	$twilio_num = "";
 
    // Step 1: Download the Twilio-PHP library from twilio.com/docs/libraries, 
    // and move it into the folder containing this file.
    require "twilio-php/Services/Twilio.php";
	
	#Make sure curl is enabled
	$_h = curl_init();
	curl_setopt($_h, CURLOPT_HEADER, 1);
	curl_setopt($_h, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($_h, CURLOPT_HTTPGET, 1);
	curl_setopt($_h, CURLOPT_URL, 'https://api.twilio.com' );
	curl_exec($h);
	//var_dump(curl_exec($_h));
 
    // Step 2: set our AccountSid and AuthToken from www.twilio.com/user/account
    $AccountSid = "";
    $AuthToken = "";
 
    // Step 3: instantiate a new Twilio Rest Client
    $client = new Services_Twilio($AccountSid, $AuthToken);
 
	# 4.0 Grab all the individuals to send dengue reminder to => individuals_array
    $individuals_query = "SELECT * FROM `individuals` GROUP BY `cell_phone_number`";
	$individuals_result = mysql_query($individuals_query, $smsdb);
	
	# 4.1 Grab all the active reminders from the database => reminder_array
	$reminders = "SELECT * FROM `reminder` WHERE `active` = 1";
	$reminders_result = mysql_query($reminders, $smsdb);
	
	$countData = 0;
	
	#Process the individuals data and store it in another array
	while($row = mysql_fetch_array($individuals_result)){
			$smsData[$countData]["dv_id"] = $row['DV_ID'];
			$smsData[$countData]["name"] = $row['name'];
			$smsData[$countData]["cell_phone_number"] = "+6".str_replace('-', '', $row['cell_phone_number']); //Remove dashes and append +6 for Malaysia code
			
			switch($row['sms_lang']){
			  case "Malay":
			     $smsData[$countData]["sms_lang"] = 2;
				 break;
			  case "Cina":
				 $smsData[$countData]["sms_lang"] = 3;
				 break;
			  default:
				 $smsData[$countData]["sms_lang"] = 1;
				 break;
			}
		
			$countData++;
	}
	
	#Process reminders and store it in another array
	while($r = mysql_fetch_array($reminders_result)){
		$lang = $r['reminder_lang'];
		$order = $r['reminder_order'];
		
		$smsReminders[$lang][$order] = $r['reminder'];
	}

	#There are two reminders - so lets loop through our list of individuals twice
	#Outer loop - also keeps track of the order
	for($i=1; $i<=2; $i++){
		
		#Inner loop
		for($j=0; $j<sizeof($smsData); $j++){
			
			$j_lang = $smsData[$j]["sms_lang"];
			$number = $smsData[$j]['cell_phone_number'];
			$message = $smsReminders[$j_lang][$i];
			
			echo "From: ".$twilio_num."<br />";
			echo "To: ".$smsData[$j]['cell_phone_number']."<br />";
			echo "Message: ".$smsReminders[$j_lang][$i]."<br />";
			
			$sms = $client->account->messages->sendMessage($twilio_num, $number, $message);
			
			$smsResult = var_export($sms);
			$insertResult = "INSERT INTO `log` (`_id`, `response`) VALUES ('', $smsResult)";
			mysql_query($insertResult, $smsdb);
		}
		
		#SMS-es are not reaching the destination in the order that they were sent (e.g. 2nd SMS reaches first before the 1st)
		#Lets try to pause the script execution for 15 seconds before resuming again..
		sleep(15);
	}
	
	#Grab some metadata for the summary
	$malay_reminder = 0;
	$chinese_reminder = 0;
	$default_reminder = 0;
	
	for($r1 = 0; $r1<sizeof($smsData); $r1++){
		 if($smsData[$r1]["sms_lang"] == 1){
			$default_reminder++;
		 }else if($smsData[$r1]["sms_lang"] == 2){
			$malay_reminder++;
		 }else if($smsData[$r1]["sms_lang"] == 3){
			$chinese_reminder++;
		 }
	}
	
	echo "<h1>Dengue Reminders Summary</h1><br />";
	echo "================================";
	echo "Total sent: ".sizeof($smsData)." x 2";
	echo "Malay reminders: ".$malay_reminder."<br />";
	echo "Chinese reminders: ".$chinese_reminder."<br />";
	echo "Dengue reminders sent!";
	
	#Close the database
	mysql_close($smsdb);
?>