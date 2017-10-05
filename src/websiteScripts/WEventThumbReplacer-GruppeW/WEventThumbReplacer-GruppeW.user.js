// ==UserScript==
// @name        WEventThumbReplacer-GruppeW
// @namespace   Zabuza
// @description Replaces event news image on the front page by generated thumbnails of banner images of the event
// @include     *.gruppe-w.de/news.php*
// @version     1
// @require https://code.jquery.com/jquery-3.2.0.min.js
// @grant       none
// ==/UserScript==

/*
 * Finds and replaces all event news images on the current site
 * by generated thumbnails of banner images of the event.
 */
function findAndReplaceThumbs() {
	$('div.panel_l > div.floatfix a[href*="news_cats.php"] > img.news-category').each(function() {
		var currentThumb = this;
		var newsAnchor = $(this).parent().first();
		
		// Get the type of the event which is also the id of the news category
		var catIdPattern = /.*news_cats\.php\?.*cat_id=(\d+)&?.*/gi
		var catId = catIdPattern.exec($(newsAnchor).attr('href'))[1];
		if (catId == 0) {
			// Is no event
			return;
		}
		
		var container = $(newsAnchor).parent().first();
		var threadAnchor = $(container).find('a[href*="viewthread.php"]').first();
		
		var threadAnchorText = $(threadAnchor).text();
		if (threadAnchorText != 'Zur Anmeldung im Forum') {
			// Is no event
			return;
		}
		
		// Get the id of events thread
		var threadIdPattern = /.*viewthread\.php\?.*thread_id=(\d+)&?.*/gi
		var threadId = threadIdPattern.exec($(threadAnchor).attr('href'))[1];
		
		if (!$.isNumeric(catId) || !$.isNumeric(threadId) || catId <= 0 || threadId <= 0) {
			// Is no event
			return;
		}
		
		// Construct the url to the generated event thumbnail
		var generatedThumbUrl = thumbGeneratorService + '?eventType=' + catId + '&eventId=' + threadId;
		
		// Replace thumbnails
		$(currentThumb).attr('src', generatedThumbUrl);
	});
}

var thumbGeneratorService = 'https://zabuza.pitaya.duckdns.org/w/eventThumb/wEventThumb.php';

$(document).ready(findAndReplaceThumbs);