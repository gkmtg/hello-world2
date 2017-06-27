<!DOCTYPE html>
<html>
<head>
<?php
/**
* * * * * * *  * * * * * * * *  * * * * * * * *  * * * * * * * *  * * * * * * * *  * * * * * * * *  * * * * * * * *  * 
 This uses the new layout for Matches (3 spots for the 3 game results, 0 if p0 won, 1 if p1 won; space / nothing if no game)
 **/
	include '/usr/home/gkmtg/public_html/gkmtg.pro/headinclude.php';  // has utility
	require_once( ABSPATH . '/tournament/tournamentutility.php');

	define('iBoxWidth',   200); // see graybox / winbox
	define('iPadWidth',   10); // pixels between
	define('iLabelWidth', 35); // pixels for "W2" or whatever
	define('iBoxHeight',  50); // see graybox
	define('iPadHeight',  10);
	define('iTextHeight', 25); // height of the placement text for losers bracket

if(isset($_POST) && isset($_POST['mID'])) { // a result is submitted
	// calculate winner / loser values
	$iaGames[0] = $_POST['g1'] == 100 ? 1 : 0;
	$iaGames[1] = $_POST['g2'] == 100 ? 1 : 0;
	$iaGames[2] = $iaGames[0] == $iaGames[1] ? "" : ($_POST['g3'] == 100 ? 1 : 0);

	$iaGameWins = array_count_values($iaGames); 
	$sWinner = $_POST['p' . array_search(max($iaGameWins), $iaGameWins) . 'dci'];
	$sLoser = $_POST['p' . (1-array_search(max($iaGameWins), $iaGameWins)) . 'dci'];

	// edit Seats
	$sqlUpdateTables = "UPDATE `Seats` SET mID=NULL WHERE mID=? AND tID=?";
	$resUpdateTables = fresQuery([$sqlUpdateTables, 'ss', [$_POST['mID'], $_POST['tournamentID']]]);

	// update this match
	$sqlUpdateMatch = "UPDATE `Matches2` SET Result=concat(?,?,?) WHERE mID=? AND tID=?";
	$resUpdateMatch = fresQuery([$sqlUpdateMatch, 'sssss', [$iaGames[0], $iaGames[1], $iaGames[2], $_POST['mID'], $_POST['tournamentID']]]);

	if(($_POST['mID'] == GRAND_FINALS) && ($sWinner == $_POST['p1dci'])) { // if there's a bracket reset
		// create grand finals 2
		$sqlInsertGF2 = "INSERT INTO `Matches2` (`mID`, `tID`, `Player0`, `Player1`) VALUES (?,?,?,?)";
		$resInsertGF2 = fresQuery([$sqlInsertGF2, 'ssss', [GRAND_FINALS2, $_POST['tournamentID'], $_POST['p0dci'], $_POST['p1dci']]]);
	}
	else {
		$sWinnerMatch = fsWinnersNextMatch($_POST['mID']);
		$sLoserMatch  = fsLosersNextMatch($_POST['mID']);

		fvAddPlayerToMatch($sWinnerMatch, $_POST['tournamentID'], $sWinner, $_POST['mID']);
		fvAddPlayerToMatch($sLoserMatch,  $_POST['tournamentID'], $sLoser, $_POST['mID']);
	}
	fvProgressBracket($_POST['mID'], $_POST['tournamentID']);
}

if(isset($_POST['tournamentID'])) {
	$sTID = $_POST['tournamentID'];
}
else if(isset($_GET['tournamentID']) && $_GET['tournamentID']!="" && $_GET['tournamentID']!="index.php") {
	fvCom($_GET['tournamentID']);
	$sTID = $_GET['tournamentID'];
}
if(isset($sTID)) {
	$sqlAllMatches = "SELECT `mID`, `tID`, `Player0`, `Player1`, `Result`, A.`FirstName` AS P0First, A.`LastName` AS P0Last, B.`FirstName` AS P1First, B.`LastName` AS P1Last, TableNumber FROM `Matches2` LEFT JOIN `Players` AS A on `Player0`=A.DCI LEFT JOIN `Players` AS B on `Player1`=B.DCI WHERE `tID`=? AND SUBSTRING(`mID`,1,1)=?";
	$stmtAllMatches = DB::cxn()->prepare($sqlAllMatches);

	$sL = "L";
	$stmtAllMatches->bind_param('ss', $sTID, $sL);
	$stmtAllMatches->execute();
	$resAllLoserMatches = $stmtAllMatches->get_result();

	$sW = "W";
	$stmtAllMatches->bind_param('ss', $sTID, $sW);
	$stmtAllMatches->execute();
	$resAllWinnerMatches = $stmtAllMatches->get_result();

	unset($sL);
	unset($sW);

	$sqlTourneySize = "SELECT `tName`, `Players` FROM `Tournaments` WHERE `tID`=?";
	$resTourneySize = fresQuery([$sqlTourneySize, "s", [$sTID]]); 
	$saTourneySize = $resTourneySize->fetch_assoc();
	$iTourneySize = substr_count($saTourneySize['Players'], ";");
	print "<title>" . $saTourneySize['tName'] . "</title>";
}
else {
	print "<title>Bracket</title>";
}

/**
 * These two make their respective bracket images
 * note, the movement isn't quite right as of 2017-05-01:
 * they both move an extra round over, but you can't tell in losers because the first round is all byes & not shown
 **/
