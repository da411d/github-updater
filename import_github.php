<?php
error_reporting(0); // Повідомлення про помилку не треба :) 

// https://github.com/settings/tokens
define("API_KEY", "your-github-token"); // Наш ключик
define("REPO", "author/repo"); // Автор/репозиторій
define("BRANCH", "master"); // Гілка
define("DESTINATION", dirname(__FILE__)); // Пункт призначення
define("STATUS", __FILE__.".status");




if(count($_POST) == 0){
	file_put_contents(STATUS, "");
	echo '
<!DOCTYPE HTML>
<html>
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="HandheldFriendly" content="True" />
	<meta name="theme-color" content="#ffffff" />
	<meta name="msapplication-navbutton-color" content="#ffffff" />
	<meta name="apple-mobile-web-app-status-bar-style" content="#ffffff" />
	<title>Github updater</title>
	<style>
		*{
			box-sizing: border-box;
			text-align: center;
		}
		body{
			font-family: "Tahoma";
		}
		.main{
			position: absolute;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			
			width: 500px;
			max-width: 100%;
			padding: 32px 64px;
			
			color: #242424;
			border-radius: 16px;
			box-shadow: 0 0 48px #d0d5d6;
		}
		
		@media(max-width: 500px){
			.main{
				padding: 16px !important;
				border-radius: 0 !important;
				box-shadow: none !important;
			}
		}
		
		#title{
			font-size: 18px;
		}
		#timer{
			margin: 16px;
			color: #1799fb!important;
			font-size: 48px;
		}
		#status{
			font-size: 20px;
		}
	</style>
	<script>
		function qq(t){return document.querySelector(t)}
		function sleep(t){return new Promise((a)=>setTimeout(a, t))}
		
		var APP = {
			started: false,
			
			start: async () => {
				var post  = new FormData();
				post.append("act", "start");
				fetch("", {
					credentials: "same-origin",
					method: "POST",
					body: post
				}).then(APP.stop).catch(APP.stop);
				
				APP.started = new Date()*1;
				
				APP.status();
				APP.timer();
			},
			
			stop: () => {
				APP.started = false;
			},
			
			status: async () => {
				while(APP.started){
					var data = await fetch(window.location.pathname+".status").then(data=>data.text());
					
					var h = data.length;
					var elem = qq("#status");
					
					if(elem.getAttribute("data-length") != h){
						elem.setAttribute("data-length", h);
						qq("#status").innerHTML = data;
					}
					
					await sleep(300);
				}
			},
			
			timer: async () => {
				while(APP.started){
					var diff = ~~((new Date() - APP.started) / 1000);
					
					var ss = diff % 60;
					if(ss < 10)ss = "0"+ss
					
					var mm = ~~(diff / 60);
					if(mm < 10)mm = "0"+mm
					
					qq("#timer").innerText = mm + ":" + ss;
					
					await sleep(300);
				}
			}
		}
	</script>
</head>

<body onload="APP.start()">
	<div class="main">
		<p id="title">Идет процесс импорта из репозитория </p>
		<p id="timer">00:00</p>
		<p id="status">Загрузка...</p>
	</div>
</body>

<!--
	Code written by David Manzhula @da411d
	https://daki.me/
-->
</html>
';
	die();
}


if($_POST["act"] == "start"){
	if($_SERVER["REMOTE_ADDR"] == "127.0.0.1"){
		file_put_contents(STATUS, "Ошибка!");
		die();
	}
	
	// Скачуєм файл
	file_put_contents(STATUS, "Шаг 1: Качаем архив с ветки " . BRANCH);
	$url = "https://github.com/" . REPO . "/archive/" . BRANCH . ".zip";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,'https://github.com');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, API_KEY);

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

	$result = curl_exec($ch);
	curl_close($ch);

	//Зберігаєм
	$zipname = md5($result) . ".zip";
	file_put_contents($zipname, $result);

	// Відкриваєм архів
	$zip = new ZipArchive;
	$res = $zip->open($zipname);
	if ($res === TRUE){
		//Чистим місце призначення
		file_put_contents(STATUS, "Шаг 2: Чистим папку"); sleep(1);
		$it = new RecursiveDirectoryIterator(DESTINATION, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				//Якщо це цей файл
				if(
					strpos($file->getRealPath(), "import_github.php")
				)continue; // Не видаляєм його, бо це наш файл
				
				unlink($file->getRealPath());
			}
		}
		rmdir($tmpdest);
		
		
		
		file_put_contents(STATUS, "Шаг 3: Распаковываем в временную папку"); sleep(1);
		$tmpdest = DESTINATION . "/tmp-" . rand(); //Тимчасова папка
		$folder = explode("/", REPO)[1] . "-" . BRANCH; //Назва підпапки - репозиторій-гілка
		
		//Розпаковуєм в тимчасову папку
		$zip->extractTo($tmpdest);
		$zip->close();
		
		//Переміщаєм з тимчасової в нормальну
		file_put_contents(STATUS, "Шаг 4: Перемещаем"); sleep(1);
		$files = scandir($tmpdest."/".$folder);
		foreach ($files as $file){
			if(in_array($file, [".",".."]))continue;
			$orig = $tmpdest."/".$folder."/".$file;
			$to = DESTINATION."/".$file;
			if(file_exists($to))unlink($to);
			rename($orig, $to);
		}
		
		//Видаляєм тимчасову папку
		file_put_contents(STATUS, "Шаг 5: Удаляем временную папку"); sleep(1);
		$it = new RecursiveDirectoryIterator($tmpdest, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}
		rmdir($tmpdest);
		
		// Не робіть так! Мені заплатили багато грошей щоб я зробив це лайно!
		// file_put_contents(STATUS, "Шаг 6: Выставляем права"); sleep(1);
		// exec("find " . dirname(__FILE__) . " -type d -exec chmod 0777 {} +");
		// exec("find " . dirname(__FILE__) . " -type f -exec chmod 0777 {} +");
		
	} else {
		file_put_contents(STATUS, "Ошибка!");
	}
	// Видаляєм архів
	unlink($zipname);
	
	file_put_contents(STATUS, '<meta http-equiv="refresh" content="0; url=./">');
	
	header("Connection: close");
	ignore_user_abort(true);
	header("Content-Length: 0");
	flush();
	
	sleep(1);
	unlink(STATUS);
}


/*
	Code written by David Manzhula @da411d
	https://daki.me/
*/
?>
