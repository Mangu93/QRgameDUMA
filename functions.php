<?php


/* 

	MANUAL BÁSICO DE PHP PARA DISEÑAR ESTA APLICACIÓN 

//Las variables se instancian al crearlas.
//Ejemplos: 

//int

	$a=12;

//string

	$b="ola ke ase";

//boolean

	$c=false;

//Para imprimir algo se usa "echo"

Ejemplos:

	if (!$c){
		echo $b;
	}

//vaya, ese ejemplo era con un if por la cara, normalmente:

	echo $b;

//Las funciones NO tienen que especificar si devuelven algo o no
//Ejemplos:

	//un void

	function imprimeTime(){
		echo time();
	}

	//una funcion que devuelve un integer

	function multiplicaPorDos($numero){
		return 2*$numero;
	}

	//Podemos dar valores por defecto
	function multiplicaPorDos($numero=2){
		return 2*$numero;
	}
	//
*/

//Así se hace un IMPORT:

include("config.php");
include("ignore/functions.database.php");


function installTables(){
	
	//Crea las cuatro tablas en nuestra base de datos

	executeQuery("CREATE TABLE IF NOT EXISTS `players` (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
						`user` VARCHAR( 30 ) NOT NULL ,
						`phone` INT( 15 ) NOT NULL ,
						`twitter` BOOL NOT NULL ,
					
						UNIQUE (`phone`)) 
						ENGINE = MYISAM ;");
		
	executeQuery("CREATE TABLE IF NOT EXISTS `places` (
						`type` INT NOT NULL ,
						`id` INT NOT NULL AUTO_INCREMENT ,
						`name` VARCHAR( 35 ) NOT NULL,
						PRIMARY KEY ( `type` , `id`)) 
						ENGINE = MYISAM ;");
	executeQuery("CREATE TABLE IF NOT EXISTS `shoots` (
						`user` INT NOT NULL ,
						`code` VARCHAR( 32 ) NOT NULL ,
						`score` INT NOT NULL ,
						PRIMARY KEY ( `user` , `code` )) 
						ENGINE = MYISAM ;");
	executeQuery("CREATE TABLE IF NOT EXISTS `codes` (
						`type` INT NOT NULL ,
						`id` INT NOT NULL ,
						`code` VARCHAR( 32 ) NOT NULL ,
						`taken` BOOL NOT NULL DEFAULT '0',
						PRIMARY KEY ( `type` , `id`) ,
						UNIQUE (`code`)) 
						ENGINE = MYISAM ;");

}

function generateCode($from){
	//Genera códigos aleatorios que no estén en la tabla codes a partir de un dato
	$mysqli=conectaDB();

	do{
		$newCode=md5($from + rand());
		$result=$mysqli->query("SELECT * FROM `codes` WHERE `code` = '".$newCode."'");
	} while ($result->num_rows!=0);

	$mysqli->close();
	return $newCode;
}

function generateQR($code,$type=1,$whereAmI=""){
	include_once("phpqrcode/qrlib.php");
	switch ($type) {
		case 1:
			//DISPARO
			$data=$GLOBALS['gameurl']."/shoot/".$code;
			break;
		case 2:
			//REGISTRO
			$data=$GLOBALS['gameurl']."/register/setSession/".$code;
			break;
		
		default:
			$data=$code;
			break;
	}
	$filename = $whereAmI.'GeneratedQR/q'.md5($data).'.png';
    QRcode::png($data, $filename, 'H', 6, 2);
	return $filename;
}


function allocatePersonalCodes(){
	$mysqli = conectaDB();

	//Esto hay que arreglarlo
	for ($id=151; $id <= 220; $id++) { 
		$code=generateCode($id+time());
		$mysqli->query("INSERT INTO `codes` (`type` ,`id` ,`code`,`taken`) VALUES ('1', '".$id."', '".$code."', '0');");
	}
	$mysqli->close();
}
function allocateStandCodes(){
	$mysqli = conectaDB();
	for ($id=1; $id <= 50; $id++) { 
		$code=generateCode($id+time());
		$mysqli->query("INSERT INTO `codes` (`type` ,`id` ,`code`,`taken`) VALUES ('2', '".$id."', '".$code."', '0');");
	}
	$mysqli->close();
}
function allocateStadiumCodes(){
	$mysqli = conectaDB();
	for ($id=1; $id <= 50; $id++) { 
		$code=generateCode($id+time());
		$mysqli->query("INSERT INTO `codes` (`type` ,`id` ,`code`,`taken`) VALUES ('3', '".$id."', '".$code."', '0');");
	}
	$mysqli->close();
}

