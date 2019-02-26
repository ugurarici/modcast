<?php

require_once __DIR__."/vendor/autoload.php";

//  Modcast: Mirror as Podcast
//  Create a podcast feed in sync with a YouTube playlist

//  get uri of YouTube playlist from user
$uri = "https://www.youtube.com/playlist?list=PLN5Tz2x_KM-iOGgMd266yUr4Klb8kL8u5";
$uriParser = new \Riimu\Kit\UrlParser\UriParser();
$uri = $uriParser->parse($uri);
$uriParams = $uri->getQueryParameters();
if(!array_key_exists('list', $uriParams)) {
    die("This is not a YouTube playlist uri.");
}

//  fetch playlist rss and parse it
$playlistRSSUri = "https://www.youtube.com/feeds/videos.xml?playlist_id=".$uriParams['list'];
$youtubeRSSParser = new \Gbuckingham89\YouTubeRSSParser\Parser();
$channel = $youtubeRSSParser->loadUrl($playlistRSSUri);

//  find each video's audio stream uri
$httpClient = new \GuzzleHttp\Client();
foreach($channel->videos as $video) {
    $videoId = $video->id;
    $videoInfoUrl="https://images".rand(1, 30)."-focus-opensocial.googleusercontent.com/gadgets/proxy?container=none&url=https%3A%2F%2Fwww.youtube.com%2Fget_video_info%3Fvideo_id%3D".$videoId;
    $response = $httpClient->request('GET', $videoInfoUrl);
    $videoInfoText = $response->getBody()->getContents();
    $videoInfo = null;
    parse_str($videoInfoText, $videoInfo);
    $vInfo = [$videoInfo['url_encoded_fmt_stream_map'], $videoInfo['adaptive_fmts']];
    $streams = explode(",", implode(",", $vInfo));
    foreach ($streams as $stream) {
        $sInfo = null;
        parse_str($stream, $sInfo);
        if($sInfo['itag']==140){
            $video->audio = $sInfo['url'];
            break;
        }
    }
}

//  generate podcast rss
// var_dump($channel);

$podcastRSSFeed = new \Castanet_Feed($channel->name, $channel->url, $channel->name);
$podcastRSSFeed->setLanguage("tr-TR");
$podcastRSSFeed->setCopyright($channel->name);
$podcastRSSFeed->setItunesImage("https://i.ytimg.com/vi/".$channel->videos[0]->id."/maxresdefault.jpg");
$podcastRSSFeed->setImage($channel->videos[0]->thumbnail_url, $channel->videos[0]->thumbnail_width, $channel->videos[0]->thumbnail_height);
$podcastRSSFeed->setItunesExplicit(true);
$podcastRSSFeed->setItunesBlock(true);
$podcastRSSFeed->setItunesOwner($channel->name);
$podcastRSSFeed->setItunesAuthor($channel->name);

foreach ($channel->videos as $video) {
    $feedItem = new \Castanet_Item;
    $feedItem->setTitle($video->title);
    $feedItem->setLink($video->url);
    $feedItem->setGuid($video->url);
    $feedItem->setDescription($video->description);
    $feedItem->setPublishDate($video->published_at);
    $feedItem->setMediaUrl($video->audio);
    $feedItem->setMediaMimeType("audio/mp4");
    $feedItem->setItunesSubtitle($channel->name);
    $feedItem->setItunesSummary($video->description);
    $feedItem->setItunesImage("https://i.ytimg.com/vi/".$video->id."/maxresdefault.jpg");

    $podcastRSSFeed->addItem($feedItem);
}

file_put_contents("demo.xml", $podcastRSSFeed);