function fsMakeWinnerSVG($presWinnerMatches, $piTotalPlayers) {
	$sBracket = "";
	$iMaxWinnerHeight = fiCalculateTopOffset(GRAND_FINALS, $piTotalPlayers);
	$sLeftmostUnfinished = GRAND_FINALS;
	while($saWinnerMatch = $presWinnerMatches->fetch_assoc()) {
		if(fiCalculateTopOffset($saWinnerMatch['mID'], $piTotalPlayers) > $iMaxWinnerHeight) $iMaxWinnerHeight = fiCalculateTopOffset($saWinnerMatch['mID'], $piTotalPlayers);
		if(fiLastFive($saWinnerMatch['mID']) > fiLastFive($sLeftmostUnfinished) && (/*$saWinnerMatch['Player0'] === null || $saWinnerMatch['Player1'] === null || */$saWinnerMatch['Result']===null)) $sLeftmostUnfinished = $saWinnerMatch['mID'];
		$sBracket .= fsMakeMatchSVG($saWinnerMatch, $piTotalPlayers);
	}
	
	$iMaxWidth = 265*4; // four columns ... maybe shrink based on window size

	//$iMaxHeight = fiCalculateTopOffset($sMaxWinnerMID, $piTotalPlayers)+iBoxHeight+iPadHeight;
	$iMaxWinnerHeight += iBoxHeight+iPadHeight;
	$iHalf = $iMaxWinnerHeight/2;
	$iThird = $iMaxWinnerHeight/3;
	// change minX according to your match / most recent match finished
	$sStartStuff  = "<svg id=\"winnerSVG\" ";
	$sStartStuff .= "data-maxwidth=\"" . fiCalculateLeftOffset(GRAND_FINALS, $piTotalPlayers) . "\" ";
	$sStartStuff .= "data-startoffset=\"" . fiCalculateLeftOffset($sLeftmostUnfinished, $piTotalPlayers) . "\" ";
	$sStartStuff .= "viewBox=\"0 0 $iMaxWidth $iMaxWinnerHeight\"><g id=\"gW\" transform=\"translate(0,0)\">" . $sBracket . "</g>";

	$sStartStuff .= "<g class=\"gButton\" id=\"gWL\" onclick=\"updateView('winnerSVG', 'left');\" >";
	$sStartStuff .= "<rect id=\"gWLRectangle\" class=\"rectButt\" x=\"0\" y=\"0\" width=\"25\" height=\"100%\" />";
	$sStartStuff .= "<polygon id=\"gWLTriangle\" class=\"triang\" points=\"5,".$iHalf." 20,".$iThird." 20,".($iThird*2)."\" />";
	$sStartStuff .= "</g>";

	$sStartStuff .= "<g class=\"gButton\" id=\"gWR\" onclick=\"updateView('winnerSVG', 'right');\" >";
	$sStartStuff .= "<rect id=\"gWRRectangle\" class=\"rectButt\" x=\"".($iMaxWidth-25)."\" y=\"0\" width=\"25\" height=\"100%\" />";
	$sStartStuff .= "<polygon id=\"gWRTriangle\" class=\"triang\" points=\"".($iMaxWidth-5).",".$iHalf." ".($iMaxWidth-20).",".$iThird." ".($iMaxWidth-20).",".($iThird*2)."\" />";
	$sStartStuff .= "</g>";

	$sStartStuff .= "</svg>";

	$sTBR  = "<h3>&nbsp;Winners Bracket&nbsp;</h3>";
	$sTBR .= $sStartStuff;
	return "<div id='wslipeSVG'>" . $sTBR . "</div>";
}

function fsMakeLoserSVG($presLoserMatches, $piTotalPlayers) {
	$sBracket = "";
	$iMaxLoserHeight = fiCalculateTopOffset(LOSERS_FINALS, $piTotalPlayers);
	$sLeftmostUnfinished = LOSERS_FINALS;
	$iMaxLoserMID = 0;
	$bContainsGF2 = false;
	while($saLoserMatch = $presLoserMatches->fetch_assoc()) {
		if(fiCalculateTopOffset($saLoserMatch['mID'], $piTotalPlayers) >= $iMaxLoserHeight) {
			$iMaxLoserHeight = fiCalculateTopOffset($saLoserMatch['mID'], $piTotalPlayers);
			$iMaxLoserMID = max($iMaxLoserMID, fiLastFive($saLoserMatch['mID']));
		}
		if($saLoserMatch['mID'] > $sLeftmostUnfinished && ($saLoserMatch['Player0'] === null || $saLoserMatch['Player1'] === null || $saLoserMatch['Result']===null)) $sLeftmostUnfinished = $saLoserMatch['mID'];
		if($saLoserMatch['mID'] == GRAND_FINALS2) $bContainsGF2=true;
		
		$sBracket .= fsMakeMatchSVG($saLoserMatch, $piTotalPlayers);

	}
	
	$iMaxWidth = 265*4; // four columns
	//fv Com($iMaxLoserHeight . " " . $iMaxLoserMID . " " . fiCalculateTopOffset($iMaxLoserMID, $piTotalPlayers));
	$iMaxLoserHeight += iBoxHeight+iPadHeight;
	
	$sBracket .= fsMakePlacementSVG($iMaxLoserMID, $iMaxLoserHeight, $piTotalPlayers, $bContainsGF2);

	$iMaxLoserHeight += iTextHeight;

	$iHalf = $iMaxLoserHeight/2;
	$iThird = $iMaxLoserHeight/3;

	// change translate according to most recent match finished (TODO: relative to your next match?)
	$sStartStuff  = "<svg id=\"loserSVG\" ";
	$sStartStuff .= "data-maxwidth=\"" . fiCalculateLeftOffset($bContainsGF2?GRAND_FINALS2:LOSERS_FINALS, $piTotalPlayers) . "\" ";
	$sStartStuff .= "data-startoffset=\"" . fiCalculateLeftOffset($sLeftmostUnfinished, $piTotalPlayers) . "\" ";
	$sStartStuff .= "viewBox=\"0 0 $iMaxWidth $iMaxLoserHeight\"><g id=\"gL\" transform=\"translate(0,0)\">" . $sBracket . "</g>";

	$sStartStuff .= "<g class=\"gButton\" id=\"gLL\" onclick=\"updateView('loserSVG', 'left');\" >";
	$sStartStuff .= "<rect id=\"gLLRectangle\" class=\"rectButt\" x=\"0\" y=\"0\" width=\"25\" height=\"100%\" />";
	$sStartStuff .= "<polygon id=\"gLLTriangle\" class=\"triang\" points=\"5,".$iHalf." 20,".$iThird." 20,".($iThird*2)."\" />";
	$sStartStuff .= "</g>";

	$sStartStuff .= "<g class=\"gButton\" id=\"gLR\" onclick=\"updateView('loserSVG', 'right');\" >";
	$sStartStuff .= "<rect id=\"gLRRectangle\" class=\"rectButt\" x=\"".($iMaxWidth-25)."\" y=\"0\" width=\"25\" height=\"100%\" />";
	$sStartStuff .= "<polygon id=\"gLRTriangle\" class=\"triang\" points=\"".($iMaxWidth-5).",".$iHalf." ".($iMaxWidth-20).",".$iThird." ".($iMaxWidth-20).",".($iThird*2)."\" />";
	$sStartStuff .= "</g>";

	$sStartStuff .= "</svg>";

	$sTBR  = "<h3>&nbsp;Losers Bracket&nbsp;</h3>";
	$sTBR .= $sStartStuff;
	return "<div id='lslipeSVG'>" . $sTBR . "</div>";
}

