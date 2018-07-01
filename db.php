<?php	
function query($query){
	$connect_result = mysql_query($query);
	if (!$connect_result){
		echo "Bad query " . $query . "<br/>\r\n" .mysql_error()."<br/>";
		mysql_query("rollback");
		echo mysql_error();
	}
	return $connect_result;
}
function getRow($query){
	$res=query($query);
	if(mysql_num_rows($res)<1){
		//trigger_error("No Rows Returned in ".$query,2);
		return null;
	}
	return mysql_fetch_row($res);
}
function getField($query){
	$res=query($query);
	if(mysql_num_rows($res)<1){
		//trigger_error("No Rows Returned in ".$query,2);
		return null;
	}
	$res=mysql_fetch_array($res);
	return $res[0];
}
function escape($str){
	global $conn;
	if(is_array($str))
		foreach($str as $key=>$val)
			$str[$key]=escape($val);
	else
		$str=mysql_real_escape_string($str);
	return $str;
}
function htmlEscape($str){
	if(is_array($str))
		foreach($str as $key=>$val)
			$str[$key]=htmlEscape($val);
	else
		$str=htmlspecialchars($str,ENT_QUOTES);
	return $str;
}
mysql_connect("localhost","screenshare","4nUGhhfYwEhcCFmb") and mysql_select_db("screenshare") or die("Error in db connect file, ".mysql_error());
?>