function allocateCodes(){
	allocatePersonalCodes();
	allocateStandCodes();
	allocateStadiumCodes();
}

function lookupCode($type,$id){
	$return=-1;
	$result=executeQuery("SELECT code FROM `codes` WHERE `type` =".$type." AND `id` =".$id." AND `taken` =0");
	if ($result->num_rows==0){
		switch ($type) {
			case 1:
				allocatePersonalCodes();
				break;
			case 2:
				allocateStandCodes();
				break;
			case 3:
				allocateStadiumCodes();
				break;
		}
		$result=executeQuery("SELECT code FROM `codes` WHERE `type` =".$type." AND `id` =".$id." AND `taken` =0");
	} 
		$object=$result->fetch_object();
		$return=$object->code;
	return $return;
}

function lookupCodeNoAlloc($type,$id){
		$result=executeQuery("SELECT code FROM `codes` WHERE `type` =".$type." AND `id` =".$id);
		$object=$result->fetch_object();
		$return=$object->code;
		return $return;
}

function getAllCodes(){
		$result=executeQuery("SELECT * FROM `codes`");
		while($object=$result->fetch_object()){
			$return[]=$object;
		}
		return $return;
}


function whoIs($code){
	$return=-1;
	$result=executeQuery("SELECT id FROM codes WHERE code='".$code."'");
	if ($result->num_rows==1){
		$object=$result->fetch_object();
		$return=$object->id;
	}
	return $return;
}

function setUserSession($code){
	$whois=whoIs($code);
	if($whois!=-1){
		session_start();
		$_SESSION['uid']=$whois;
	}
}

function controlUserSession($redir=true){
	session_start();
	if (!isset($_SESSION['uid'])){
		if ($redir) header("Location: ../");
	} 
	return $_SESSION['uid'];
}
function addPlayer($user,$phone,$twitter){
	$mysqli = conectaDB();
	if(!$mysqli->query("INSERT INTO `players` (`id` ,`user` ,`phone` ,`twitter`) VALUES (NULL , '".utf8_decode($user)."', '".$phone."', '".$twitter."');")){
		$id=-1;
		$code=-1;
	} else {
		$id=$mysqli->insert_id;
		$code=lookupCode(1,$id);
		setTaken(1,$id);
	}
	$mysqli->close();
	return $code;
}

function addPlace($name, $type){
	$mysqli = conectaDB();
	if(!$mysqli->query("INSERT INTO `places` (`id` ,`name`, `type`) VALUES (NULL , '".utf8_decode($name)."', '".$type."');")){
		$id=-1;
		$code=-1;
	} else {
		$id=$mysqli->insert_id;
		$code=lookupCode($type,$id);
		setTaken($type,$id);
	}
	$mysqli->close();
	return $code;
}


function setTaken($type,$id){
	executeQuery("UPDATE `codes` SET `taken` = '1' WHERE `codes`.`type` =".$type." AND `codes`.`id` =".$id.";");
}

function getPlayer($id){
	$return=-1;
	$result=executeQuery("SELECT user, phone, twitter FROM players WHERE id=".$id);
		if ($result->num_rows==1){
			$object=$result->fetch_object();
			$return= (object) array(	
						'id'		=> $id,
						'type' 		=> 1,
						'user' 		=> $object->user, 
						'phone'		=> $object->phone,
						'twitter'	=> $object->twitter,);
		}
	return $return;
}

function getPlace($id, $type){
$return=-1;
	$result=executeQuery("SELECT name FROM places WHERE id=".$id. " AND type=". $type);
		if ($result->num_rows==1){
			$object=$result->fetch_object();
			$return= (object) array('type' 		=> $type,
									'name' 		=> $object->name,);
		}
	return $return;
}

function whatAmIGonnaShoot($code){
	$return=-1;
	$result=executeQuery("SELECT type, id FROM codes WHERE code='".$code."'");
	if($result->num_rows==1){
		$object=$result->fetch_object();

	}
	return $return;
}
function shoot($uid,$code){
	/*
		Errores:	0: no hay error
					1: código escaneado incorrecto
					2: ya ha escaneado este código
					3: no puedes dispararte a ti mismo
	*/
	$error=0;	//Presuponemos que no habrá un error
	$execute=true;
	//Obtenemos info del código escaneado
	$result=executeQuery("SELECT type, id FROM codes WHERE code='".$code."'");

	if($result->num_rows==1){
		//Si hay un resultado en nuestra búsqueda, obtenemos los datos de ese objeto

		$object=$result->fetch_object();

		//Obtenemos la info adicional y asignamos una puntuación
		if($object->type==1){
			//Si type==1, es una persona
			if($object->id==$uid){
				$execute=false;
				$error=3;
			}
			$objectInfo=getPlayer($object->id);

			$score=75;
		} else {
			//Si type!=1, es un lugar
			$objectInfo=getPlace($object->id, $object->type);
			$score=($object->type==3?100:($object->type==2?25:($object->type==4?150:0)));
		}

			//Guardamos el disparo en la Base de Datos
		if($execute){
			if(!executeQuery("INSERT INTO `shoots` (`user` ,`code` ,`score`) VALUES ('".$uid."', '".$code."', '".$score."');")){
				//Si no se ejecuta correctamente el query, devolvemos el error 2 (porque no se puede introducir una nueva fila en la DB)
				$error=2;
			}	
		}
		

	} else {
		//Si no hay un registro en la base de datos con ese código, o el número es superior (brainfuck)
		$error=1;
	}
	

	return (object) array(	
					'error' => $error,
					'score' => $score, 	//esto está de ejemplo 
					'info' => $objectInfo, );	//id del lugar/persona donde se ha disparado
}

