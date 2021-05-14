<?php
error_reporting(0); // Повідомлення про помилку не треба :)

// Код безпеки. Можна любі букви-цифри, бажано більше восьми і менше 128
// Залишити false якщо не потрібен
// Код передається в URL після #:
// https://example.com/path/to/import_github.php#SOME-RANDOM-STRING-HERE-1234567890
define("SECURITY", "SOME-RANDOM-STRING-HERE-1234567890");

// Токен отримати можна тут: https://github.com/settings/tokens
define("API_TOKEN", "1234567890abcdef1234567890abcdef12345678");

// Репозиторій в форматі author/repoName
define("REPO", "author/repoName");

// Гілка
define("BRANCH", "master");

// Папка, куда будем розпаковувати. Зазвичай - папка де ми зараз
define("DESTINATION", dirname(__FILE__));

// LOCK-файл. Не трогати
define("LOCKFILE", dirname(__FILE__) . ".lock");


/* ВСЬО ЩО ДАЛЬШЕ - НЕ ТРОГАТИ */
if ($_POST["act"] == "start") {
  // Перевірка ключа безпеки
  $securityKeySeed = $_POST["seed"] ?? "";
  $securityKeyKey = "_" . sha1($securityKeySeed . SECURITY);
  $securityPass = $_POST[$securityKeyKey] ?? "";
  if (SECURITY !== false && (empty($securityPass) || $securityPass !== SECURITY)) {
    die("Invalid security key");
  }
  
  // Щоб випадково не затерти зміни, зроблені на локалці
  if ($_SERVER["REMOTE_ADDR"] == "127.0.0.1") {
    die("Denied for localhost");
  }
  
  $lockfile = fopen(LOCKFILE, "w+");
  if (!flock($lockfile, LOCK_EX | LOCK_NB)) {
    die("Unable to obtain lock, the previous process is still going on");
  }
  
  $mainDestination = rtrim(DESTINATION, "/\\");
  $backupDestination = $mainDestination . ".bak/bak-" . date("Y-m-d_H-i-s");
  $unzipDestination = $mainDestination . ".unzip-" . time();
  $tempDestination = $mainDestination . ".tmp-" . time();
  $targetSubfolder = explode("/", REPO)[1] . "-" . strtolower(BRANCH); //Назва підпапки: repoName-branch
  $downloadZipFilename = $mainDestination . ".zip-" . time() . ".zip";
  
  // Скачуєм файл
  $url = "https://github.com/" . REPO . "/archive/" . BRANCH . ".zip";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_USERPWD, API_TOKEN);
  
  $result = curl_exec($ch);
  file_put_contents($downloadZipFilename, $result);
  curl_close($ch);
  
  // Відкриваєм архів
  $zip = new ZipArchive;
  $isOpen = $zip->open($downloadZipFilename);
  if ($isOpen === true) {
    $zip->extractTo($unzipDestination);
    $zip->close();
    
    $backupDestinationParent = dirname($backupDestination);
    if (!file_exists($backupDestinationParent)) {
      mkdir($backupDestinationParent);
    }
    // Робим рокіровку
    rename($unzipDestination . "/" . $targetSubfolder, $tempDestination);
    rename($mainDestination, $backupDestination);
    rename($tempDestination, $mainDestination);
    
    // Видаляєм тимчасову папку
    rmdir($unzipDestination);
    
    echo "Success";
    
  } else {
    echo "Unable to unzip";
  }
  
  // Видаляєм архів
  unlink($downloadZipFilename);
  
  flock($lockfile, LOCK_UN);
  fclose($lockfile);
  unlink(LOCKFILE);
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  die();
}

$securityKeySeed = rand();
$securityKeyKey = "_" . sha1($securityKeySeed . SECURITY);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>

<head>
  <meta charset="utf-8"/>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="theme-color" content="#ffffff">
  <meta name="msapplication-navbutton-color" content="#ffffff">
  <meta name="apple-mobile-web-app-status-bar-style" content="#ffffff">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="HandheldFriendly" content="True">
  <meta charset="UTF-8">
  <title>Github Updater</title>
  <style>
    * {
      box-sizing: border-box;
    }
    
    html {
      font-size: 20px;
    }
    
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 16px;
      font-family: system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans","Liberation Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";
    }
    
    p {
      margin: 0;
    }
    
    p:not(:last-child) {
      margin-bottom: 1em;
    }
    
    .popup {
      width: 500px;
      padding: 2rem 3rem;
      text-align: center;
      border-radius: 1rem;
      box-shadow: 0 0 2rem rgba(0, 0, 0, 0.3);
      color: #242424;
    }
    
    .js-info {
      color: #1799fb;
      font-size: 1.5rem;
    }
    
    .loader {
      display: block;
      text-align: center;
      height: 2rem;
    }
    
    .loader:after {
      content: '';
      display: inline-block;
      width: 2rem;
      height: 2rem;
      border: 0.2rem solid transparent;
      border-bottom-color: #1799fb;
      border-left-color: #1799fb;
      border-radius: 2rem;
      animation: spin 0.5s infinite linear;
    }
    
    @keyframes spin {
      from {
        transform: rotate(0deg);
      }
      to {
        transform: rotate(360deg);
      }
    }
  </style>
</head>

<body>
<div class="popup">
  <p class="title">Идет процесс импорта из репозитория</p>
  <p class="js-info"></p>
</div>

<script>
  (async () => {
    const info = document.querySelector(".js-info");
    info.classList.add("loader");
    
    const formData = new FormData();
    formData.append("act", "start");
    formData.append("seed", "<?php echo $securityKeySeed; ?>");
    formData.append("<?php echo $securityKeyKey; ?>", location.hash.substr(1));
    history.replaceState({}, null, "#");
    
    const resultText = await fetch("?", {
      method: "POST",
      body: formData,
    }).then(response => response.text());
    
    info.innerText = resultText;
    info.classList.remove("loader");
    
    if (resultText.toLowerCase() === "success") {
      setTimeout(() => location.assign("/"), 3000);
    }
  })();
</script>
</body>

<!--
  Code written by David Manzhula @da411d
  https://daki.me/
-->
</html>
