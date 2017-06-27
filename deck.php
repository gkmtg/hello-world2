<!DOCTYPE HTML>
<html lang="en">
<head>
<?php
include '/usr/home/gkmtg/public_html/gkmtg.pro/headinclude.php';  // has utility

function fsExtractDeckName($psDeckDivText) {
	$iDeckTitleIndex = strpos($psDeckDivText, "class=\"decktitle\">") + strlen("class=\"decktitle\">");
	$iDeckLastTitleIndex = strpos($psDeckDivText, "<", $iDeckTitleIndex);
	return substr($psDeckDivText, $iDeckTitleIndex, $iDeckLastTitleIndex-$iDeckTitleIndex);

}

// if this is live, this should be removed and added to utility

if(isset($_GET['d']) && $_GET['d']!='index.php') {
	$sDeckDiv = fsGenerateDeckDiv2($_GET['d']);
	$sDeckName = fsExtractDeckName($sDeckDiv);
	print "<title>" . $sDeckName . "</title>";
}
else {
	print "<title>Need a Deck ID</title>";
}

/**
 * returns a nice div with the appropriate functions you need for a deck
 * TODO: cards have their own mouseover stuff
 * TODO: all of the deck functions (favorite, move to deckbuilder, sort by data, ... ?)
 **/
function fsGenerateDeckDiv2($psDeckID) {
	$sqlGetDeck = "SELECT * FROM DeckCards WHERE DeckID=? AND MainSide = ?";
	$stmtGetDeck = DB::cxn()->prepare($sqlGetDeck);
	// main
	$hmMainDeck = array();
	$iTempDeck = MAIN;
	$stmtGetDeck->bind_param('si', $psDeckID, $iTempDeck);
	$stmtGetDeck->execute();
	$resMainDeck = $stmtGetDeck->get_result();
	while($saMainDeckUnit = $resMainDeck->fetch_assoc()) {
		$hmMainDeck[$saMainDeckUnit['UniqueID']] = $saMainDeckUnit['Quantity'];
	}
	// side
	$hmSideDeck = array();
	$iTempDeck = SIDE;
	$stmtGetDeck->bind_param('si', $psDeckID, $iTempDeck);
	$stmtGetDeck->execute();
	$resSideDeck = $stmtGetDeck->get_result();
	while($saSideDeckUnit = $resSideDeck->fetch_assoc()) {
		$hmSideDeck[$saSideDeckUnit['UniqueID']] = $saSideDeckUnit['Quantity'];
	}
	// other info
	$sqlGetDeckInfo = "SELECT * FROM Decks WHERE DeckID = ?";
	$resGetDeckInfo = fresQuery([$sqlGetDeckInfo, 's', [$psDeckID]]);
	$saDeckInfo = $resGetDeckInfo->fetch_assoc();
	
	$sqlDeckBuilder = "SELECT * FROM Players WHERE DCI = ?";
	$resDeckBuilder = fresQuery([$sqlDeckBuilder, 's', [$saDeckInfo['DCI']]]);
	$saDeckBuilder = $resDeckBuilder->fetch_assoc();

	$sqlTourneyInfo = "SELECT * FROM Tournaments WHERE tID = ?";
	$resTourneyInfo = fresQuery([$sqlTourneyInfo, 's', [$saDeckInfo['tID']]]);
	$saTourneyInfo = $resTourneyInfo->fetch_assoc();

	$sTBR = "<div class=\"deckDiv\">";
	$sTBR .= "<div class=\"decktitle\">" . $saDeckInfo['DeckName'] . "</div>";
	$sTBR .= "<div class=\"buildertitle\">" . $saDeckBuilder['FirstName'] . " " . $saDeckBuilder['LastName'] . "</div>";
	if(isset($saDeckInfo['EventName']) && $saDeckInfo['EventName'] != ""){
		$sTBR .= "<h4>" . ((isset($saDeckInfo['Record']) && $saDeckInfo['Record'] != "")? $saDeckInfo['Record'] . " at ":"") . $saTourneyInfo['tName'] . "</h4>";	
	}
	

	$sqlCardInfo = "SELECT * FROM CardInvariants WHERE UniqueID = ?";
	$stmtCardInfo = DB::cxn()->prepare($sqlCardInfo);
	$sTBR .= "<div class=\"mainsidetitle\">MAIN</div> Sort by ...<br>";
	$sTBR .= "<button onclick=\"fvSortDeck('" . $psDeckID . "Main', 'cmc')\">CMC</button>";
	$sTBR .= "&nbsp;<button onclick=\"fvSortDeck('" . $psDeckID . "Main', 'types')\">Type</button>";
	$sTBR .= "&nbsp;<button onclick=\"fvSortDeck('" . $psDeckID . "Main', 'name')\">Name</button>";
	$sTBR .= "<div class=\"indivDeck\" id=\"" . $psDeckID . "Main\">\n";
	foreach ($hmMainDeck as $key => $value) {
		$stmtCardInfo->bind_param('s', $key);
		$stmtCardInfo->execute();
		$resCardInfo = $stmtCardInfo->get_result();
		$saCardInfo = $resCardInfo->fetch_assoc();

		$sPrefVar = fsPreferredVariant($saCardInfo['UniqueID']);

		$sTBR .= "<div class=\"cardindeck\" ";
		$sTBR .= "data-name=\"" . $saCardInfo['Name'] . "\" ";
		$sTBR .= "data-cmc=\"" . $saCardInfo['CMC'] . "\" ";
		$sTBR .= "data-types=\"" . $saCardInfo['Supertypes'] . "\"";
		$sTBR .= ">" . $value . " <span onmouseover=\"fvViewGK('" . $sPrefVar . "')\";>" . $saCardInfo['Name'] . "</span></div>\n";
	}
	$sTBR .= "<img src onerror=\"fvSortDeck('" . $psDeckID . "Main','types');\">";
	$sTBR .= "</div>";
	$sTBR .= "<div class=\"mainsidetitle\">SIDEBOARD</div> Sort by ...<br>"; // uhh, just text??
	$sTBR .= "<button onclick=\"fvSortDeck('" . $psDeckID . "Side', 'cmc')\">CMC</button>";
	$sTBR .= "&nbsp;<button onclick=\"fvSortDeck('" . $psDeckID . "Side', 'types')\">Type</button>";
	$sTBR .= "&nbsp;<button onclick=\"fvSortDeck('" . $psDeckID . "Side', 'name')\">Name</button>";
	$sTBR .= "<div class=\"indivDeck\" id=\"" . $psDeckID . "Side\">\n";
	foreach ($hmSideDeck as $key => $value) {
		$stmtCardInfo->bind_param('s', $key);
		$stmtCardInfo->execute();
		$resCardInfo = $stmtCardInfo->get_result();
		$saCardInfo = $resCardInfo->fetch_assoc();

		$sPrefVar = fsPreferredVariant($saCardInfo['UniqueID']);

		$sTBR .= "<div class=\"cardindeck\" ";
		$sTBR .= "data-name=\"" . $saCardInfo['Name'] . "\" ";
		$sTBR .= "data-cmc=\"" . $saCardInfo['CMC'] . "\" ";
		$sTBR .= "data-types=\"" . $saCardInfo['Supertypes'] . "\"";
		$sTBR .= ">" . $value . " <span onmouseover=\"fvViewGK('" . $sPrefVar . "')\";>" . $saCardInfo['Name'] . "</span></div>\n";
	}
	$sTBR .= "</div>";
	$sTBR .= "<img src onerror=\"setTimeout(function() {fvSortDeck('" . $psDeckID . "Side','types');}, 500);\">";
	$sTBR .= "</div>";
	return $sTBR;
}