function calculateScoreOfUser($user) {
	$scores = executeQuery("SELECT score FROM shoots WHERE user='".$user."'");
	
	$totalScore = 0;

	while ($aScore=$scores->fetch_object()){
			$totalScore += $aScore->score;
	}

	return $totalScore;
}

function getAllUsers(){

	$result=executeQuery("SELECT * FROM players");

	while ($object=$result->fetch_object()) {
		$return[$object->id]=(object)  array(	'user' => $object->user, 
												'twitter' => $object->twitter,
												'phone' => $object->phone,
												'id' => $object->id,);
	}
	return $return;
}

function getAllPlaces(){

	$result=executeQuery("SELECT * FROM places");

	while ($object=$result->fetch_object()) {
		$return[]=(object)  array(	'name' => $object->name,
									'id' => $object->id,
									'type' => $object->type,);
	}
	return $return;
}

function getInfoOf($code){
	$return=-1;
	$result=executeQuery("SELECT id, type FROM codes WHERE code='".$code."'");
	$object=$result->fetch_object();
	if ($object->type==1){
		$return=getPlayer($object-> id);
	} else {
		$return=getPlace($object-> id, $object-> type);
	}
	return $return;
}

function getInfoOfShoots($uid){
	$shoots = executeQuery("SELECT code FROM shoots WHERE user='".$uid."'");
	while ($object=$shoots->fetch_object()){
			$return[]=getInfoOf($object->code);
	}
	return $return;
}
function scoreOfAll(){

	$players=getAllUsers();

	$result=executeQuery("SELECT user, sum(score) 'points' FROM shoots GROUP BY user ORDER BY sum(score) DESC");


 	if($result->num_rows>0){
		while ($object=$result->fetch_object()) {
			$scores[$object->user]=$object->points;
		}

		$i=1;

		foreach ($scores as $user => $score) {
			$return[]=(object) array(	
									'order' => $i++,
									'nick' => $players[$user]->user ,
									'twitter' => $players[$user]->twitter ,
									'score' => $score , );
			}
	}
	return $return;
}
function eraseQRs($path="../"){
	$dir=$path."GeneratedQR/";
	$directorio=opendir($dir); 
	while ($archivo = readdir($directorio)){
		if(($archivo!='index.php')&&($archivo!='eraseQR.php')&&$archivo!='.'&&$archivo!='..'){
			unlink($dir.$archivo);
		}
	}
}

function showPerson($person){
	if($person->twitter==1){	
		$show="<a href='http://www.twitter.com/".$person->user."'>@". $person->user . "</a>";
	} else {
		$show=$person->user;
	}
	return utf8_encode($show);
}

function showPlace($place){
	return utf8_encode($place->name);
}
/* AUTENTICACIÓN ADMINISTRADOR */

function setAdmin($user,$pass){
	if($user==$GLOBALS['admin_user']&&$pass==$GLOBALS['admin_pass']){
		session_start();
		$_SESSION['admin']=true;
		header("Location:../");
	} 
} 

function isAdmin(){
	//Busca si el usuario es administrador, y si no redirecciona a la web de login 	
	session_start();
	if((!isset($_SESSION['admin']))||($_SESSION['admin']!=true)){
		header("Location: ".$GLOBALS['gameurl']."/admin/login");
	}
}


	//print_r(shoot(1,'f876e5650f808e2a6add0a03cfb7a23d'));
	//echo addPlace(utf8_decode("Conserjería"),2) . "\n". addPlayer('tutida','666',true);
	//generateQR("http://qea.me/shoot/". addPlace(utf8_decode("Conserjería"),2));
	//generateQR("http://qea.me");
	//scoreOfAll();
	//installTables();


?>