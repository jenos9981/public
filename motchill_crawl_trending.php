<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_REQUEST['url']) || trim($_REQUEST['url']) == '') die();


include('simple_html_dom.php');


$url = urldecode($_REQUEST['url']);
$objURL = parse_url($url);
$MOTCHILL_DOMAIN = $objURL['scheme'] . '://' . $objURL['host'];


/**================= MAIN ====================*/
$html = str_get_html(fetch($MOTCHILL_DOMAIN));
$a_tag_elements = $html->find('#film_hot .item a');

$movies = [];
foreach ($a_tag_elements as $key => $element) {
    $href = $element->getAttribute('href');
    $name = $element->getAttribute('title');
    $movie_id =  extractMovieId($MOTCHILL_DOMAIN . $href);

    if ($movie_id) {
        array_push($movies, [
            'id' => $movie_id,
            'name' => $name,
        ]);
    }
}


sendData(true, 'OK', $movies);

/**=====================================*/
function fetch($url)
{
    $agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, $agent);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_exec($curl);
    $ftp_result = curl_exec($curl);
    curl_close($curl);
    return $ftp_result;
}

function sendData($status, $message, $movie = [])
{
    header('content-type: application/json');
    echo json_encode(['status' => $status, 'message' => $message, 'movie' => $movie], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    die();
}


function extractMovieId($fullURL)
{
    $objURL = parse_url($fullURL);
    preg_match('/[0-9]+\.html$/', $objURL['path'], $matches);
    if (count($matches) <= 0) return false;

    return str_replace('.html', '', $matches[0]);
}