function fsMakeMatchSVG($psaTourneyMatch, $piTourneySize) {
	$iPowerOfTwo = fiPowOfTwo($piTourneySize);
	$sMID = $psaTourneyMatch['mID'];

	$iLeft = fiCalculateLeftOffset($sMID, $iPowerOfTwo); // the starting point of gray box, pixels from the left edge
	$iTop  = fiCalculateTopOffset($sMID,  $iPowerOfTwo); // the starting point of gray box, pixels from the top edge

	// gray box
	$sTBR = "<rect class=\"graybox\" x=\"". $iLeft . "\" y=\"" . $iTop . "\" />";

	// match number
	$sTBR .= "<text class=\"matchid\">";
	$sTBR .= "<tspan x=\"" . ($iLeft-5) . "\" y=\"" . ($psaTourneyMatch['TableNumber'] == null?($iTop+30):($iTop+18)) ."\">";
	if($sMID == GRAND_FINALS2) {
		$sTBR .= "GF2";
	}
	else if($sMID==GRAND_FINALS) {
		$sTBR .= "GF";
	}
	else if($sMID==WINNERS_FINALS) {
		$sTBR .= "WF";
	}
	else if($sMID==LOSERS_FINALS) {
		$sTBR .= "LF";
	}
	else {
		$sTBR .= substr($sMID,0,1) . fiLastFive($sMID);
	}
	$sTBR .= "</tspan>";

	if($psaTourneyMatch['TableNumber'] != null) {
		$sTBR .= "<tspan x=\"" . ($iLeft-5) . "\" y=\"" . ($iTop+43) . "\">t#" . $psaTourneyMatch['TableNumber'] . "</tspan>";
	}
	$sTBR .= "</text>";

	// winner highlight
	if($psaTourneyMatch['Result'] != null) {
		$iP0Wins = substr_count($psaTourneyMatch['Result'], '0');
		$iP1Wins = substr_count($psaTourneyMatch['Result'], '1');
		
		$iWinnerIndex = $iP0Wins == 2 ? 0 : 1;

		$sTBR .= "<rect class=\"winbox\" x=\"" . $iLeft . "\" y=\"" . ($iTop+25*$iWinnerIndex) . "\" />";
	}
	
	// player names
	$sTBR .= "<text>";
	$sTBR .= "<tspan x=\"" . ($iLeft+6) . "\" y=\"" . ($iTop+18) . "\"";

	$bP0Undefined = $psaTourneyMatch['P0First'] == null || $psaTourneyMatch['P0First'] === "";
	$bP1Undefined = $psaTourneyMatch['P1First'] == null || $psaTourneyMatch['P1First'] === "";
	if($bP0Undefined) {
		$sTBR .= "class=\"nowinner\">";
		if(fiLastFive($sMID) >= $iPowerOfTwo*$iPowerOfTwo/8) { // winner of bye matches (losers of round one get a bye in winners bracket - let's find a way to get rid of it!)
			// default:
			// Winner of "L" . (int) (fiLastFive($sMID)/2)
			// that's asking for the bye rounds
			// so, we need Loser of what-would-go-into-the-bye-round
			$sqlSelectLoserOfWinnersBracket = "SELECT mID FROM `Matches2` WHERE tID = ? AND mID LIKE ?";
			$resSelectLoserOfWinnersBracket = fresQuery([$sqlSelectLoserOfWinnersBracket, 'ss', [$psaTourneyMatch['tID'], "L%". fsPad(1+fiLastFive($sMID)*2, PAD_HALF)]]);
			$saLoserofWinnersBracket = $resSelectLoserOfWinnersBracket->fetch_assoc();
			$sTBR .= "Loser of W" . (int) substr($saLoserofWinnersBracket['mID'],1,PAD_HALF);
		}
		else if ($sMID==GRAND_FINALS) {
			$sTBR .= "Winner of WF";
		}
		else if(strpos($sMID, fsPad(0,PAD_HALF))>-1) { // winner of two (W00000real#, and some losers are L00000real#)
			$sTBR .= "Winner of " . substr($sMID,0,1) . (fiLastFive($sMID)*2);
		}
		else if($sMID==LOSERS_FINALS) {
			$sTBR .= "Loser of WF";
		}
		else { // loser / winner matchup (W0000x0000y)
			$sTBR .= "Loser of W" . (int)(substr($sMID,1,PAD_HALF));
		}
		$sTBR .= "</tspan>";// "winner of X / loser of X"
	}
	else {
		$sTBR .= ">" . $psaTourneyMatch['P0First'] . " " . $psaTourneyMatch['P0Last'] . "</tspan>";
	}

	$sTBR .= "<tspan x=\"" . ($iLeft+6) . "\" y=\"" . ($iTop+43) . "\"";
	if($bP1Undefined) {
		$sTBR .= "class=\"nowinner\">";
		if(fiLastFive($sMID) >= $iPowerOfTwo*$iPowerOfTwo/8) {
			// default:
			// Winner of "L" . (int) (fiLastFive($sMID)/2)+1
			// that's asking for the bye rounds
			// so, we need Loser of what-would-go-into-the-bye-round
				$sqlSelectLoserOfWinnersBracket = "SELECT mID FROM `Matches2` WHERE tID = ? AND mID LIKE ?";
				$resSelectLoserOfWinnersBracket = fresQuery([$sqlSelectLoserOfWinnersBracket, 'ss', [$psaTourneyMatch['tID'], "L%". fsPad(fiLastFive($sMID)*2, PAD_HALF)]]);
				$saLoserofWinnersBracket = $resSelectLoserOfWinnersBracket->fetch_assoc();
				$sTBR .= "Loser of W" . (int) substr($saLoserofWinnersBracket['mID'],1,PAD_HALF);
		}
		else if ($sMID==GRAND_FINALS) {
			$sTBR .= "Winner of LF";
		}
		else if( strpos($sMID, fsPad(0,PAD_HALF))>-1 ) { // winner of two
			$sTBR .= "Winner of " . substr($sMID,0,1) . (fiLastFive($sMID)*2+1);
		}
		else { // loser, winner
			$sTBR .= "Winner of " . substr($sMID,0,1) . (fiLastFive($sMID)*2);
		}

		$sTBR .= "</tspan>"; // "winner of X / loser of X"
	}
	else {
		$sTBR .= ">" . $psaTourneyMatch['P1First'] . " " . $psaTourneyMatch['P1Last'] . "</tspan>";
	}
	$sTBR .= "</text>";

	// results
	if($psaTourneyMatch['Result'] != null) {
		$sTBR .= "<text class=\"result\">";
		$sTBR .= "<tspan x=\"" . ($iLeft+183) . "\" y=\"" . ($iTop+18) . "\">" . substr_count($psaTourneyMatch['Result'], "0") . "</tspan>";
		$sTBR .= "<tspan x=\"" . ($iLeft+183) . "\" y=\"" . ($iTop+43) . "\">" . substr_count($psaTourneyMatch['Result'], "1") . "</tspan>";
		$sTBR .= "</text>";
	}
	

	//<!-- long horizontal line -->
	// make gray if both players aren't defined
	$sTBR .= "<line class=\"horzline";
	if($bP0Undefined || $bP1Undefined) {
		$sTBR .= " grayl"; // make it gray
	}
	if($psaTourneyMatch['Result'] != null) { // long (match result is defined)
		$sTBR .= "\" x1=\"" . $iLeft . "\" y1=\"" . ($iTop+25) . "\" x2=\"". ($iLeft+200) . "\" y2=\"" . ($iTop+25) . "\" />";
	}
	else { // short, match isn't complete
		$sTBR .= "\" x1=\"" . $iLeft . "\" y1=\"" . ($iTop+25) . "\" x2=\"". ($iLeft+180) . "\" y2=\"" . ($iTop+25) . "\" />";
		if($bP0Undefined || $bP1Undefined) { // match isn't even defined
			// no notepad
		}
		else {
		//	$sTBR .= "<a xlink:href=\"/\" target=\"_blank\">";
			$sTBR .= "<image alt=\"Report\" class=\"notepad\" onclick=\"fvMatchPopUp(";
			$sTBR .= "'" . $psaTourneyMatch['tID'] . "',";
			$sTBR .= "'" . $psaTourneyMatch['mID'] . "',";
			$sTBR .= "'" . $psaTourneyMatch['Player0'] . "',";
			$sTBR .= "'" . $psaTourneyMatch['P0First'] . "',";
			$sTBR .= "'" . $psaTourneyMatch['P0Last'] . "',";
			$sTBR .= "'" . $psaTourneyMatch['Player1'] . "',";
			$sTBR .= "'" . $psaTourneyMatch['P1First'] . "',";
			$sTBR .= "'" . $psaTourneyMatch['P1Last'] . "'";

			$sTBR .= ");\" x=\"".($iLeft+170)."\" y=\"" . ($iTop+13) . "\" height=\"25px\" width=\"25px\" xlink:href=\"/images/notepad.png\" />";
		//	$sTBR .="</a>";
		}
	}
	//	<!-- vertical line -->
	$sTBR .= "<line class=\"vertline";
	if($bP1Undefined || $bP1Undefined) $sTBR .= " grayl";
	$sTBR .= "\" x1=\"" . $iLeft . "\" y1=\"" . $iTop . "\" x2=\"" . $iLeft . "\" y2=\"" . ($iTop+50) . "\" />";

	if(!$bP0Undefined && !$bP1Undefined && ($psaTourneyMatch['Player0'] == BYE_DCI || $psaTourneyMatch['Player1'] == BYE_DCI)) {
		return "";
	}

	return $sTBR;
}

