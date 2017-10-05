<?php
function getEventContent($eventId) {
	$urlOfThread = 'https://www.gruppe-w.de/forum/viewthread.php?thread_id=' . $eventId;
	$contents = file_get_contents($urlOfThread);
	preg_match('/<!--forum_thread_prepost_1-->(.+)<!--(?:forum_thread_prepost_2|sub_forum_thread_table)-->/siU', $contents, $firstPostRaw);
	preg_match('/<div class=.post-message.>(.+)<!--sub_forum_post_message-->/siU', $firstPostRaw[1], $firstPost);

	return $firstPost[1];
}

function extraxtEventBanner($postContent) {
	preg_match('/.{1,2000}?<img src=["\'](.{1,150})["\'].{1,500}<strong>.{0,150}W.{0,50}ann\?.{0,50}<\/strong>/siU', $postContent, $imgUrl);

	return $imgUrl[1];
}

function getNameOfEventType($eventType) {
	// The mapping used in the forum is probably this one, else adjust
	$typeToName = array(
		19 => 'comp',
		20 => 'coop+',
		21 => 'coop',
		22 => 'milsim',
		23 => 'orga',
		24 => 'deploy',
		25 => 'blackbox',
		26 => 'zeus',
		27 => 'tvt',
		28 => 'training'
	);
	return $typeToName[$eventType];
}

function loadImage($url) {
	$imgType = exif_imagetype($url);
	if ($imgType == IMAGETYPE_GIF) {
		return imagecreatefromgif($url);
	} else if ($imgType == IMAGETYPE_JPEG) {
		return imagecreatefromjpeg($url);
	} else if ($imgType == IMAGETYPE_PNG) {
		return imagecreatefrompng($url);
	} else {
		return imagecreatefromwbmp($url);
	}
}

function getDefaultEventThumbUrl($eventType) {
	$newsPre = 'https://www.gruppe-w.de/images/news_cats/news_';
	$newsSucc = '.jpg';
	
	$name = getNameOfEventType($eventType);
	
	return $newsPre . $name . $newsSucc;
}

function getEventTypeStickerUrl($eventType) {
	$stickerPre = 'https://zabuza.pitaya.duckdns.org/w/eventThumb/sticker/sticker_';
	$stickerSucc = '.png';
	
	$name = getNameOfEventType($eventType);
	$stickerUrl = $stickerPre . $name . $stickerSucc;
	
	return $stickerUrl;
}

function createBannerThumb($bannerUrl, $outputWidth, $outputHeight, $eventType) {
	list($width, $height) = getimagesize($bannerUrl);

	// Scale image to output width
	$xAdjustRatio = $outputWidth / $width;
	$xAdjustWidth = $outputWidth;
	$xAdjustHeight = ceil($xAdjustRatio * $height);

	// Scale image to output height
	$yAdjustRatio = $outputHeight / $height;
	$yAdjustWidth = ceil($yAdjustRatio * $width);
	$yAdjustHeight = $outputHeight;

	// Determine fore- and background
	if ($yAdjustWidth < $outputWidth) {
		// yAdjust is foreground
		$foregroundWidth = $yAdjustWidth;
		$foregroundHeight = $yAdjustHeight;
	
		// xAdjust is background
		$backgroundWidth = $xAdjustWidth;
		$backgroundHeight = $xAdjustHeight;
	} else {
		// xAdjust is foreground
		$foregroundWidth = $xAdjustWidth;
		$foregroundHeight = $xAdjustHeight;
	
		// yAdjust is background
		$backgroundWidth = $yAdjustWidth;
		$backgroundHeight = $yAdjustHeight;
	}
	
	// Compute destination positions such that images
	// are placed in the center of the destination
	$backgroundDestX = floor(($outputWidth - $backgroundWidth) / 2);
	$backgroundDestY = floor(($outputHeight - $backgroundHeight) / 2);

	$foregroundDestX = floor(($outputWidth - $foregroundWidth) / 2);
	$foregroundDestY = floor(($outputHeight - $foregroundHeight) / 2);

	// Create container for the final image
	$thumb = imagecreatetruecolor($outputWidth, $outputHeight);
	// Load source
	$sourceType = exif_imagetype($bannerUrl);
	if ($sourceType == IMAGETYPE_GIF) {
		header('Content-Type: image/gif');
	} else if ($sourceType == IMAGETYPE_JPEG) {
		header('Content-Type: image/jpeg');
	} else if ($sourceType == IMAGETYPE_PNG) {
		header('Content-Type: image/png');
	} else {
		header('Content-Type: image/x-ms-bmp');
	}
	$source = loadImage($bannerUrl);

	// Scale and place background
	imagecopyresampled($thumb, $source, $backgroundDestX, $backgroundDestY, 0, 0, $backgroundWidth, $backgroundHeight, $width, $height);
	// Blur background
	for ($i = 0; $i < 20; $i++) {
		imagefilter($thumb, IMG_FILTER_GAUSSIAN_BLUR);
	}

	// Scale and place foreground
	imagecopyresampled($thumb, $source, $foregroundDestX, $foregroundDestY, 0, 0, $foregroundWidth, $foregroundHeight, $width, $height);
	
	// Place event type sticker
	$stickerUrl = getEventTypeStickerUrl($eventType);
	list($stickerWidth, $stickerHeight) = getimagesize($stickerUrl);
	$sticker = loadImage($stickerUrl);
	imagecopyresampled($thumb, $sticker, 3, $outputHeight - (3 + $stickerHeight), 0, 0, $stickerWidth, $stickerHeight, $stickerWidth, $stickerHeight);

	// Draw a black 1px border
	$blackColor = imagecolorallocate($thumb, 0, 0, 0);
	imageline($thumb, 0, 0, $outputWidth - 1, 0, $blackColor);
	imageline($thumb, 0, $outputHeight - 1, $outputWidth - 1, $outputHeight - 1, $blackColor);
	imageline($thumb, 0, 0, 0, $outputHeight - 1, $blackColor);
	imageline($thumb, $outputWidth - 1, 0, $outputWidth - 1, $outputHeight - 1, $blackColor);

	return $thumb;
}

// Input
$outputWidth = 250;
$outputHeight = 125;

$eventType = $_GET['eventType'];
$eventId = $_GET['eventId'];

// Extract
$imgUrl = extraxtEventBanner(getEventContent($eventId));
// Create
$thumb = createBannerThumb($imgUrl, $outputWidth, $outputHeight, $eventType);
// Output
imagejpeg($thumb, null, 100);
imagedestroy($thumb);
?>