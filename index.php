<?php
	include "db.php";
	$get=escape($_GET);
	$post=escape($_POST);
	if($_POST){
		query("insert into drawing(token,color,`data`,`time`) values('$get[token]','$post[color]','$post[data]',now())");
		exit;
	}
	else if($_GET["time"]){
		for($i=0;$i<6;$i++){
			$get=escape($_GET);
			$res=query("select id,color,data from drawing where token='$_GET[token]' and id>'$get[id]' order by id asc");
			if(mysql_num_rows($res)==0){
				usleep(1000*500);//half sec
				continue;
			}
			$data=array();
			$id=0;
			
			while($row=mysql_fetch_assoc($res)){
				$data[]=array("color"=>"$row[color]","coords"=>"$row[data]");
				$id=$row['id'];
			}
			$data=json_encode($data);
			echo "{data:$data,id:'$id',msg:'$msg'}";
			exit;
			
		}
		echo "{data:null,msg:'Nothing there'}";
		exit;
	}
	if(!$_GET["token"]){
		$lets="ABCDEFGHJKMNPQRSTWXYZ0123456789";
		$token="";
		for($i=0;$i<3;$i++){
			$token.=$lets[rand(0,strlen($lets)-1)];
		}
		
		header("Location: ?token=$token");
		query("Insert into drawing(color,token,`time`,data) values('Black','$token',now(),'0-0,0-0')");
		exit;
	}
	$dbtime=date("Y-m-d H:i:s",time()-60*15);
	query("delete from drawing where time<'$dbtime'");
	$res=query("select token,max(time) as t from drawing group by token order by t"); 
	echo "<div class='sessionlist'>active sessions...<br/>";
	if(mysql_num_rows($res)){
		while($row=mysql_fetch_assoc($res)){
			$itsme="";
			if($_GET['token']==$row['token'])$itsme=" (this is you )";
			echo "<a href='?token=$row[token]'>$row[token]</a>$itsme<br/>";
		}
		
	}
	else{
		echo "None<br/>";
	}
	echo "<a href='?ntime=".microtime(true)."'>get new session</a><br/></div>";
	

?>
<table border=1 class='colorselect'><tr><td><input type=button value=black /><input type=button value=red /><input type=button value=blue /><input type=button value=yellow /></td></tr></table>
<div>Click and drag your mouse in the gray area.  You can open another browser window to <a href='?token=<?=$_GET['token']?>'>Here</a> to see that it is being shared over the network. You can also select a different color above.</div> 
<style type='text/css'>
.colorselect{margin:auto;}
.colorselect input{display:block;}
.sessionlist{border:1px solid gray;float:left;}

#canvas{
	background-color:#CCC;
	width:700px;
	height:500px;
	margin:30px auto;
	border:3px solid pink;
	position:relative;
	user-select:none;
}
.pxl{
	width:2px;
	height:2px;
	position:absolute;
}
</style>
 <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script>