/**
 * returns the svg elements that show the placement the player goes to when they lose
 * placed at the bottom of the loser's bracket
 **/
function fsMakePlacementSVG($piMaxLoserMID, $piMaxLoserHeight, $piTourneySize, $pbContainsGF2) {
	// 1 2 3 4 56 78 9101112 13141516
	// 16-> 6 / 7
	// 8 -> 4 / 5
	$iPowerOfTwo = fiPowOfTwo($piTourneySize);
	$iMaxColumn = $pbContainsGF2 ? fiCalculateColumn(GRAND_FINALS2, $iPowerOfTwo) : fiCalculateColumn(LOSERS_FINALS, $iPowerOfTwo);
	$sTBR = "";
	$iMIDCounter = 1;
	$iLoserCount = 3;
	$iIncrement = 1;
	while($iMIDCounter <= fiLastFive($piMaxLoserMID)) {
		$iLeftOffset = fiCalculateLeftOffset("L" . fsPad($iMIDCounter, PAD_FULL), $piTourneySize);
		$iTopOffset = $piMaxLoserHeight; // + iBoxHeight + iPadHeight;
		$sTBR .= "<text><tspan class=\"placement\" ";
		$sTBR .= "x=" . $iLeftOffset . " y=" . $iTopOffset;
		$sTBR .= ">";
		if($iLoserCount > 4) {
			$sTBR .= "Loser gets top " . $iLoserCount;
		}
		else {
			$sTBR .= "Loser gets " . $iLoserCount;

			switch(substr($iLoserCount,-1)) {
				case "1": $sTBR .= "st"; break;
				case "2": $sTBR .= "nd"; break;
				case "3": $sTBR .= "rd"; break;
				default:  $sTBR .= "th"; break;
			}
		}
		for($iTemp = 0; $iTemp < $iIncrement; $iTemp++) {
			$iLoserCount++;
		}
		if( pow(2, (int) (log($iLoserCount, 2))) == $iLoserCount ) $iIncrement *=2;
		$sTBR .= "</tspan></text>";
		$iMIDCounter *=2;
	}
	return $sTBR;

}

/**
 * returns the offset from the left (ie. an X coordinate)
 **/
function fiCalculateLeftOffset($psMID, $piPowerOfTwo) {
	// 25 comes from the arrow button
	return 25+iLabelWidth + fiCalculateColumn($psMID, $piPowerOfTwo) * (iBoxWidth+iPadWidth+iLabelWidth);
}

/** 
 * returns what column the match should fit into
 * losers round one returns 0, next round returns 1
 * winners is offset a little differently
 **/
function fiCalculateColumn($psMID, $piPowerOfTwo) {
	static $iaColumns = array();

	$iY = fiLastFive($psMID); // called "Y" because that's the convention for the last 5 digits of a matchID
	$sSimplifiedMID	= substr($psMID,0,1) . fsPad($iY,	PAD_HALF);
	$sDoubleSimple	= substr($psMID,0,1) . fsPad($iY*2,	PAD_HALF);

	if(isset($iaColumns[$sSimplifiedMID])) return $iaColumns[$sSimplifiedMID];

	if ($psMID == GRAND_FINALS2) {
		$iaColumns[$sSimplifiedMID] = fiCalculateColumn(LOSERS_FINALS, $piPowerOfTwo)+1;
	}
	else if($psMID == GRAND_FINALS) {
		$iaColumns[$sSimplifiedMID] = fiCalculateColumn(WINNERS_FINALS, $piPowerOfTwo)+1;
	}
	else if(substr($psMID, 0,1)=="W" && $iY >= $piPowerOfTwo/2) {
		$iaColumns[$sSimplifiedMID] = 0;
	}
	else if($iY >= $piPowerOfTwo*$piPowerOfTwo/4) { // first round, for 16 players, L32 ... we want L32 and W8
		$iaColumns[$sSimplifiedMID] = -1; // push the first round of losers off the page
	}
	else {
		$iaColumns[$sSimplifiedMID] = fiCalculateColumn($sDoubleSimple, $piPowerOfTwo)+1;
	}
	return $iaColumns[$sSimplifiedMID];
}

/**
 * returns the offset from the top (ie. a Y coordinate)
 **/
