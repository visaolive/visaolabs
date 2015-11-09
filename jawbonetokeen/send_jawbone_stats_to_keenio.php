<?php
// Emilio Taylor: 11/09/2015 - VisaoLabs (emilio (at) visaocrm.com)	
// UP by Jawbone to Keen.io Fitness Tracking Script
// The intention of this code is to: 
// 1) Show how easy it is to login to Jawbone and receive the latest tracking data
// 2) Parse Jawbone Daily Summary Data
// 3) Connect with Keen.io to track and report on these analytics	
// 
// Note: 
// > Using the latest Keen.io API
// > Using v.1.33 of the Jawbone API
	
// User tracking array
// These are the credentials used to login to Jawbone to retrieve data
// Update the email and pwd values, but leave the service as "nudge"
// This has been setup in an array so multiple users can be added to compare stats

$users = array(
	array(
		"email" => 'email@test.com',
		"pwd" => 'xxxxxxx',
		"service" => 'nudge',
	),
);	

// Keen.io API Credentials

$collection_name  = 'Jawbone Tracking'; // Collection Name can be whatever you'd like. You'll find reference to this in Keen.io when selecting which Collection to analyze 
$project_id   = 'xxxxxxx';
$write_key    = 'xxxxxxx';


// tracking date
// this will format the date in YYYYMMDD format that Jawbone API Expects for parameters
$date = date('Ymd');


// convert object to array function
function objectToArray( $object )
{
	if( !is_object( $object ) && !is_array( $object ) )
	{
		return $object;
	}
	if( is_object( $object ) )
	{
		$object = get_object_vars( $object );
	}
	return array_map( 'objectToArray', $object );
}

// begin foreach for each user in users array
foreach($users as $user)
{

	$email   		= $user['email'];
	$pwd   			= $user['pwd'];
	$service  		= $user['service'];

	// begin: authentication

	$url 			= 'https://jawbone.com/user/signin/login';

	$postfields 	= array('email'=>$email, 'pwd'=>$pwd, 'service'=>$service);
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // On dev server only!
	$result = curl_exec($ch);
	
	/*
	echo 'Authentication Response: ';
	echo '<pre>';
	print_r(json_decode($result, true));
	echo '</pre>';
	*/

	// results from authentication CURL(POST)
	$result_array 	= json_decode($result, true);

	// token: for subsequent authentication purposes
	$token  		= $result_array['token'];

	// xid: for authentication purposes
	// first_name, last_name: for User Identification 
	$xid    		= $result_array['user']['xid'];
	$first_name 	= $result_array['user']['first_name'];
	$last_name  	= $result_array['user']['last_name'];

	// end: authentication

	// begin: daily summary

	$url = "https://jawbone.com/nudge/api/v.1.33/users/$xid/score";

	// filter tracking for today's date
	$params 		= array('date' => $date);

	// update URL with query string
	$url 		   .= '?' . http_build_query($params);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"x-nudge-token: $token",
		));
	$result = curl_exec($ch);
	
	// result from daily summary CURL (GET)
	$result_object 	= json_decode($result);
	
	// convery daily summary object to array for parsing
	$result_array 	= objectToArray($result_object);

	/*
	echo 'Daily Summary Response: ';
	echo '<pre>';
	print_r($result_array);
	echo '</pre>';
	*/

	$collection_segment 			= 'Daily Summary';
	$distance 						= $result_array['data']['move']['distance'];
	$calories 						= $result_array['data']['move']['calories'];
	$bmr_calories_day 				= $result_array['data']['move']['bmr_calories_day'];
	$goals_steps_performed 			= $result_array['data']['move']['goals']['steps'][0];
	$goals_steps_goal 				= $result_array['data']['move']['goals']['steps'][1];
	$goals_steps_ratio 				= $goals_steps_performed/$goals_steps_goal;
	$goals_workout_time_performed 	= $result_array['data']['move']['goals']['workout_time'][0];
	$goals_workout_time_goal 		= $result_array['data']['move']['goals']['workout_time'][1];
	$goals_workout_time_ratio 		= $goals_workout_time_performed/$goals_workout_time_goal;
	$longest_active 				= $result_array['data']['move']['longest_active'];
	$bg_steps 						= $result_array['data']['move']['bmr_calories_day'];
	$bmr_calories 					= $result_array['data']['move']['bmr_calories'];
	$active_time 					= $result_array['data']['move']['active_time'];

	// end: daily summary

	// send data to Keen.io API

	// create array for daily summary collection
	$data = array(
		"collection_segment" 			=> $collection_segment,
		"user_name" 					=> $first_name.' '.$last_name,
		"distance" 						=> $distance,
		"calories" 						=> $calories,
		"bmr_calories_day" 				=> $bmr_calories_day,
		"goals_steps_performed" 		=> $goals_steps_performed,
		"goals_steps_goal" 				=> $goals_steps_goal,
		"goals_steps_ratio" 			=> $goals_steps_ratio,
		"goals_workout_time_performed" 	=> $goals_workout_time_performed,
		"goals_workout_time_goal" 		=> $goals_workout_time_goal,
		"goals_workout_time_ratio" 		=> $goals_workout_time_ratio,
		"longest_active" 				=> $longest_active,
		"bg_steps" 						=> $bg_steps,
		"bmr_calories" 					=> $bmr_calories,
		"active_time" 					=> $active_time
	);

	// convert daily summary array to JSON
	$data_string = json_encode($data);

	$ch = curl_init("https://api.keen.io/3.0/projects/$project_id/events/$collection_name?api_key=$write_key");
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string );
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

	$result = curl_exec($ch);
	
	// result from keen.io submission CURL(POST)
	$result_array = json_decode($result);

	echo 'Keen.io API Submission Response: If 1: Then Successful, If 0: Then Failure';
	echo '<pre>';
	print_r($result_array);
	echo '</pre>';


}
// end foreach

// great references for Jawbone API Documentation
// http://eric-blue.com/projects/up-api/
// https://niklaslindblad.se/2013/07/jawbone-up-api-updates/ << this provides the latest information for the example I used above

// great references for Keen.io API Documentation
// https://keen.io/docs/api/

?>