<?php
/*
 * Automatically generates thumbnails of banner images of events at 'https://www.gruppe-w.de'.
 * Has two GET-parameters:
 * - eventType : The type of the event
 * - eventId : The id of the thread which contains the banner image to use as template for the thumbnail
 *
 * @author Zabuza {@literal <zabuza.dev@gmail.com>}
 */
 
/*
 * Gets the plain content of the event with the given thread id.
 * This is the content of the threads first post.
 *
 * @param eventId The id of the events thread
 * @return The plain content of the given events first post
 */
function getEventContent($eventId) {
	// NOTE: Method should be replaced by an access to the GruppeW-database
	$urlOfThread = 'https://www.gruppe-w.de/forum/viewthread.php?thread_id=' . $eventId;
	$contents = file_get_contents($urlOfThread);
	preg_match('/<!--forum_thread_prepost_1-->(.+)<!--(?:forum_thread_prepost_2|sub_forum_thread_table)-->/siU', $contents, $firstPostRaw);
	preg_match('/<div class=.post-message.>(.+)<!--sub_forum_post_message-->/siU', $firstPostRaw[1], $firstPost);

	return $firstPost[1];
}

/*
 * Extracts the banner image from the given raw post content.
 *
 * @param postContent Content of the post to extract banner from
 * @return The url to the banner included in the given post
 */
function extraxtEventBanner($postContent) {
	// NOTE: If data comes from GruppeW-database this probably
	// needs to be adjusted to BB-code instead of raw HTML
	preg_match('/.{1,2000}?<img src=["\'](.{1,150})["\'].{1,500}<strong>.{0,150}W.{0,50}ann\?.{0,50}<\/strong>/siU', $postContent, $imgUrl);

	return $imgUrl[1];
}

/*
 * Gets the name represented by the given event type.
 *
 * @param eventType Type of the event to get
 * @return The name represented by the given event type
 */
function getNameOfEventType($eventType) {
	// NOTE: The mapping used in the forum is probably this one, else adjust
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

/*
 * Loads and returns a handle to the image at the given url.
 *
 * @param url The url of the image to load
 * @return A handle to the image loaded from the given url
 */
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

/*
 * Creates and returns a handle to the default thumbnail used for an event
 * with the given type.
 * Also sets the output type, should only be used if this default thumbnail sets
 * the final output image.
 *
 * @param eventType The type of the event
 * @return A handle pointing to the default thumbnail used for an event with the given type
 */
function loadDefaultEventThumbUrl($eventType) {
	$defaultThumbPre = 'https://www.gruppe-w.de/images/news_cats/news_';
	$defaultThumbSucc = '.jpg';
	
	$name = getNameOfEventType($eventType);
	$defaultThumbUrl = $defaultThumbPre . $name . $defaultThumbSucc;
	
	$sourceType = exif_imagetype($defaultThumbUrl);
	if ($sourceType == IMAGETYPE_GIF) {
		header('Content-Type: image/gif');
	} else if ($sourceType == IMAGETYPE_JPEG) {
		header('Content-Type: image/jpeg');
	} else if ($sourceType == IMAGETYPE_PNG) {
		header('Content-Type: image/png');
	} else {
		header('Content-Type: image/x-ms-bmp');
	}
	$defaultThumb = loadImage($defaultThumbUrl);
	
	return $defaultThumb;
}

/*
 * Gets the url to the sticker representing the given event type.
 *
 * @param eventType The type of the event
 * @return An url pointing to the sticker representing the given event type
 */
function getEventTypeStickerUrl($eventType) {
	// NOTE: Sticker should be selfhosted by GruppeW and also
	// substituted by high-quality images from the original PSD-Files
	$stickerPre = 'https://zabuza.pitaya.duckdns.org/w/eventThumb/sticker/sticker_';
	$stickerSucc = '.png';
	
	$name = getNameOfEventType($eventType);
	$stickerUrl = $stickerPre . $name . $stickerSucc;
	
	return $stickerUrl;
}

/*
 * Creates and returns a handle to the thumbnail of the given banner.
 *
 * @param bannerUrl The url to the banner which should be used as thumbnail template
 * @param outputWidth The desired width of the thumbnail
 * @param outputHeight The desired height of the thumbnail
 * @eventType The type of the event
 * @return A handle to the created thumbnail
 */
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
	imagecopyresampled($thumb, $source,
		$backgroundDestX, $backgroundDestY, 0, 0,
		$backgroundWidth, $backgroundHeight, $width, $height);
	// Blur background
	for ($i = 0; $i < 20; $i++) {
		imagefilter($thumb, IMG_FILTER_GAUSSIAN_BLUR);
	}

	// Scale and place foreground
	imagecopyresampled($thumb, $source,
		$foregroundDestX, $foregroundDestY, 0, 0,
		$foregroundWidth, $foregroundHeight, $width, $height);
	
	// Place event type sticker
	$stickerUrl = getEventTypeStickerUrl($eventType);
	list($stickerWidth, $stickerHeight) = getimagesize($stickerUrl);
	$sticker = loadImage($stickerUrl);
	$stickerSpacing = 3;
	imagecopyresampled($thumb, $sticker,
		$stickerSpacing, $outputHeight - ($stickerSpacing + $stickerHeight), 0, 0,
		$stickerWidth, $stickerHeight, $stickerWidth, $stickerHeight);

	// Draw a black 1px border
	$blackColor = imagecolorallocate($thumb, 0, 0, 0);
	imageline($thumb, 0, 0, $outputWidth - 1, 0, $blackColor);
	imageline($thumb, 0, $outputHeight - 1, $outputWidth - 1, $outputHeight - 1, $blackColor);
	imageline($thumb, 0, 0, 0, $outputHeight - 1, $blackColor);
	imageline($thumb, $outputWidth - 1, 0, $outputWidth - 1, $outputHeight - 1, $blackColor);

	return $thumb;
}

// Input
$eventType = $_GET['eventType'];
$eventId = $_GET['eventId'];

// Desired dimension of thumbnail
$outputWidth = 250;
$outputHeight = 125;

// Extract
$imgUrl = extraxtEventBanner(getEventContent($eventId));

// Create
if (empty($imgUrl)) {
	// Fallback to the default image if banner extraction failed
	$thumb = loadDefaultEventThumbUrl($eventType);
} else {
	$thumb = createBannerThumb($imgUrl, $outputWidth, $outputHeight, $eventType);

}

// Output
imagejpeg($thumb, null, 100);
imagedestroy($thumb);
?>