function fiCalculateTopOffset($psMID, $piPowerOfTwo) {
	static $iaHeights = array();

	$piPowerOfTwo = fiPowOfTwo($piPowerOfTwo); // just in case? X_x

	$iY = fiLastFive($psMID);
	$sSimplifiedMID = substr($psMID,0,1) . fsPad($iY, PAD_HALF);

	if(isset($iaHeights[$sSimplifiedMID])) return $iaHeights[$sSimplifiedMID];

	if($psMID == GRAND_FINALS2) {
		$iUpperHeight = fiCalculateTopOffset(GRAND_FINALS, $piPowerOfTwo);
		$iLowerHeight = fiCalculateTopOffset(GRAND_FINALS, $piPowerOfTwo);
		$iaHeights[$sSimplifiedMID] = ($iUpperHeight + $iLowerHeight)/2.;
	}
	else if($psMID==GRAND_FINALS) {
		$iUpperHeight = fiCalculateTopOffset(WINNERS_FINALS, $piPowerOfTwo);
		$iLowerHeight = fiCalculateTopOffset(LOSERS_FINALS,  $piPowerOfTwo);
		$iaHeights[$sSimplifiedMID] = ($iUpperHeight + $iLowerHeight)/2.;
	}
	else if(substr($psMID,0,1)=="L") {
		$iTwoCol = (int) log($iY, 4);
		$iFirstLoserMatch = $piPowerOfTwo*$piPowerOfTwo/4; // 16 => 64, 8 => 16
		$iMaxDepth = $piPowerOfTwo/2; // max # of winners matches in a round

		if($iY >= $iFirstLoserMatch) { // greater than means inside of the first round of losers
			$iSetToZero = $iY-$iFirstLoserMatch;				// creates		0, 1,  4,  5, etc.
			$iToBaseFour = base_convert($iSetToZero, 10, 4);	// turn this into 0, 1, 10, 11, etc.
			$iThisDepth = base_convert($iToBaseFour, 2, 10);	// turn this into 0, 1,  2,  3, etc.
			$iaHeights[$sSimplifiedMID] = ($iThisDepth/2 + 0 /*$iMaxDepth*/)*(iBoxHeight+iPadHeight); // want these closer, so the first displayed round is closer
		}
		else if($iY < 2*pow(4,$iTwoCol)) { // matches with losers from winners
			$iaHeights[$sSimplifiedMID] = fiCalculateTopOffset("L" . fsPad($iY*2, PAD_HALF), $piPowerOfTwo);
		}
		else { // matches from losers
			$iUpperHeight = fiCalculateTopOffset("L" . fsPad($iY*2,  PAD_HALF), $piPowerOfTwo);
			$iLowerHeight = fiCalculateTopOffset("L" . fsPad($iY*2+1,PAD_HALF), $piPowerOfTwo);
			$iaHeights[$sSimplifiedMID] = ($iUpperHeight+$iLowerHeight)/2.;
		}
	}
	else { // winners bracket
		if($iY >= $piPowerOfTwo/2) { // the first round of the winners bracket
			$iThisDepth = $iY - $piPowerOfTwo/2; // 8 - 16/2 = 0, 15 - 16/2 = 7
			$iaHeights[$sSimplifiedMID] = $iThisDepth * (iBoxHeight+iPadHeight);
		}
		else { // every other round
			$iUpperHeight = fiCalculateTopOffset("W" . fsPad($iY*2,  PAD_HALF), $piPowerOfTwo);
			$iLowerHeight = fiCalculateTopOffset("W" . fsPad($iY*2+1,PAD_HALF), $piPowerOfTwo);
			$iaHeights[$sSimplifiedMID] = ($iUpperHeight+$iLowerHeight)/2.;
		}
	}
	return $iaHeights[$sSimplifiedMID];
}
?>

<style>
body {
	font-family: "Palatino Linotype";
	font-size: 90%;
}
.nowinner, .placement {
	font-style: italic;
	fill:#5A5A5A;
}
.matchid {
	font-weight: bold;
	text-anchor: end;
}
.horzline {
	stroke:rgb(42,0,255); /* 0 0 255 */
	stroke-width:1;
}
.vertline {
/*  stroke:rgb(255,100,5); */
/*  stroke: rgb(90,90,90); */
	stroke: rgb(42,0,255);
	stroke-width:3;
}
.graybox {
	fill:url(#graygrad);
	width:200px;
	height:50px;
}
.winbox {
	fill:url(#wingrad);
	width:200px;
	height:25px;
}
.result {
	font-weight: bold;
}
.grayl {
	stroke: rgb(90,90,90);
}
h3, h2 {
	text-align: center;
}

.notepad {
	width:25px;
	height:25px;
	cursor: pointer;
}
.winnerSVG, .loserSVG {
	width: calc(100% - 50px);
	display: inline;
}
g {
	transition: all 0.5s ease-in-out;
	transition-delay: .25s;
}
.rectButt, .triang {
	cursor: pointer;
	transition: all 0.25s ease-in-out;
}
.rectButt {
	/* fill:rgba(61,00,204,0);*/
	fill:rgba(255,100,5,.22); /*85,00,255,.68); */
}
.triang {
	fill: #5500ff; /* white */
}
.disabledRectangle {
	cursor: not-allowed;
	fill: darkgray;
}
.disabledTriangle {
	cursor: not-allowed;
	fill: lightgray;
}
.nobordertable {
	border-collapse: collapse;
	border: none;
	margin: auto;
}
@media only screen and (min-width:560px) { /* on small screens, disable hover (one click to trigger svg buttons) */
	.gButton:hover .rectButt {
		fill:rgba(255,100,5,.68); /*85,00,255,.68); */
	}
	.gButton:hover .triang {
		fill:#3d00cc;
	}
	.gButton:hover .disabledRectangle {
		fill:darkgray;
	}
	.gButton:hover .disabledTriangle {
		fill:lightgray;
	}
}
/* ******************** */
.game {
  display:block;
  margin: 2em auto;
  text-align: center;
  transition: opacity 0.35s ease-in-out;
}
.wins {
	text-align: center;
	display: inline-block;
	width:48%;
}
.resWinner, .resLoser, .wins {
	font-size: larger;
	font-family: sans-serif;
}
.resWinner {
	color:#ff6405;
}
.resWinner:after {
	content:"W";
}
.resLoser {
	color:gray;
}
.resLoser:after {
	content:"L";
}
.gres {
	width:15%;
  
	display: inline-block;
}
.stupidplaceholder {
	display: inline-block;
	width:60%;
}
input[type=range] {
	-webkit-appearance: none;
	margin: 10px 0;
	width: 100%;
	user-select: none;
}
input[type=range]:focus {
	outline: none;
}
input[type=range]::-webkit-slider-runnable-track {
	width: 100%;
	height: 20px;
	cursor: pointer;
	animate: 0.2s;
	box-shadow: 1px 1px 2px #000000;
	background: #FFFFFF;
	border-radius: 5px;
	border: 1px solid #000000;
}
input[type=range]::-webkit-slider-thumb {
	box-shadow: 0px 0px 5px #000000;
	border: 1px solid #000000;
	height: 50px;
	width: 50px;
	border-radius: 25px;
	/*background: #643DB3; */
	cursor: move;
	-webkit-appearance: none;
	margin-top: -15px;
}
input[type=range]:focus::-webkit-slider-runnable-track {
	background: #FFFFFF;
}
input[type=range]::-moz-range-track {
	width: 100%;
	height: 20px;
	cursor: pointer;
	animate: 0.2s;
	box-shadow: 1px 1px 2px #000000;
	background: #FFFFFF;
	border-radius: 5px;
	border: 1px solid #000000;
}
input[type=range]::-moz-range-thumb {
	box-shadow: 0px 0px 5px #000000;
	border: 1px solid #000000;
	height: 37px;
	width: 50px;
	border-radius: 10px;
	cursor: move;
}
input[type=range]::-ms-track {
	width: 100%;
	height: 20px;
	cursor: pointer;
	animate: 0.2s;
	background: transparent;
	border-color: transparent;
	color: transparent;
}
input[type=range]::-ms-fill-lower {
	background: #FFFFFF;
	border: 1px solid #000000;
	border-radius: 10px;
	box-shadow: 1px 1px 2px #000000;
}
input[type=range]::-ms-fill-upper {
	background: #FFFFFF;
	border: 1px solid #000000;
	border-radius: 10px;
	box-shadow: 1px 1px 2px #000000;
}
input[type=range]::-ms-thumb {
	box-shadow: 0px 0px 5px #000000;
	border: 1px solid #000000;
	height: 37px;
	width: 50px;
	border-radius: 10px;
	/*background: #643DB3; */
	cursor: move;
}

#g1::-webkit-slider-thumb {
	background-image: url('/images/g1.png');
}
#g1::-moz-range-thumb {
	background-image: url('/images/g1.png');
}
#g1::-ms-thumb {
	background-image: url('/images/g1.png');
}