?>
<script>
function fiGKScore(psTypeLine) {
	iScore = 0;
	if(psTypeLine.toLowerCase().includes('artifact'))		iScore = 1;
	if(psTypeLine.toLowerCase().includes('enchantment'))	iScore = 5; // artifact enchantments are lumped with enchantments
	if(psTypeLine.toLowerCase().includes('instant'))		iScore = 6; // instants don't overlap
	if(psTypeLine.toLowerCase().includes('planeswalker'))	iScore = 7; // planeswalkers don't overlap
	if(psTypeLine.toLowerCase().includes('sorcery'))		iScore = 8; // sorceries don't overlap
	if(psTypeLine.toLowerCase().includes('creature'))		iScore = 4; // creatures are lumped together
	if(psTypeLine.toLowerCase().includes('land'))			iScore = 100; // lands go last
	
	return iScore;
}
function fsToType(piGKScore) {
	switch(piGKScore) {
		case 1: return "Artifacts";
		case 5: return "Enchantments";
		case 6: return "Instants";
		case 7: return "Planeswalkers";
		case 8: return "Sorceries";
		case 4: return "Creatures";
		default: return "Lands";
	}
}

function fvSortDeck(psDeckElID, psSortBy) {
	peDeck = document.getElementById(psDeckElID);
	peDeck.style.opacity=0;
	setTimeout(function() {
		var nlTemp = peDeck.getElementsByClassName('cardindeck');
		
		eaSorted = [];
		for(i = 0; i < nlTemp.length; i++) {
			eaSorted.push(nlTemp[i]);
		}

		eaSorted.sort(function(pea, peb) {
		switch(psSortBy) {
			case 'types':
				iAScore = fiGKScore(pea.dataset.types);
				iBScore = fiGKScore(peb.dataset.types);
				
				if(iAScore == iBScore) return pea.dataset['name'].localeCompare(peb.dataset['name']);
				else return iAScore - iBScore;
				break;
			case 'name':
				return pea.dataset['name'].localeCompare(peb.dataset['name']);
				break;
			default: // CMC, future other data types
				if(pea.dataset[psSortBy] == peb.dataset[psSortBy]) return pea.dataset['name'].localeCompare(peb.dataset['name']);
				else return pea.dataset[psSortBy] - peb.dataset[psSortBy];
				break;
		}
		
			
		});
		
		while(peDeck.hasChildNodes()) {
			peDeck.removeChild(peDeck.lastChild);
		}
		
		if(psSortBy == 'types') {
			iCurrGKScore = fiGKScore(eaSorted[0].dataset['types']);
			eHeader = document.createElement("div");
			eHeader.classList.add("typetitle");
			eHeader.appendChild(document.createTextNode(fsToType(iCurrGKScore)));
			peDeck.appendChild(eHeader);
		}
		for(i = 0; i < eaSorted.length; i++) {
			if(psSortBy == 'types') {
				if(fiGKScore(eaSorted[i].dataset[psSortBy]) != iCurrGKScore) {
					iCurrGKScore = fiGKScore(eaSorted[i].dataset['types']);
					eHeader = document.createElement("div");
					eHeader.classList.add("typetitle");
					eHeader.appendChild(document.createTextNode(fsToType(iCurrGKScore)));
					peDeck.appendChild(eHeader);
				}
			}
			peDeck.appendChild(eaSorted[i]);
		}
	}, 300);
	setTimeout(function() {	peDeck.style.opacity=1; }, 500);
}

</script>
<!-- these need to be added to universal css -->
<style>
.indivDeck {
	transition: opacity 0.30s;
	-o-transition: opacity 0.30s;
	-moz-transition: opacity 0.30s;
	-webkit-transition: opacity 0.30s;
}
.decktitle {
	font-size: x-large;
	font-weight: bold;
}
.buildertitle {
	font-weight: bold;
}
.mainsidetitle {
	font-size: larger;
	font-weight: bold;
}
.typetitle {
	font-size: large;
	font-weight: bold;
	color: darkgray;
}
</style>
</head>
<body>
<?php include ABSPATH . '/navi.php';  ?>
	<div id="wrapper">
	<p></p>
	<?php 
	if(isset($sDeckDiv)) {
		print $sDeckDiv;
	}
	else {
		print "<p>Need a DeckID</p>";
	}
	
	include ABSPATH . '/footinclude.php'; ?>
	</div>
</body>
</html>
