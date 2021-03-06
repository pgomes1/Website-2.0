<?php
//This is very roughly imported from the old server, and does not conform to the new coding standards. Beware!
require_once('includes/util.php');
load_config('config.txt');
my_quick_con($config);
if(!isset($_SESSION)) session_start();
if(!isset($loginUpdate)) require_once('includes/loginUpdate.php');
$settings = get_settings();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><?php placeTabIcon(); ?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>UMBC HvZ</title>
<meta name="keywords" content="" />
<meta name="description" content="" />
<link href="/style.css" rel="stylesheet" type="text/css" media="all" />
<link href='https://fonts.googleapis.com/css?family=Oxygen' rel='stylesheet' type='text/css'/>
<?php htmlHeader(); ?>
</head>
<body>
<div id="wrapper">
	<?php pageHeader(); ?>
	<div id="page" class="container">
		<div id="content">
		
		<script type="text/javascript">
		/*var pass = prompt("Password:");
		if(pass != "OZ") {
			alert("Password incorrect.  Redirecting to umbchvz.com");
			window.location="http://umbchvz.com";
		}*/
		</script>
			
			<?php
			
			//VOTING CONSTANTS
			
			//Default value used for dummy votes to create a candidate
			$defaultUID = $settings['nullUID']; 
			
			//Number of votes needed for a "dummy" vote to be created for the option and make said option a candidate
			//If a write-in doesn't reach this threshold, it will not be counted by the vote counter
			$writeInThreshold = $settings['writeInThreshold']; 
			
			//Number of votes required to show voting results to sub-admin accounts
			$showVotesThreshold = $settings['showVotesThreshold']; 
			
			echo "<h1 style='text-align:center; margin-top: 10px;'><b>Meet the Candidates!</b></h1><br/>";
			//echo "<h1 style='text-align:center; margin-top: 10px;'><b>Constitution Voting</b></h1><br/>";
			
			//The officer_positions table includes "Web Committee" because what the table actually represents is the set of positions to be voted on,
			//rather than literal officer positions. Non-officers were never supposed to have elections, until we replaced webmaster with web committee,
			//and I didn't feel like renaming a table already in the database when we did that because it's not that big of a deal
			
			//If you have a problem with that then suck it... or you could change it if you feel like it and I'm gone... not my problem anymore!
			//E
			//-Kyle J Mosier
			$qury = mysql_query("SELECT position FROM officer_positions ORDER BY id ASC;");
			while($ret = mysql_fetch_assoc($qury)) {
				$curPos = $ret['position'];
				
				echo "<h4><b>$curPos:</b></h4>";
				
				//election candidates represents what the issues you're actually voting on. This syetem could be used to do polls the same way it works for candidates
				//However, it is NOT dependent on the list of voteable options. The names of things are misleading...
				$qury2 = mysql_query("SELECT * FROM election_candidates WHERE position='$curPos';");
				if(mysql_num_rows($qury2) > 0) {
					while($ret2 = mysql_fetch_assoc($qury2)) {
						$curName = $ret2['name'];
						$curBio = $ret2['bio'];
						echo "<br><div style=\"width:400px;\"><b>$curName</b> - $curBio</div><br>";
						//Actually display bios here
					}
				} else {
					echo "<br>None<br>";
				}
				
				echo "<br>";
			}
		
			if (!isLoggedIn()){
				echo "<h4 style=\"text-align: center\">You must be signed in to vote!</h4>";
			}else{
				$uid = $_SESSION['uid'];
				$canVote = canVote($uid); //look for canVote() in util.php if voting criteria changes
				//$canVote = true;
				if(!$canVote){
					//It's possible they should be able to, and either they weren't *signed in* to 5 meetings, 
					//or attendance records told y'all to go fuck yourselves, again (I'm sorry)
					echo "You are not eligible to vote.  Eligibility to vote is determined by having been signed in to at least five"
					." meetings during the current or previous semester.  If you think this is in error, please contact an admin.";
				}else{
					//load these three arrays
					$curVote = array();
					$positions = array();
					$candidates = array();
					
					//Prepare this array for displaying actual positions to vote ON
					$qury = mysql_query("SELECT position FROM election_votes GROUP BY position ORDER BY position ASC;");
					while($ret = mysql_fetch_assoc($qury)){
						$curVote[$ret['position']] = "";
						$positions[] = $ret['position'];
					}
					
					//Prepare this array for displaying actual "candidates" to vote FOR
					$qury = mysql_query("SELECT position, voteFor AS name FROM election_votes GROUP BY position, voteFor;");
					//$qury = mysql_query("SELECT position FROM election_candidates GROUP BY position, name ORDER BY RAND();");
					while($ret = mysql_fetch_assoc($qury)){
						if(!array_key_exists($ret['position'], $candidates)) $candidates[$ret['position']] = array();
						$candidates[$ret['position']][] = $ret['name'];
					}
					
					
					//load any existing vote data
					$ret = mysql_oneline("SELECT COUNT(*) cnt FROM election_votes WHERE uid='$uid';");
					if($ret['cnt']!=0){
						$qury = mysql_query("SELECT position, voteFor FROM election_votes WHERE uid='$uid';");
						while($ret = mysql_fetch_assoc($qury)){
							$curVote[$ret['position']] = $ret['voteFor'];
						}
					}
			    		
					//save any vote submission data, if it exists
					if(array_key_exists("submit", $_POST)){
						foreach($positions as $curPos){
							$postPos = preg_replace("/ /","_",$curPos);
							if(array_key_exists($postPos, $_POST) || $_POST[$postPos."-other"]!=""){
								$newVote = mysql_real_escape_string(($_POST[$postPos."-other"]!=""?$_POST[$postPos."-other"]:$_POST[$postPos]));
								if($curVote[$curPos]!=""){
									mysql_query("UPDATE election_votes SET voteFor='$newVote' WHERE uid='$uid' AND position='$curPos';");
								}else{
									mysql_query("INSERT INTO election_votes (uid, position, voteFor) VALUES ('$uid','$curPos','$newVote');");
								}
								$curVote[$curPos] = $newVote;
							}else{
								mysql_query("DELETE FROM election_votes WHERE uid='$uid' AND position='$curPos';");
							}
							//Count number of votes for this option.
							$numVotes = mysql_oneline("SELECT COUNT(*) cnt FROM election_votes WHERE position = '$curPos' AND voteFor = '$newVote';");
							$numVotes = $numVotes['cnt'];
							if($numVotes >= $writeInThreshold) { 
								//Create a dummy vote for this option, thus making it look like a candidate to the counter,
								//but it still needs to show as a write-in option, so instead of a blank uid, initialize it to
								//a uid value that no user account will have, but also make sure the dummy vote is not a duplicate
								mysql_query("DELETE FROM election_votes WHERE uid = '$defaultUID' AND position = '$curPos' AND voteFor = '$newVote';");
								mysql_query("INSERT INTO election_votes (uid, position, voteFor) VALUES ('$defaultUID','$curPos','$newVote');");
							}
						}
						echo "<h4 style=\"text-align: center\">Vote saved! </h4><br><br>";
					}
					
					//reload candidates
					$candidates = array();
					$qury = mysql_query("SELECT position, voteFor AS name FROM election_votes GROUP BY position, voteFor;");
					while($ret = mysql_fetch_assoc($qury)){
						if(!array_key_exists($ret['position'], $candidates)) $candidates[$ret['position']] = array();
						$candidates[$ret['position']][] = $ret['name'];
					}
					
					//present voting options
					echo '<form method="post" action="">';
					foreach($positions as $curPos){
						echo "<b>$curPos</b><br>";
						$test=false;
						foreach($candidates[$curPos] as $curCan){
							echo '<label for="'."$curPos&$curCan".'"><input type="radio" id="'."$curPos&$curCan".'" name="'.$curPos.'" value="'.$curCan.'"'.($curVote[$curPos]==$curCan&&($test=true)?' checked="checked"':"").'>'."$curCan</label><br/>";
						}
						
						if(substr($curPos, 0, 13) != "Web Committee") {
							//Used to allow write-in options
							//If you're "position" starts with "Web Committee", then write-ins are blocked, since that's simply a yes/no vote
							echo "<br>Other: <input name=\"{$curPos}-other\"".($test?"":" value=\"{$curVote[$curPos]}\"")."><br><br>";
							echo "<br><br>";
						}else{
							echo "<br/><br/>";
						}
					}
					
					if($settings['lockVoting'] == "unlock") {
						echo '<input type="submit" name="submit" value="Submit vote"></form>';
					} else {
						echo "<h2 style=\"text-align:center\">Voting is currently closed.</h2>";
					}
					
					//If user is an officer/web committee, show a complete, anonymized count of votes
					//I hope you like this vote counter, because I stayed up late during Thanksgiving vacation in Florida to make it work
					//With this counter, web committee does NOT need to actually look at who voted for who to do their job
					//This counter also makes it harder to fix an election, since multiple have continuous access to its live results
					//YOU SHOULD BE SUSPICIOUS OF ANYONE WHO WANTS/TRIES TO REMOVE THIS FUNCTIONALITY FOR ANY REASON
					//IF ANYONE TRIES TO KILL THIS FUNCTIONALITY, LET ALL (OTHER) OFFICERS, (OTHER) WEB COMMITTE MEMBERS, AND HICCUP KNOW RIGHT AWAY 
					//ALSO MAYBE TELL ME (KYLE) SO I CAN COME BACK AND YELL AT SOMEONE
					if($_SESSION['isAdmin'] >= 2) {
						echo "<br><br><b>Voting results:</b><br><br>";
						$blankVotes = array();
						$qury = mysql_query("SELECT position, voteFor AS name FROM election_votes WHERE uid = '' OR uid = '$defaultUID';");
						while($ret = mysql_fetch_assoc($qury)){
							if(!array_key_exists($ret['position'], $blankVotes)) $blankVotes[$ret['position']] = array();
							$blankVotes[$ret['position']][] = $ret['name'];
						}
						foreach($positions as $curPos){
							echo "<u>$curPos</u><br>";
							foreach($blankVotes[$curPos] as $curCan) {
								$numVotes = mysql_oneline("SELECT COUNT(*) cnt FROM election_votes WHERE position = '$curPos' AND voteFor = '$curCan';");
								$numVotes = $numVotes['cnt'] - 1; //Don't count the dummy as a vote
								if($numVotes != 1) {
									echo "'".$curCan."' has ".$numVotes." votes";
								}else {
									echo "'".$curCan."' has 1 vote";
								}
								echo "<br>";
							}
							echo "<br><br>";
						}
						echo "<br><br>";
					}
					
					//Show count of votes, but hide the stats. Only show which voting options are "winning"
					//Make sure you keep the else below, or you'll be printing redundant information
					else if($_SESSION['isAdmin'] >= 1) {
						echo "<br><br>Your admin level is not high enough to see the exact vote counts of this election"; //Lol git gud
						echo "<br><br><b>Voting results:</b><br><br>";
						$blankVotes = array();
						$qury = mysql_query("SELECT position, voteFor AS name FROM election_votes WHERE uid = '' OR uid = '$defaultUID';");
						while($ret = mysql_fetch_assoc($qury)){
							if(!array_key_exists($ret['position'], $blankVotes)) $blankVotes[$ret['position']] = array();
							$blankVotes[$ret['position']][] = $ret['name'];
						}
						foreach($positions as $curPos){
							echo "<u>$curPos</u><br>";
							$leadingCan = "";
							$highestNumVotes = 0;
							$hasTie = false;
							foreach($blankVotes[$curPos] as $curCan) {
								$numVotes = mysql_oneline("SELECT COUNT(*) cnt FROM election_votes WHERE position = '$curPos' AND voteFor = '$curCan';");
								$numVotes = $numVotes['cnt'] - 1; //Don't count the dummy as a vote, but count everything else
								if($numVotes > $highestNumVotes) {
									$highestNumVotes = $numVotes;
									$leadingCan = "'".$curCan."'";
								}elseif ($numVotes == $highestNumVotes and $leadingCan != "") {
									$leadingCan = $leadingCan." and '".$curCan."'";
									$hasTie = true;
								}
								echo "<br>";
							}
							if(highestNumVotes <= $showVotesThreshold) {
								echo "Not enough votes have been cast to display any results"; //Lol git gud
							} elseif ($hasTie == false) {
								echo $curCan." has the most votes";
							} else {
								echo "The candidates ".$curCan." are tied for having the most votes"; //Uh oh. Hopefully the election doesn't end this way...
							} 
						}
						echo "<br><br>";
					}
				}
			}
			?>
		</div>
		<div id="sidebar">
		<?php printSidebar(); ?>
		</div>
	<div class="clearfix">&nbsp;</div>
	</div>
	<div id="footer" class="container">
		<?php printFooter(); ?>
	</div>
</div>
</body>
</html>