#g2::-webkit-slider-thumb {
	background-image: url('/images/g2.png');
}
#g2::-moz-range-thumb {
	background-image: url('/images/g2.png');
}
#g2::-ms-thumb {
	background-image: url('/images/g2.png');
}

#g3::-webkit-slider-thumb {
	background-image: url('/images/g3.png');
}
#g3::-moz-range-thumb {
	background-image: url('/images/g3.png');
}
#g3::-ms-thumb {
	background-image: url('/images/g3.png');
}

input[type=range]:focus::-ms-fill-lower {
	background: #FFFFFF;
}
input[type=range]:focus::-ms-fill-upper {
	background: #FFFFFF;
}

input[type=range][title="W"]::-webkit-slider-thumb {
	background-image: url('/images/checkicon50.png');
}
input[type=range][title="W"]::-moz-range-thumb {
	background-image: url('/images/checkicon50.png');
}
input[type=range][title="W"]::-ms-thumb {
	background-image: url('/images/checkicon50.png');
}

.dcicheck {
	padding:10px;
	text-align:center;
	-webkit-text-security: disc;
	/*width: 9em; */
	width: 70%;
}
.dcicheck::-webkit-outer-spin-button,
.dcicheck::-webkit-inner-spin-button {
	/* display: none; <- Crashes Chrome on hover */
	-webkit-appearance: none;
	margin: 0; /* <-- Apparently some margin are still there even though it's hidden */
}
</style>
<script>
function detectswipe(el,func) {
	swipe_det = new Object();
	swipe_det.sX = 0;
	swipe_det.sY = 0;
	swipe_det.eX = 0;
	swipe_det.eY = 0;
	var min_x = 20;  //min x swipe for horizontal swipe
	var max_x = 40;  //max x difference for vertical swipe
	var min_y = 40;  //min y swipe for vertical swipe
	var max_y = 50;  //max y difference for horizontal swipe
	var direc = "";
	ele = document.getElementById(el);
	ele.addEventListener('touchstart',function(e){
		var t = e.touches[0];
		swipe_det.sX = t.screenX; 
		swipe_det.sY = t.screenY;
		},false);
	ele.addEventListener('touchmove',function(e){
		e.preventDefault();
		var t = e.touches[0];
		swipe_det.eX = t.screenX; 
		swipe_det.eY = t.screenY;
	  },false);
	  ele.addEventListener('touchend',function(e){
		//horizontal detection
		if ((((swipe_det.eX - min_x > swipe_det.sX) || (swipe_det.eX + min_x < swipe_det.sX)) && ((swipe_det.eY < swipe_det.sY + max_y) && (swipe_det.sY > swipe_det.eY - max_y)))) {
			if(swipe_det.eX > swipe_det.sX) direc = "r";
			else direc = "l";
		}
		//vertical detection
		if ((((swipe_det.eY - min_y > swipe_det.sY) || (swipe_det.eY + min_y < swipe_det.sY)) && ((swipe_det.eX < swipe_det.sX + max_x) && (swipe_det.sX > swipe_det.eX - max_x)))) {
			if(swipe_det.eY > swipe_det.sY) direc = "d";
			else direc = "u";
		}
	
		if (direc != "") {
			if(typeof func == 'function') func(el,direc);
		}
		direc = "";
	},false);
}

function fvSwipeView(peWhichSVG, psWhichDirection) {
	//alert(psWhichDirection == 'r');
	switch(peWhichSVG) {
		case 'wslipeSVG':
			switch(psWhichDirection) {
				case 'r':
					updateView('winnerSVG', 'left');
					break;
				case 'l':
					updateView('winnerSVG', 'right');
					break;
				case 'u':
					window.location.hash= '#lslipeSVG';
					break;
			}
			break;
		case 'lslipeSVG':
			switch(psWhichDirection) {
				case 'r':
					updateView('loserSVG', 'left');
					break;
				case 'l':
					updateView('loserSVG', 'right');
					break;
				case 'd':
					window.location.hash= '#wslipeSVG';
					break;
			}
			break;
	}
	//if(psWhichDirection == 'r')
	//	updateView(peWhichSVG.id, 'right');
	//else (psWhichDirection == 'l')
	//	updateView(peWhichSVG.id, 'left');
}

/*
	minX = parseInt(curBox.substring(0,curBox.indexOf(" ")));
	minY = curBox.substring(curBox.indexOf(" ")+1);
	minY = minY.substring(0, minY.indexOf(" "));
	heit = curBox.substring(curBox.lastIndexOf(" ")+1);

	parameters:
	psWhichSVG:			winnerSVG or loserSVG
	psWhichDirection:	left or right
 */
