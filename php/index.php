<?php
/** 
 * Radio-API
 * https://github.com/KIMB-technologies/Radio-API
 * 
 * (c) 2019 - 2020 KIMB-technologies 
 * https://github.com/KIMB-technologies/
 * 
 * released under the terms of GNU Public License Version 3
 * https://www.gnu.org/licenses/gpl-3.0.txt
 */
define('HAMA-Radio', 'Radio');
error_reporting( !empty($_ENV['DEV']) && $_ENV['DEV'] == 'dev' ? E_ALL : 0 );

/**
 * Loading
 */
require_once( __DIR__ . '/classes/autoload.php' );
Config::checkAccess( !empty($_GET['mac']) && Helper::checkValue( $_GET['mac'], Id::MAC_PREG ) ? $_GET['mac'] : null );

/**
 * Radio Server Test Requests
 */
$uri = !empty( $_GET['uri'] ) ? $_GET['uri'] : 'none';
// Login
if( $uri == '/setupapp/hama/asp/BrowseXML/loginXML.asp' && !isset( $_GET['mac'] ) ){
	Output::sendAnswer('<EncryptedToken>3a3f5ac48a1dab4e</EncryptedToken>');
	die(); //will never be reached
}

/**
 * Radio ID/ Mac Login
 */
try{
	if( empty($_GET['mac']) ){
		throw new Exception();
	}
	$radioid = new Id($_GET['mac']);
}
catch(Exception $e){
	if(!isset($_GET['yesStay'])){ // redirect user to gui
		header('Location:'. Config::DOMAIN .'gui/?redirFromIndex');
		http_response_code(303);
		die();
	}
	Output::sendAnswer('<Error>No MAC!</Error>');
	die(); //will never be reached
}
$out = new Output();
$data = new Data($radioid->getId());
$unread = new UnRead($radioid->getId());

/**
 * Page Handling
 */
//Normal Pages
if( isset( $_GET['sSearchtype'] ) && $_GET['sSearchtype'] == 3 ){ // only one station (play this)
	$out->prevUrl(Config::DOMAIN . '?go=inital');
	$id = $_GET['Search'];
	if( is_numeric( $id ) && preg_replace('/[^0-9]/','', $id ) === $id ){
		$sta = $data->getById( $id );
		if( $sta !== array() ){
			$out->addStation(
				$id,
				$sta['name'],
				$data->getStationURL($id, $radioid->getMac()),
				false,
				isset($sta['desc']) ? $sta['desc'] : '',
				isset($sta['logo']) ? $sta['logo'] : ''
			);
			$out->prevUrl(Config::DOMAIN . 'cat?cid=' . $sta['cid'] );
		}
	}
}
else if( isset( $_GET['sSearchtype'] ) && $_GET['sSearchtype'] == 5 ){ // only one episode (play this)
	$out->prevUrl(Config::DOMAIN . 'cat?cid=3');
	$id = $_GET['Search'];
	$parts = array();
	if( preg_match('/^(\d+)X(\d+)$/', $id, $parts ) === 1 ){
		$ed = PodcastLoader::getEpisodeData( $parts[1], $parts[2], $data );
		if( $ed != array() ){
			$unread->openItem($parts[1], $ed['episode']['url']);
			if($ed['proxy']){
				$url = Config::DOMAIN . 'stream.php?id=' . $parts[1] . '&eid=' . $parts[2] . '&mac=' . $radioid->getMac();
			}
			else if($ed['finalurl']){
				$url = Helper::getFinalUrl($ed['episode']['url']);
			}
			else{
				$url = $ed['episode']['url'];
			}
			$out->addEpisode(
				$parts[1],
				$parts[2],
				$ed['title'],
				$ed['episode']['title'],
				$url,
				$ed['episode']['desc'],
				$ed['logo']
			);
			$out->prevUrl(Config::DOMAIN . 'cat?cid=3&id=' . $parts[1]);
		}
	}
}
else if( $uri == '/cat' && !empty( $_GET['cid'] )  ){ // list of stations by catergory
	$out->prevUrl(Config::DOMAIN . '?go=inital');

	$cid = $_GET['cid'];
	if( is_numeric( $cid ) && in_array( $cid, array_keys($data->getCategories()) ) ){
		if( $cid == 3 && isset( $_GET['id'] ) && preg_replace('/[^0-9]/','', $_GET['id']  ) === $_GET['id'] ){
			$id = $_GET['id'];
			$pd = PodcastLoader::getPodcastDataById( $id, $data );
			$proxy = !empty($data->getById( $id )['proxy']);
			$unread->searchItem($id);
			foreach( $pd['episodes'] as $eid => $e ){
				$out->addEpisode(
					$id,
					$eid,
					$pd['title'],
					$unread->knownItem($id, $e['url']) . $e['title'],
					$proxy ? Config::DOMAIN . 'stream.php?id=' . $id . '&eid=' . $eid . '&mac=' . $radioid->getMac() : $e['url'],
					$e['desc'],
					$pd['logo']
				);
			}
			$out->prevUrl(Config::DOMAIN . 'cat?cid=3');
		}
		else{
			foreach( $data->getListOfItems( $cid ) as $id => $item ){
				if( $cid == 3){
					$out->addPodcast(
						$id,
						$item['name'],
						Config::DOMAIN . 'cat?cid=' . $cid . '&id=' . $id
					);
				}
				else{
					$out->addStation(
						$id,
						$item['name'],
						$data->getStationURL($id, $radioid->getMac()),
						true
					);
				}
			}
		}
	}
}
else{ // list of categories (startpage)
	foreach( $data->getCategories() as $cid => $name ){
		$out->addDir( $name, Config::DOMAIN . 'cat?cid=' . $cid );
	}
	// add code (for gui)
	$out->addDir( 'GUI-Code: ' . $radioid->getCode(), Config::DOMAIN . '?go=inital' );

	// Log unknown
	if( $uri != '/setupapp/hama/asp/BrowseXML/loginXML.asp' ){
		file_put_contents( __DIR__ . '/data/log.txt', json_encode( $_GET ) . PHP_EOL, FILE_APPEND );
	}
}
?>
