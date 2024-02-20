<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_REQUEST['url']) || trim($_REQUEST['url']) == '') die();


include('simple_html_dom.php');
$url = urldecode($_REQUEST['url']);
$defaultMovie = [
    "motchill_id" => 0,
    "actors" => [],
    "content" => "",
    "description" => "",
    "countries" => "",
    "categories" => [],
    "label" => "",
    "lang" => "",
    "name" => "",
    "next_ep" => "",
    "playLink" => "",
    "poster" => "",
    "real_name" => "",
    "status" => "",
    "tags" => [],
    "time" => "",
    "total_ep" => "",
    "year" => "",
    "episodes_checksum" => "",
    "episodes" => []
];

$objURL = parse_url($url);
$MOTCHILL_DOMAIN = $objURL['scheme'] . '://' . $objURL['host'];


// Extract movie id
preg_match('/[0-9]+\.html$/', $objURL['path'], $matches);
if (count($matches) <= 0) {
    sendData(false, 'Maybe invalid url');
};


$movie = $defaultMovie;
$movie['motchill_id'] = str_replace('.html', '', $matches[0]);

/** Crawl info */
$html = str_get_html(fetch($url));
$movie['name'] = trim($html->find('#page-info h1 .title', 0)->text());
$movie['real_name'] = trim($html->find('#page-info h2 .real-name', 0)->text());

$content = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/si", '<$1$2>', $html->find('#info-film > div', 0)->__toString());
$content = str_replace(['<div>', '</div>'], '', $content);
$content = trim(substr($content, 0, strpos($content, '<p>&nbsp;</p>')));

$movie['content'] = '<h2>Nội dung phim:</h2>' . preg_replace("/\<b\>(.*)\<\/b\>/", "Bộ phim <strong>${movie['name']}</strong> (<em>${movie['real_name']}</em>) ", $content);
$movie['description'] = subWords(strip_tags($content), 40) . '...';
$movie['poster'] = $html->find("#page-info > div.blockbody > div.info > div.poster > a > img", 0)->getAttribute("src");
$movie['playLink'] = $MOTCHILL_DOMAIN . $html->find("#page-info > div.blockbody > div.info > div.poster .btn-stream-link", 0)->getAttribute("href");


$dtInfo = $html->find(".info .dinfo dl dt");
$ddInfo = $html->find(".info .dinfo dl dd");

foreach ($dtInfo as $key => $element) {
    $infoKey = trim($element->text());

    if ($infoKey == "Trạng thái:") $infoKey = "label";
    if ($infoKey == "Sắp chiếu:") $infoKey = "next_ep";
    if ($infoKey == "Thời lượng:") $infoKey = "time";
    if ($infoKey == "Số tập:") $infoKey = "total_ep";
    if ($infoKey == "Tình trạng:") $infoKey = "status";
    if ($infoKey == "Ngôn ngữ:") $infoKey = "lang";
    if ($infoKey == "Năm sản xuất:") $infoKey = "year";
    if ($infoKey == "Thể loại:") $infoKey = "categories";
    if ($infoKey == "Quốc gia:") $infoKey = "countries";
    if ($infoKey == "Diễn viên:") $infoKey = "actors";
    if ($infoKey == "Đạo diễn:") $infoKey = "directors";


    $xValue = trim($ddInfo[$key]->text()) == "Phụ đề Việt" ? "Vietsub" : trim($ddInfo[$key]->text());

    if ($infoKey === 'actors' || $infoKey === 'categories') $xValue = array_map(fn ($str) => trim($str), explode(',', $xValue));

    $movie[$infoKey] = $xValue;
}


//Remake status
if (strtolower($movie['label']) == 'trailer') {
    $movie['status'] = 'TRAILER';
} else if (strpos(strtolower($movie['status']), 'hoàn tất')) {
    $movie['status'] = 'COMPLETED';
} else {
    $movie['status'] = 'ONGOING';
}


$movie['tags'] = array_merge([$movie['name'], $movie['real_name'], "Phim " . $movie['countries']], $movie['actors']);



/** Crawl episode */
$episodeLinks = [];
$episodePage = str_get_html(fetch($movie['playLink']));
foreach ($episodePage->find(".episodes .list-episode a") as $element) {
    array_push($episodeLinks, [
        'name' => trim($element->text()),
        'link' => $MOTCHILL_DOMAIN . $element->getAttribute('href')
    ]);
}

foreach ($episodeLinks as $epLink) {
    $epPage = str_get_html(fetch($epLink['link']));

    //Has mutiple links
    if ($epPage->find('#server-backup li')) {
        $mLinks = [];

        foreach ($epPage->find('#server-backup li') as $linkElement) {
            array_push($mLinks,  [
                'name' => trim($linkElement->find('span', 0)->text()),
                'dataLink' => $linkElement->getAttribute('data-link')
            ]);
        }

        array_push($movie['episodes'], ['name' => $epLink['name'], 'url' => parse_url($epLink['link'])['path'], 'links' => $mLinks]);
    } else {
        preg_match_all('/var dataLink="(.*)"/', $epPage->find('body', 0)->__toString(), $matches);
        if (isset($matches[1][0])) {
            array_push($movie['episodes'], ['name' => $epLink['name'], 'url' => parse_url($epLink['link'])['path'], 'links' => [[
                'name' => $epLink['name'],
                'dataLink' => $matches[1][0]
            ]]]);
        }
    }
}

//Episode checksum
$movie['episodes_checksum'] = md5(json_encode($movie['episodes']));
//Movie checksum
$movie['movie_checksum'] = md5(json_encode($movie));


//Remake play link
$movie['playLink'] = parse_url($movie['playLink'])['path'];



sendData(true, 'OK', $movie);

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


function subWords($str, $len = 50)
{
    $str = explode(' ', $str);
    $r = '';
    for ($i = 0; $i < count($str); $i++) {
        if ($i < $len) {
            $r .= ($str[$i] . ' ');
        }
    }
    return trim($r);
}


function sendData($status, $message, $movie = [])
{
    header('content-type: application/json');
    echo json_encode(['status' => $status, 'message' => $message, 'movie' => $movie], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    die();
}
