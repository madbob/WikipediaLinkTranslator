<?php

require('vendor/autoload.php');

function normalizeURL($url)
{
	if (preg_match('/^(https|http)?(\:)?(\/\/)?[a-z\-]*\.wikipedia\.org\/wiki\/.*$/', $url) == 1) {
		if (strncmp($url, 'http', 4) == 0) {
			if (strncmp($url, 'https:', 6) == 0)
				$offset = 6;
			else
				$offset = 5;

			$url = substr($url, $offset);
		}

		if (substr($url, 0, 2) != '//')
			$url = '//' . $url;

		/*
			Unfortunately there is no way to match translate anchors
			into a page, so those are not considered
		*/
		$hash = strpos($url, '#');
		if ($hash !== false)
			$url = substr($url, 0, $hash);

		return $url;
	}
	else {
		return null;
	}
}

function getUrlInfo($url)
{
	$client = new \Predis\Client();
	$u = $client->get($url);
	if ($u == null) {
		$info = (object) [];

		$page = file_get_contents('https:' . $url);
		$xml = simplexml_load_string($page);

		$nodes = $xml->xpath("//ul/li[contains(@class, 'interlanguage-link')]/a");
		foreach($nodes as $node)
			$info->{$node['lang']} = (string) $node['href'];

		$enc_info = json_encode($info);

		foreach($info as $lang => $url)
			$client->set($url, $enc_info);

		return $info;
	}
	else {
		return json_decode($u);
	}
}

function getProperLink($url, $info)
{
	/*
		TODO: use GeoIP to match a geographic area and a language?
	*/
	if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		return $url;

	$languages = [];
	foreach($info as $lang => $url)
		$languages[] = $lang;

	$negotiator = new \Negotiation\LanguageNegotiator();
	$bests = $negotiator->getBest($_SERVER['HTTP_ACCEPT_LANGUAGE'], $languages);
	$lang = $bests->getType();
	return $info->$lang;
}

if (isset($_GET['u']) && !empty($_GET['u'])) {
	$url = urldecode($_GET['u']);
	$url = normalizeURL($url);
	if ($url) {
		$info = getUrlInfo($url);
		$new_url = getProperLink($url, $info);
		header('Location: ' . $new_url);
		exit();
	}
}

else if (isset($_GET['t']) && !empty($_GET['t'])) {
	$url = urldecode($_GET['t']);
	$url = normalizeURL($url);
	if ($url != null)
		echo sprintf("http://%s/?u=%s", $_SERVER['HTTP_HOST'], urlencode($url));
	exit();
}

?><html>
	<head>
		<title>Wikipedia Link Translator</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<script type="application/javascript" src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
		<script type="application/javascript">
			$(document).ready(function() {
				$('input[name=t]').on('paste', function () {
					var element = $(this);
					setTimeout(function () {
						var text = element.val();
						if (text != '') {
							$.get('?t=' + encodeURIComponent(text), function(data) {
								$('.result').text(data).attr('href', data);
							});
						}
					}, 100);
				});
			});
		</script>

		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" integrity="sha512-dTfge/zgoMYpP7QbHy4gWMEGsbsdZeCXz7irItjcC3sPUFtf0kuFbDz/ixG7ArTxmDjLXDmezHubeNikyKGVyQ==" crossorigin="anonymous">
	</head>

	<body>
		<div class="container">
			<div class="col-md-12">
				<p>&nbsp;</p>
			</div>

			<div class="col-md-12 text-center">
				<img src="img/logo.png" />
			</div>

			<div class="col-md-12 text-center">
				<input class="form-control input-lg" type="text" name="t" placeholder="e.g. https://en.wikipedia.org/wiki/Encyclopedia" />
			</div>
			<div class="col-md-12 text-center">
				<p class="alert alert-info lead">
					<a class="result">&nbsp;</a>
				</p>
			</div>

			<div class="col-md-12">
				<p>&nbsp;</p>
			</div>

			<div class="col-md-12">
				<p>
					Wikipedia Link Translator is a tool to route users to the most appropriate Wikipedia page for a given subject, accordly to their preferred language.
				</p>
				<p>
					Paste a Wikipedia URL, from any language, in the field here above to obtain a translatable link, or just include where you want a link in the form <code>http://<?php echo $_SERVER['HTTP_HOST'] ?>?u=YOUR_WIKIPEDIA_LINK</code>. The application will guess a valid language for the user visiting the link, and will automatically redirect him to the relative page.
				</p>
			</div>

			<div class="col-md-12">
				<p>&nbsp;</p>
			</div>

			<div class="col-md-12">
				<p>
					Powered by <a href="http://php.net/">PHP</a> and <a href="http://redis.io/">Redis</a>. Created and hosted by <a href="http://madbob.org/">Roberto -MadBob- Guido</a>.
				</p>
			</div>
		</div>

		<script type="application/javascript" src="http://vh.madbob.org/vh.js.php?project=madbob/WikipediaLinkTranslator"></script>
	</body>
</html>