function updateView(psWhichSVG, psWhichDirection) {

	gTransforming = psWhichSVG=="winnerSVG" ? document.getElementById('gW') : document.getElementById('gL');
	xyz = document.getElementById(psWhichSVG);
	curBox = xyz.getAttribute('viewBox');
	widt = curBox.substring(0, curBox.lastIndexOf(" "));
	widt = widt.substring(widt.lastIndexOf(" ")+1);

	maxwidth = xyz.dataset.maxwidth;
	lastcontwidth = parseInt(maxwidth) + 265;

	iTransX = gTransforming.transform.baseVal.getItem(0).matrix.e;
	if(psWhichDirection=="left") iTransX += 245;
	if(psWhichDirection=="right") iTransX -= 245;
	gTransforming.setAttribute('transform', 'translate(' + (iTransX) + ',0)');

	// we want to disable to-the-right when iTransX+contentwidth > viewwidth
	// we want to disable to-the-left when iTrans>=0 (increasing iTrans moves the screen off to the left)

	// once we hit max side, turn off button
	if(iTransX>=0) { // left button
		// no function
		document.getElementById(gTransforming.id + 'L').onclick = null;
		// no cursor, greyed out
		document.getElementById(gTransforming.id + 'LTriangle').classList.add('disabledTriangle');
		document.getElementById(gTransforming.id + 'LRectangle').classList.add('disabledRectangle');
	
	}
	else {
		// function
		document.getElementById(gTransforming.id + 'L').onclick = function() {updateView(psWhichSVG,'left');};
		// cursor, purple
		document.getElementById(gTransforming.id + 'LTriangle').classList.remove('disabledTriangle');
		document.getElementById(gTransforming.id + 'LRectangle').classList.remove('disabledRectangle');
	}

	if(lastcontwidth + iTransX <= widt) { // right button
		// no function
		document.getElementById(gTransforming.id + 'R').onclick = null;
		// cursor, purple
		document.getElementById(gTransforming.id + 'RTriangle').classList.add('disabledTriangle');
		document.getElementById(gTransforming.id + 'RRectangle').classList.add('disabledRectangle');
	}
	else {
		// function
		document.getElementById(gTransforming.id + 'R').onclick = function() {updateView(psWhichSVG,'right');};
		// cursor, purple
		document.getElementById(gTransforming.id + 'RTriangle').classList.remove('disabledTriangle');
		document.getElementById(gTransforming.id + 'RRectangle').classList.remove('disabledRectangle');
	}
}
/* onload, move the contents to the appropriate spot */
function fvStartRight() {
	fvRealStart('winnerSVG');
	fvRealStart('loserSVG');
}

function fvRealStart(psWhichSVG) {
	iTransX = 0;

	gTransforming = psWhichSVG=="winnerSVG" ? document.getElementById('gW') : document.getElementById('gL');
	//alert(gTransforming.id);
	xyz = document.getElementById(psWhichSVG);
	curBox = xyz.getAttribute('viewBox');
	
	widt = curBox.substring(0, curBox.lastIndexOf(" "));
	
	widt = parseInt(widt.substring(widt.lastIndexOf(" ")+1));

	maxwidth = xyz.dataset.maxwidth;
	lastcontwidth = parseInt(maxwidth) + 265;

	iHowMuch = parseInt(xyz.dataset.startoffset) - 61; // -60 comes from the label, -1 to upset the while loop below
	
	while(iTransX >= 0-iHowMuch && (lastcontwidth + iTransX > widt)) {
		iTransX -= 245;
	}

	// once we hit max side, turn off buttons
	if(iTransX>=0) { // left button
		// no function
		document.getElementById(gTransforming.id + 'L').onclick = null;
		// no cursor, greyed out
		document.getElementById(gTransforming.id + 'LTriangle').classList.add('disabledTriangle');
		document.getElementById(gTransforming.id + 'LRectangle').classList.add('disabledRectangle');
	
	}
	else {
		// function
		document.getElementById(gTransforming.id + 'L').onclick = function() {updateView(psWhichSVG,'left');};
		// cursor, purple
		document.getElementById(gTransforming.id + 'LTriangle').classList.remove('disabledTriangle');
		document.getElementById(gTransforming.id + 'LRectangle').classList.remove('disabledRectangle');
	}

	if(lastcontwidth + iTransX <= widt) { // right button
		// no function
		document.getElementById(gTransforming.id + 'R').onclick = null;
		// cursor, purple
		document.getElementById(gTransforming.id + 'RTriangle').classList.add('disabledTriangle');
		document.getElementById(gTransforming.id + 'RRectangle').classList.add('disabledRectangle');
	}
	else {
		// function
		document.getElementById(gTransforming.id + 'R').onclick = function() {updateView(psWhichSVG,'right');};
		// cursor, purple
		document.getElementById(gTransforming.id + 'RTriangle').classList.remove('disabledTriangle');
		document.getElementById(gTransforming.id + 'RRectangle').classList.remove('disabledRectangle');
	}

	gTransforming.setAttribute('transform', 'translate(' + (iTransX) + ',0)');
	gTransforming.style.transitionDelay="0s";


}

function fvMatchPopUp(psTID,psMID,psPlayer0,psP0First,psP0Last,psPlayer1,psP1First,psP1Last) {
	resultTID.value = psTID;
	resultMID.value = psMID;
	p0dci.value = psPlayer0;
	p1dci.value = psPlayer1;
	p0FirstName.innerHTML = psP0First;
	p0LastName.innerHTML = psP0Last;
	p1FirstName.innerHTML = psP1First;
	p1LastName.innerHTML = psP1Last;
	pass0.dataset.dci = psPlayer0.slice(0,4);
	pass1.dataset.dci = psPlayer1.slice(0,4);

	g1.value=50;
	g2.value=50;
	g3.value=50;
	p0g1.classList.remove('resWinner');
	p0g1.classList.remove('resLoser');
	p1g1.classList.remove('resWinner');
	p1g1.classList.remove('resLoser');

	p0g2.classList.remove('resWinner');
	p0g2.classList.remove('resLoser');
	p1g2.classList.remove('resWinner');
	p1g2.classList.remove('resLoser');

	p0g3.classList.remove('resWinner');
	p0g3.classList.remove('resLoser');
	p1g3.classList.remove('resWinner');
	p1g3.classList.remove('resLoser');

	g3line.style.opacity=1;

	p0wlabel.innerHTML = 0;
	p0wins.value=0;
	p1wlabel.innerHTML = 0;
	p1wins.value=0;
	submitresult.disabled = true;
	modalmatch.style.display="block";
	//alert(psTID + " " + psMID + "\n" + psPlayer0 + " " + psP0First + " " + psP0Last + "\n");
}