$(function(){
	var timer=null;//flush buffer when timer goes off
  	var flushinterval=1000;//flush buffer if it's been this long since last flush millisecs
	var flushcheckinterval=200;
	var maxbuffsize=1000;
	var buffer="";
	var lastflushtime=new Date().valueOf();
	var dragging=false;
	var canvas=$("#canvas")[0];
	var prevx=-1;
	var prevy=-1;
	var mycolor="Black";
	var lastid=0;
	var bufferempty=true;
	(function(){
		var buttons=$(".colorselect input");
		var cur=null;
		buttons.click(function(e){
			mycolor=this.value;
			if(cur)cur.disabled=false;
			this.disabled=true;
			cur=this;
		});
		buttons[0].click();
	})();
	
	window.onmousedown=function(e){dragging=true;}
	window.onmouseup=undrag;
	window.onmouseout=function(e){
		if(e.target==this)
			undrag(e);
	}
	window.ondrag=function(e){
		console.log("!!DRag start");
		return false;
	}
	function undrag(e){
		dragging=false;
		//buffer+="U~";
		prevx=-1;
		prevy=-1;
		flush();
		buffer="";
		console.log("undrag "+e.type);
	}
	canvas.onmousemove=function(e){
		if(!dragging)
			return; 
		bufferempty=false;
		var x=e.clientX-this.offsetLeft;
		var y=e.clientY-this.offsetTop;

		/* if the mouse is moving quickly, there won't be a onmousemove event every
		pixel, it will skip like from (1,1) to (4,1)
		*/
		if(prevx==-1 || prevy==-1){
		}
		else{
			draw(x,y,prevx,prevy,mycolor);
		}
		prevx=x;
		prevy=y;

		buffer+=x+"-"+y+"~";
		if(buffer.length >=maxbuffsize){
			console.log("buffer full size="+buffer.length);
			flush();
		}

	};
	var jaxcount=0;
	function flush(){
		//clear buffer but save as it is right now
		if(bufferempty)return;
		var tmp=buffer;
		bufferempty=true;
		if(prevx==-1&&prevy==-1){console.log("prevx and y are null");buffer="";}
		else buffer=prevx+"-"+prevy+"~";
		//but this clears the buffer
		function wait(){
			if(jaxcount>0){//a request is in the air, wait
				setTimeout(wait,300);
				console.log("request is in air");

			}
			else{
				//var tmp=buffer;
				//buffer="";
				lastflushtime=new Date().valueOf();
				jaxcount++;
				var st=Date.now();
				//setTimeout(function(){
					$.ajax({
						type:"POST",
						data:{color:mycolor,data:tmp},
						complete:function(response){
							//console.log(new Date().valueOf()+" flushed: "+tmp+",req took "+(Date.now()-st));
							jaxcount--;
						},
					});
				//},300);
			}
		}
		wait();
	}
	setInterval(function(){
		var curtime=new Date().valueOf();
		if(curtime-flushinterval > lastflushtime && !bufferempty){
			flush();
		}
	},flushcheckinterval);
	var rprevx=-1;
	var rprevy=-1;
	function fetch(){
		$.ajax({
			url:"?token=<?=$_GET['token']?>&time="+new Date().valueOf()+"&id="+lastid,
			complete:function(response){
				try{
					var text=response.responseText;
					var obj=eval("("+text+")");
					//console.log(obj.msg);
					if(obj.data){
						//var coords=obj.data.split("~");
						
						
						lastid=obj.id;
						//coords.pop();
						for(var j=0;j<obj.data.length;j++){
							var color=obj.data[j].color;
							var coords=obj.data[j].coords.split("~");
							//console.log(coords);
							var i=0;//do we start at zero
			/*				if( rprevx==-1 || rprevy==-1 ){
								var xy=coords[0].split("-");
								rprevx=xy[0];//local!!
								rprevy=xy[1];
								i++;
							}
			*/				
							var xy=coords[0].split("-");
							rprevx=xy[0];
							rprevy=xy[1];
							for(i=i;i<coords.length;i++){
								
								var xy=coords[i].split("-");
								
				/*				if(coords[i]=="U~"){
									rprevx=-1;
									rprevy=-1;
									console.log("found u");
									continue;
								}
			*/					
								//var match=coords[i].match(/(\w+):(\d+)-(\d+)/);
								//var x=match[2];
								//var y=match[3];
								//var color=match[1];
								if(rprevx==-1 || rprevy==-1){//mouse up
									//next is set, but prev is not, don't draw
								}
								else{
									draw(xy[0],xy[1],rprevx,rprevy,color);
								}
								rprevx=xy[0];
								rprevy=xy[1];
							}
						}
					}
					setTimeout(fetch,100);
				}
				catch(exp){
					console.log("error "+exp);
					//alert(exp);
				}
			}
		});
	}
	fetch();
	function draw(x,y,prevx,prevy,color){
//		var pxl=document.createElement("DIV");
//		$(pxl).css({top:y+"px",left:x+"px","background-color":color});
//		pxl.className="pxl";
//		canvas.appendChild(pxl);
//		return;
			var distx=x-prevx;
			var disty=y-prevy;
			if(Math.abs(distx)>Math.abs(disty)){
				var numtoadd=Math.abs(distx);
			}
			else
				var numtoadd=Math.abs(disty);
			//numtoadd=Math.floor(numtoadd/2);
			var unitx=distx/numtoadd;
			var unity=disty/numtoadd;

			for(var i=1;i<=numtoadd;i++){
				var pxl=document.createElement("DIV");
				var newy=Number(prevy)+unity*i;
				var newx=Number(prevx)+unitx*i;
				$(pxl).css({top:newy+"px",left:newx+"px","background-color":color});
				pxl.className="pxl";
				canvas.appendChild(pxl);
			}
	}
});
</script>
				<div id='canvas'>
				</div>
