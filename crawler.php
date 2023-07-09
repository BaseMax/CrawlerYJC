<?php
// Functions
function sendGet(string $link) : ?string
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $link);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)");
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

function checkCategory(string $category_link) : ?array
{
    $category_data = sendGet($category_link);
    if ($category_data === null) return null;

    // Detect next page
    $regex = '/<a href=\"(\/fa\/section\/ajax\/([0-9]+)\/([0-9]+))\" class=\"next\">/i';
    preg_match($regex, $category_data, $match);
    $next_page = null;
    if (isset($match[1], $match[2], $match[3])) $next_page = "https://www.yjc.ir" . $match[1];
    // var_dump($next_page);

    // Detect IDs
    $regex = '/href=\"\/fa\/news\/([0-9]+)/i';
    // var_dump($regex);
    preg_match_all($regex, $category_data, $matches);
    // var_dump($matches);
    if ($matches && isset($matches[1])) {
        $ids = $matches[1];
        return [
            "ids" => $ids,
            "next_page" => $next_page,
        ];
    }

    return null;
}

function parseCategory(string $category_link): array
{
    $category_page_ids = checkCategory($category_link);

    if ($category_page_ids === null) {
        return [];
    }

    $ids = (array) $category_page_ids["ids"];
    print count($ids) . "\n";

    while ($category_page_ids["next_page"] !== null) {
        print "next page: " . $category_page_ids["next_page"] . ": ";
        $category_page_ids = checkCategory($category_page_ids["next_page"]);
        $next_ids = $category_page_ids["ids"];
        print count($next_ids) . " <-> ";

        if (is_array($next_ids) && !empty($next_ids)) {
            foreach ($next_ids as $next_id) $ids[] = $next_id;
        }
        print count($ids) . "\n";
    }

    // Filter and remove duplicates
    $ids = array_unique($ids);
    $ids = array_filter($ids, function ($id) {
        return is_numeric($id);
    });
    $ids = array_values($ids);

    return $ids;
}

function link2name(string $link) : string
{
    return str_replace("https://www.yjc.ir/fa/", "", $link);
}

$categories = [
    "https://www.yjc.ir/fa/political",
    "https://www.yjc.ir/fa/world",
    "https://www.yjc.ir/fa/sports",
    "https://www.yjc.ir/fa/social",
    "https://www.yjc.ir/fa/comercial",
    "https://www.yjc.ir/fa/art",
    "https://www.yjc.ir/fa/science",
    "https://www.yjc.ir/fa/multimedia",
    "https://www.yjc.ir/fa/photo",
    "https://www.yjc.ir/fa/states",
    "https://www.yjc.ir/fa/freereporter",
    "https://www.yjc.ir/fa/netsearching",
];

// Main
foreach ($categories as $category_link) {
    print "$category_link\n";
    $ids = parseCategory($category_link);
    file_put_contents(link2name($category_link) . ".json", json_encode($ids));
    // exit();
}

// foreach ($categories as $category_link) {
//     $ids = json_decode(file_get_contents(link2name($category_link) . ".json"), true);
//     foreach ($ids as $id) {
//         print "$id\n";
//     }
// }
