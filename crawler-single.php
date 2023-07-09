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

function fetchNews(int $id)
{
    $url = "https://www.yjc.ir/fa/news/" . $id . "/";
    if (file_exists("cache/" . $id . ".html")) {
        $data = file_get_contents("cache/" . $id . ".html");
    } else {
        $data = sendGet($url);
        file_put_contents("cache/" . $id . ".html", $data);
    }

    if ($data === null || $data === "") return;

    // <span class="title-news"> 					گفت وگوی سفیر ایران در کویت با امیرعبداللهیان 				</span>
    $title = null;
    $regex = '/<span class=\"title-news\">(.*?)<\/span>/i';
    preg_match($regex, $data, $_title);
    if (isset($_title[1])) $title = trim($_title[1]);

    // <strong class="news_strong">محمد توتونچی، سفیر جمهوری اسلامی ایران در کویت پیش از عزیمت به محل مأموریت با حسین امیرعبداللهیان، وزیر امور خارجه دیدار کرد.</strong>
    $summary = null;
    $regex = '/<strong class=\"news_strong\">(.*?)<\/strong>/i';
    preg_match($regex, $data, $_summary);
    if (isset($_summary[1])) $summary = trim($_summary[1]);

    // <div class="row baznashr-body"> 			<p>محمد توتونچی، سفیر جمهوری اسلامی ایران در کویت پیش از عزیمت به محل مأموریت با حسین امیرعبداللهیان، وزیر امور خارجه دیدار کرد.</p>
// <p>&nbsp;در این ملاقات، سفیر جمهوری اسلامی ایران ضمن تشریح آخرین وضعیت روابط کشورمان با کویت، مهمترین برنامه&zwnj;های توسعه روابط با این کشور را مورد اشاره قرار داد.</p>
// <p>وزیر امور خارجه نیز با تاکید بر اولویت گسترش روابط با کشور&zwnj;های مسلمان همسایه، رهنمود&zwnj;هایی جهت تقویت بیش از پیش مناسبات در همه ابعاد به خصوص حوزه&zwnj;های اقتصادی، فرهنگی و امور ایرانیان ارائه کرد.</p>
// <p>توتونچی، در سمت&zwnj;های رییس اداره دوم خلیج فارس وزارت امور خارجه و معاونت سفارت جمهوری اسلامی ایران در مسقط خدمت کرده است.</p> 			<div class="path_bottom_body"> 				<a href="/"> 					باشگاه خبرنگاران جوان 				</a> 				<i class="fa fa-circle"></i>  				 					<a href="/fa/political"> 						سیاسی 					</a> 					 						<i class="fa fa-circle"></i> 						<a href="/fa/political/29"> 							سیاست خارجی 						</a> 					 				 			</div> 		</div>
// <div class="wrapper"
    $text = null;
    $regex = '/<div class=\"row baznashr-body\">(.*?)<\/div>(\s*|)<div class=\"wrapper/is';
    preg_match($regex, $data, $_text);
    if (isset($_text[1])) $text = trim($_text[1]);
    // Remove from `<div class=\"path_bottom_body\">` to end of text
    $regex = '/<div class=\"path_bottom_body\">(.*?)<\/div>(\s*|)$/is';
    $text = trim(preg_replace($regex, "", $text));

    // <meta itemprop="image" content="https://cdn.yjc.ir/files/fa/news/1402/4/18/17965297_840.jpg">
    $image = null;
    $regex = '/<meta itemprop=\"image\" content=\"(.*?)\">/i';
    preg_match($regex, $data, $_image);
    if (isset($_image[1])) $image = trim($_image[1]);

    // <div class="path_bottom_body"> 				<a href="/"> 					باشگاه خبرنگاران جوان 				</a> 				<i class="fa fa-circle"></i>  				 					<a href="/fa/political"> 						سیاسی 					</a> 					 						<i class="fa fa-circle"></i> 						<a href="/fa/political/29"> 							سیاست خارجی 						</a> 					 				 			</div>
    $category = null;
    $regex = '/<div class=\"path_bottom_body\">(.*?)<\/div>/i';
    preg_match($regex, $data, $_category);
    if (isset($_category[1])) $category = $_category[1];

    $categories = [];
    $regex = '/<a href=\"(.*?)\">(.*?)<\/a>/i';
    preg_match_all($regex, $category, $_categories);
    if (isset($_categories[2])) $categories = array_map('trim', array_values(array_unique($_categories[2])));
    // Remove "باشگاه خبرنگاران جوان" from categories if exists
    if (isset($categories[0]) && $categories[0] == "باشگاه خبرنگاران جوان") unset($categories[0]);
    $categories = array_values($categories);

    //  href="/fa/tags/([^\"]+)\"
    $tags = [];
    $regex = '/href=\"\/fa\/tags\/([^\/]+)\/([^\/]+)\/([^\"]+)\"/i';
    preg_match_all($regex, $data, $_tags);
    if (isset($_tags[3])) $tags = array_map('trim', array_map('urldecode', array_values(array_unique($_tags[3]))));
    
    file_put_contents("news/$id.json", json_encode([
        "url" => $url,
        "id" => $id,
        "title" => $title,
        "summary" => $summary,
        "text" => $text,
        "image" => $image,
        "categories" => $categories,
        "tags" => $tags,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

@mkdir("news");
@mkdir("cache");

$json_files = glob("*.json");
foreach ($json_files as $json_file) {
    $ids = json_decode(file_get_contents($json_file), true);
    foreach ($ids as $id) {
        fetchNews($id);
    }
}
