<?php include('../includes/dbconnect.php'); ?>
<?php include('../includes/lib.php'); ?>
<?php
	header('Content-type: application/json');
	
	//read json from request
	$data = json_decode($_POST['data']);
	
	if (VERSION <> $data->{'version'})
	{
		$response = new Response(0,"Access denied");
	}
	else	
	{
		$access_key= $data->{'access_key'};
		$from_user_id = $data->{'from_user_id'};
		$to_user_id = $data->{'to_user_id'};
		$tag_1 = $data->{'tag_1'};
		$tag_2 = $data->{'tag_2'};
		$tag_1_name = $data->{'tag_1_name'};
		$tag_2_name = $data->{'tag_2_name'};
		
		//open databse connection
		$conn = openDBCon();
		
		//verify user existance
		$sql = "SELECT user_id ";
		$sql .= " FROM fb_user ";
		$sql .= " WHERE user_id = " . $from_user_id . " AND ";
		$sql .= " access_key='" . $access_key . "'";
		
		// echo $sql;
		
		$rs = mysql_query($sql);
		 
		if (mysql_num_rows($rs)>0){ 
			
			$sql = "SELECT friend_id, tag_status ";
			$sql .= " FROM friend " ;
			$sql .= " WHERE my_user_id = " . $from_user_id;
			$sql .= " AND friend_user_id = " . $to_user_id;
			
			// echo $sql;
		   
			$rs=mysql_query($sql);
			
			if(mysql_num_rows($rs) > 0){
			
				$row = mysql_fetch_assoc($rs);
				
				$friend_id = $row["friend_id"];
				$tag_status = $row["tag_status"];
				
				//$sql="SELECT tag_id FROM tag WHERE tag_id IN(".$tag_1 ."," .$tag_2 .")";		
				
				if ($tag_1 == 0){
					
					$sql = "SELECT tag_id FROM tag WHERE UPPER(name) ='" . strtoupper($tag_1_name) . "'" ;
					// echo $sql;
					
					$rs=mysql_query($sql);
					
					if (mysql_num_rows($rs) > 0)
					{
						$row = mysql_fetch_assoc($rs);
		
						$tag_1 = $row["tag_id"];
					}
					else
					{
						$today = gmdate('Y-m-d H:i:s', time());
		
						$sql= " Insert into tag (name,created_date) values (";
						$sql .= "'" . $tag_1_name . "',";
						$sql .= "'" . $today . "')"; 
						
						// echo $sql;
						$rs=mysql_query($sql);
						
						$tag_1 = mysql_insert_id();
					} 				
				}
				
				if ($tag_2 == 0){
					
					$sql = "SELECT tag_id FROM tag WHERE UPPER(name) ='" . strtoupper($tag_2_name) . "'" ;
					// echo $sql;
					
					$rs=mysql_query($sql);
					
					if (mysql_num_rows($rs) > 0)
					{
						$row = mysql_fetch_assoc($rs);
		
						$tag_2 = $row["tag_id"];
					}
					else
					{
						$today = gmdate('Y-m-d H:i:s', time());
		
						$sql= " Insert into tag (name,created_date) values (";
						$sql .= "'" . $tag_2_name . "',";
						$sql .= "'" . $today . "')"; 
						
						// echo $sql;
						$rs=mysql_query($sql);
						
						$tag_2 = mysql_insert_id();
					} 				
				}
				
				$sql="SELECT tag_id FROM tag WHERE tag_id IN (" . $tag_1 . "," . $tag_2 . ")";
				
				// echo $sql;
				
				$rs=mysql_query($sql);
	
				if(mysql_num_rows($rs) > 0){						
					
					$response = new Response(1,"Sucess"); 
					
					$response->tag_1 = $tag_1;
					$response->tag_2 = $tag_2;
					$response->tag_1_name = $tag_1_name;
					$response->tag_2_name = $tag_2_name;
					
					$today = gmdate('Y-m-d H:i:s', time());
					
					
					$sql = "SELECT friend_id, tag_status  ";
					$sql .= " FROM friend " ;
					$sql .= " WHERE my_user_id = " . $to_user_id;
					$sql .= " AND friend_user_id = " . $from_user_id ;
					
					//echo $sql;
				   
					$rs=mysql_query($sql);
			
					$tag_status = 0;
			
					if(mysql_num_rows($rs) > 0){
					
						$row = mysql_fetch_assoc($rs);
						
						$r_friend_id = $row["friend_id"];
						$r_tag_status = $row["tag_status"];
						
						if ($r_tag_status == 1)
						{
							$tag_status = 1;
						}
					}else{
						$sql = " INSERT into friend ";
						$sql .= " (my_user_id, friend_user_id, created_date, modified_date) values (";
						$sql .= $to_user_id . ",";
						$sql .= $from_user_id . ",";
						$sql .= "'" . $today . "','" . $today . "')";
						
						// echo $sql;
						
						mysql_query($sql);
					}
					
					$sql = " UPDATE friend SET ";
					$sql .= " tag_1 = " . $tag_1 . ",";
					$sql .= " tag_2 = " . $tag_2 . ","; 
					$sql .= " tag_status = " . $tag_status . ",";
					$sql .= " tag_date = '" . $today . "', ";
					$sql .= " modified_date = '" . $today . "' ";
					$sql .= " WHERE friend_id = " . $friend_id;
					
					//echo $sql;
					
					mysql_query($sql);
					
					if ($tag_status == 0){
						send_apns_notification_on_tagging($from_user_id, $to_user_id, $tag_1, $tag_2, $tag_status, $today );
					}else{
						//find out maximum tag accepted
						$sql = " SELECT max_tag.tag, tag.name, max(cntr) as mcntr FROM ( ";
						$sql .= " 	SELECT tag, ifnull(sum(cnt),0) as cntr FROM ( ";
						$sql .= " 		SELECT tag_1 as tag , count(friend_id) as cnt ";
						$sql .= " 		FROM friend ";
						$sql .= " 		WHERE friend_user_id = " . $from_user_id;
						$sql .= " 		AND tag_status = 1 ";
						$sql .= " 		GROUP BY tag_1 ";
						$sql .= " 		UNION ";
						$sql .= " 		SELECT tag_2 as tag , count(friend_id) as cnt ";
						$sql .= " 		FROM friend ";
						$sql .= " 		WHERE friend_user_id = " . $from_user_id;
						$sql .= " 		AND tag_status = 1 ";
						$sql .= " 		GROUP BY tag_2 ";
						$sql .= " 		) as tagging ";
						$sql .= " 	GROUP BY tag ";
						$sql .= " 	) as max_tag ";
						$sql .= " JOIN tag ON (tag.tag_id = max_tag.tag) ";
						$sql .= " GROUP BY tag.name ";
						
						//echo $sql;
						
						$rs=mysql_query($sql);
						
						if (mysql_num_rows($rs)>0){ 
						
							$row = mysql_fetch_assoc($rs);
							
							$tag_1 = $row["tag"];
							
							$row = mysql_fetch_assoc($rs);
							
							$tag_2 = $row["tag"];
						}else{
							$tag_1 = 0;
							$tag_2 = 0;
						}
						
						//Update frien'd profile
						$sql = " UPDATE fb_user SET ";
						$sql .= " tag_1 = " . $tag_1 . ",";
						$sql .= " tag_2 = " . $tag_2 . ",";
						$sql .= " completed_requests = completed_requests + 1, ";
						$sql .= " modified_date = '" . $today . "' ";
						$sql .= " WHERE user_id = " . $from_user_id; 						
						
						mysql_query($sql);
						
						//find out maximum tag accepted
						$sql = " SELECT max_tag.tag, tag.name, max(cntr) as mcntr FROM ( ";
						$sql .= " 	SELECT tag, ifnull(sum(cnt),0) as cntr FROM ( ";
						$sql .= " 		SELECT tag_1 as tag , count(friend_id) as cnt ";
						$sql .= " 		FROM friend ";
						$sql .= " 		WHERE friend_user_id = " . $to_user_id;
						$sql .= " 		AND tag_status = 1 ";
						$sql .= " 		GROUP BY tag_1 ";
						$sql .= " 		UNION ";
						$sql .= " 		SELECT tag_2 as tag , count(friend_id) as cnt ";
						$sql .= " 		FROM friend ";
						$sql .= " 		WHERE friend_user_id = " . $to_user_id;
						$sql .= " 		AND tag_status = 1 ";
						$sql .= " 		GROUP BY tag_2 ";
						$sql .= " 		) as tagging ";
						$sql .= " 	GROUP BY tag ";
						$sql .= " 	) as max_tag ";
						$sql .= " JOIN tag ON (tag.tag_id = max_tag.tag) ";
						$sql .= " GROUP BY tag.name ";
						
						//echo $sql;
						
						$rs=mysql_query($sql);
						
						if (mysql_num_rows($rs)>0){ 
						
							$row = mysql_fetch_assoc($rs);
							
							$tag_1 = $row["tag"];
							
							$row = mysql_fetch_assoc($rs);
							
							$tag_2 = $row["tag"];
						}else{
							$tag_1 = 0;
							$tag_2 = 0;
						}
						
						//Update frien'd profile
						$sql = " UPDATE fb_user SET ";
						$sql .= " tag_1 = " . $tag_1 . ",";
						$sql .= " tag_2 = " . $tag_2 . ",";
						$sql .= " completed_requests = completed_requests + 1, ";
						$sql .= " modified_date = '" . $today . "' ";
						$sql .= " WHERE user_id = " . $to_user_id; 
						
						mysql_query($sql);						
						
					}
					
				}else{
				
					$response= new Response(0,"Access denied, invalid tag reference");
					
				}
			}
			else
			{
				
				$response= new Response(0,"Access denied, invalid friend reference");
			}
					
		}
		else
		{
			
			$response= new response(0,"Access denied, invalid user reference");
				
		}
		   
		closeDBCon($conn);
		
	}
	   
	echo json_encode($response);
	   
	   
class Response{
	public $code;
	public $message;
	public $tag_1;
	public $tag_2;
	public $tag_1_name;
	public $tag_2_name;

	public function __construct($iCode, $iMessage ){
		$this->code = $iCode;
		$this->message = array();
		$this->message[] = $iMessage;
	}
}


?>