function fvUpdateWinner(psGame) {
	p0game = document.getElementById('p0'+psGame);
	p1game = document.getElementById('p1'+psGame);
	if(document.getElementById(psGame).value<40) {
		p0game.classList.add('resWinner');
		p0game.classList.remove('resLoser');
		p1game.classList.add('resLoser');
		p1game.classList.remove('resWinner');
		document.getElementById(psGame).value=0;
		document.getElementById(psGame).title="W";
	}
	else if(document.getElementById(psGame).value>60) {
		p0game.classList.add('resLoser');
		p0game.classList.remove('resWinner');
		p1game.classList.add('resWinner');
		p1game.classList.remove('resLoser');
		document.getElementById(psGame).value=100;
		document.getElementById(psGame).title="W";
	}
	else {
		p0game.classList.remove('resWinner');
		p0game.classList.remove('resLoser');
		p1game.classList.remove('resWinner');
		p1game.classList.remove('resLoser');
		document.getElementById(psGame).value=50;
		document.getElementById(psGame).title="";
	}
	if(g1.value!=g2.value || g1.value==50 || g2.value==50) { //} p0g1.classList.contains('resWinner') && p1g2.classList.contains('resWinner') || p0g1.classList.contains('resLoser') && p1g2.classList.contains('resLoser')) {
		g3line.style.opacity=1;
	}
	else {
		g3line.style.opacity=0;
		g3.value=50;
		g3.title="";
		p0g3.classList.remove('resWinner');
		p1g3.classList.remove('resWinner');
		p0g3.classList.remove('resLoser');
		p1g3.classList.remove('resLoser');
	}

	p0wlabel.innerHTML = (p0g1.classList.contains('resWinner')?1:0) + (p0g2.classList.contains('resWinner')?1:0) + ((p0g3.classList.contains('resWinner')&&g3line.style.opacity==1)?1:0);
	p0wins.value = p0wlabel.innerHTML;

	p1wlabel.innerHTML = (p1g1.classList.contains('resWinner')?1:0) + (p1g2.classList.contains('resWinner')?1:0) + ((p1g3.classList.contains('resWinner')&&g3line.style.opacity==1)?1:0);
	p1wins.value = p1wlabel.innerHTML;

	bNotEnoughWins = (parseInt(p0wlabel.innerHTML) < 2 && parseInt(p1wlabel.innerHTML) < 2);
	bWrongDCI = pass0.classList.contains('redborder') || pass1.classList.contains('redborder');
	bUnfilledG1 = !p0g1.classList.contains('resLoser') && !p0g1.classList.contains('resWinner');
	bUnfilledG2 = !p0g2.classList.contains('resLoser') && !p0g2.classList.contains('resWinner');
	
	submitresult.disabled = bNotEnoughWins || bWrongDCI || bUnfilledG1 || bUnfilledG2;
}

function fvDCIPasswordCheck(peDCIField) {
	peDCIField.value = peDCIField.value.replace('-','');
	if(peDCIField.value.length>4) peDCIField.value=peDCIField.value.slice(0,4);
	if(parseInt(peDCIField.value) != parseInt(peDCIField.dataset.dci)) {
		peDCIField.classList.add('redborder');
		peDCIField.classList.remove('blackborder');
	}
	else {
		peDCIField.classList.remove('redborder');
		peDCIField.classList.add('blackborder');
	}

	submitresult.disabled = (parseInt(p0wlabel.innerHTML) < 2 && parseInt(p1wlabel.innerHTML) < 2) || pass0.classList.contains('redborder') || pass1.classList.contains('redborder');
}

window.addEventListener('click', function() {
	if(event.target == modalmatch) {
		modalmatch.style.display="none";
	}
}, false);
</script>
</head>
<body onload="fvStartRight();">
<?php include ABSPATH . "/navi.php"; ?>
<div id="modalmatch" class="modal">
	<form class="modal-content animate" method="POST">
		<div class="imgcontainer">
			<span onclick="modalmatch.style.display='none'" class="close" title="Close Modal">&times;</span>
			<!--<img src="/images/signin.png" alt="Avatar" class="avatar"> -->
		</div>
		<div class="container">
			<div class="wins" id="p0FirstName">P0FirstName</div>
			<div class="wins" id="p1FirstName">P1FirstName</div>
			<div class="wins" id="p0LastName">P0Lastname</div>
			<div class="wins" id="p1LastName">P1Lastname</div>
			<div class="wins"><h3 id="p0wlabel">0</h3></div>
			<div class="wins"><h3 id="p1wlabel">0</h3></div>
			<div class="game" id="g1line">
				<div class="gres" id="p0g1"></div>
				<div class="stupidplaceholder">
					<input type="range" name="g1" id="g1" min="0" max="100" onchange="fvUpdateWinner('g1')">
				</div>
				<div class="gres" id="p1g1"></div>
			</div>
			<div class="game" id="g2line">
				<div class="gres" id="p0g2"></div>
				<div class="stupidplaceholder">
					<input type="range" name="g2" id="g2" min="0" max="100" onchange="fvUpdateWinner('g2')">
				</div>
				<div class="gres" id="p1g2"></div>
			</div>
			<div class="game" id="g3line" style="opacity:0">
				<div class="gres" id="p0g3"></div>
				<div class="stupidplaceholder">
					<input type="range" name="g3" id="g3" min="0" max="100" onchange="fvUpdateWinner('g3')">
				</div>
				<div class="gres" id="p1g3"></div>
			</div>
			<div class="wins"><input id="pass0" oninput="fvDCIPasswordCheck(this)" type="number" max="9999" class="dcicheck redborder" placeholder="DCI (first 4 digits)" required></div>
			<div class="wins"><input id="pass1" oninput="fvDCIPasswordCheck(this)" type="number" max="9999" class="dcicheck redborder" placeholder="DCI (first 4 digits)" required></div>

			<input type="hidden" name="tournamentID" id="resultTID" value="??">
			<input type="hidden" name="mID" id="resultMID" value="??">
			<input type="hidden" name="p0wins" id="p0wins" value=0>
			<input type="hidden" name="p1wins" id="p1wins" value=0>
			<input type="hidden" name="p0dci" id="p0dci" value=0>
			<input type="hidden" name="p1dci" id="p1dci" value=0>
			<button type="submit" class="submbtn" id="submitresult" disabled>Submit Result</button>
			<div class="container" style="background-color:#f1f1f1">
				<button type="button" onclick="modalmatch.style.display='none'" class="cancelbtn">Cancel</button>
		</div>
	</div>
	</form>
</div>
<div id="wrapper">
	<svg height="0" width="0">
	<linearGradient id="graygrad" x1="0%" y1="0%" x2="100%" y2="0%">
		<stop offset="0%" style="stop-color:rgb(225,225,225);stop-opacity:1" />
		<stop offset="100%" style="stop-color:rgb(255,255,255);stop-opacity:1" />
	</linearGradient>
	<linearGradient id="wingrad" x1="0%" y1="0%" x2="100%" y2="0%">
		<stop offset="0%" style="stop-color:rgb(255,100,5);stop-opacity:1" />
		<stop offset="100%" style="stop-color:rgb(255,255,255);stop-opacity:1" />
	</linearGradient>
	</svg>

	
<?php
	if(isset($sTID)) {
		//$sqlGetAllMatches = "SELECT * From Matches WHERE tID=?";
		//$resMatches = fresQuery([$sqlGetAllMatches, "s", ["6"]]);
		print "<h3>".$saTourneySize['tName']."</h3>";

		print fsMakeWinnerSVG($resAllWinnerMatches, $iTourneySize);

		print fsMakeLoserSVG($resAllLoserMatches, $iTourneySize);
	}
	else {
		print "<p>Choose a tournament:</p>";
		print "<form method=\"get\">";
		print fsCreateTournamentSelector(1); // we want to see started tournaments
		print "	<br><input type=\"submit\" value=\"View Bracket\">";
		print "</form>";
	}
include ABSPATH . '/footinclude.php';
?>
</div>
<script>
//detectswipe('wslipeSVG', fvSwipeView);
//detectswipe('lslipeSVG', fvSwipeView);
</script>
</body>
</html>
