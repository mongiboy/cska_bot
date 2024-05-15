<?
$arRuMonth = array (
    'янв' => '01',
    'фев' => '02',
    'мар' => '03',
    'апр' => '04',
    'май' => '05',
    'июн' => '06',
    'июл' => '07',
    'авг' => '08',
    'сен' => '09',
    'окт' => '10',
    'ноя' => '11',
    'дек' => '12',
);

$page = file_get_contents('https://pfc-cska.com/matches/spisok-matchej/');

// *Находим блок с показом следующего матча
preg_match('#<div class="list-matches__item(.*?)<\/a>#s', $page, $nextMatch);
$nextMatch = $nextMatch[0];

// * Создаём объект с датой матча
preg_match('#list-matches__date.*?>?(\d\d).(.{6}).*?(\d\d).*?(\d\d)#s', $nextMatch, $date);
$matchDate = new DateTime();
$matchDate->setDate(date('Y'), $arRuMonth[$date[2]], $date[1]);
$matchDate->setTime($date[3], $date[4]);

// * Находим название матча
preg_match('#matches__name">(.*?),(.*?)<\/#s', $nextMatch, $name);
$matchName = trim(str_replace('  ', ' ', $name[1])) . ', ' . trim(str_replace('  ', ' ', $name[2]));

// * Определяем дома или на выезде
preg_match('#list-matches__item--(.*?)"#s', $nextMatch, $place);
$matchPlace = $place[1];

// * Название команды соперника
preg_match_all('#__team-name">(.*?)<\/#s', $nextMatch, $team);
$matchTeam = trim($team[1][0]);

// * Путь до логотипа соперника
preg_match_all('#img src="(.*?)"#s', $nextMatch, $logo);
$logo = $logo[1][0];
$ourLogo = 'img/pfk_cska.png';

if($matchPlace == 'home') {
    $arTeams = ['ПФК ЦСКА', $matchTeam];
    $arLogo = [$ourLogo, $logo];
} else {
    $arTeams = [$matchTeam, 'ПФК ЦСКА'];
    $arLogo = [$logo, $ourLogo];
}
$url = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
// * Путь до картинки на сайте
$imageURL = $url . '/img/' . strtolower(translit($arTeams[0]) . '-' . translit($arTeams[1]) . '.jpg');

// * Формируем сообщение для телеги
$text =  $date[3] . ':' . $date[4] . PHP_EOL . $arTeams[0] . ' - ' . $arTeams[1] . '.' . PHP_EOL . $matchName;

$trigger = whenGame($matchDate);

switch($trigger){
    case 'timeToImage';
        addImage($arLogo, $arTeams);
        break;
    case 'tomorrowGame';
    case 'soonGame';
        sendMessage($trigger, $text, $imageURL);
        break;
}
function whenGame(object $date){ // * С помощью функции определяем время для события
    $now = new DateTime();
    $diff = $date->format('d') - $now->format('d');

    if($diff == 1 && $now->format('H') == 18){
        return 'timeToImage'; // * Время для создания картинки
    }

    if($diff == 1 && $now->format('H') == 19){
        return 'tomorrowGame'; // * Время для отправки сообщения за день
    }

    if($diff == 0 && ($date->format('H') - $now->format('H') == 1)){
        return 'soonGame'; // * Время для отправки сообщения за час
    }
    return false;
}
function addImage($arLogo, $arTeams){
    $arPath = [] ;
    foreach($arLogo as $logo){
        if($logo != 'img/pfk_cska.png'){
            $pathToLogo = $logo;
            $path_parts = pathinfo($pathToLogo);
            $pathToImage = 'img/' . $path_parts['basename'];
            copy($pathToLogo, $pathToImage);
        } else {
            $pathToImage = 'img/pfk_cska.png';
        }

        $arPath[] = $pathToImage;
    }

    $logoA = new Imagick(realpath($arPath[0]));
    $logoB = new Imagick(realpath($arPath[1]));
    $bg = new Imagick(realpath('img/bg-400.jpg'));

    $image = clone($bg);
    $image->compositeImage($bg, Imagick::COMPOSITE_IN, 0, 0);
    $image->compositeImage($logoA, Imagick::COMPOSITE_DEFAULT, 40, 50);
    $image->compositeImage($logoB, Imagick::COMPOSITE_DEFAULT, 260, 50);

    //header("Content-Type: image/png");
    $image->writeImage($_SERVER['DOCUMENT_ROOT'] . '/img/' . strtolower(translit($arTeams[0]) . '-' . translit($arTeams[1]) . '.jpg'));
    $image->destroy();
    $logoA->destroy();
    $logoB->destroy();
    $bg->destroy();
}
function sendMessage($when, $text, $photo){
    // * Параметры для Телеги. Токен и id чата хранятся в отдельном файле config.php
    $config = __DIR__ . '/config.php';

    if(file_exists($config)){
        include $config;
    } else {
        return false;
    }
    /** @var array $telegram */
    $token = $telegram['token'];
    $chatId = $telegram['chatId'];
    $myChatId = $telegram['myChatId'];

    $message = '';

    if($when == 'tomorrowGame'){
        $message = 'Завтра в ' . $text;
    } elseif($when == 'soonGame'){
        $message = 'Скоро игра: ' . $text;
    }

    $response = array(
        'chat_id' => $myChatId,
        'photo' => $photo,
        'caption' => $message,
    );

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendPhoto');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $test = curl_exec($ch);
    curl_close($ch);
    return true;
}
function translit(string $value): string
{
    $converter = array(
        'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
        'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
        'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
        'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
        'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
        'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
        'э' => 'e',    'ю' => 'yu',   'я' => 'ya',

        'А' => 'A',    'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
        'Е' => 'E',    'Ё' => 'E',    'Ж' => 'Zh',   'З' => 'Z',    'И' => 'I',
        'Й' => 'Y',    'К' => 'K',    'Л' => 'L',    'М' => 'M',    'Н' => 'N',
        'О' => 'O',    'П' => 'P',    'Р' => 'R',    'С' => 'S',    'Т' => 'T',
        'У' => 'U',    'Ф' => 'F',    'Х' => 'H',    'Ц' => 'C',    'Ч' => 'Ch',
        'Ш' => 'Sh',   'Щ' => 'Sch',  'Ь' => '',     'Ы' => 'Y',    'Ъ' => '',
        'Э' => 'E',    'Ю' => 'Yu',   'Я' => 'Ya',
        ' ' => '_',
    );

    return strtr($value, $converter);
}
?>
<h1>CSKA Moscow</h1>
<img src="<?=$imageURL?>" alt="">
