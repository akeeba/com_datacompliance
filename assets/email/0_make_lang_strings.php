<?php
/**
 * @param   string  $file
 */
function parseHtml(string $file): void
{
	$html = file_get_contents($file);

	$dom = new DOMDocument();
	$dom->loadHTML($html);

	$title    = $dom->getElementsByTagName('title')->item(0)->nodeValue;
	$bodyNode = $dom->getElementsByTagName('body')->item(0);
	$bodyHtml = trim(str_replace(['<body>', '</body>'], ['', ''], $dom->saveHTML($bodyNode)));
	$bodyText = str_replace("\n", '\\n', strip_tags(str_replace('</h3>', '\n' . str_repeat('-', 70), $bodyHtml)));
	$bodyHtml = str_replace("\n", '\\n', $bodyHtml);

	$title    = str_replace('"', '\\"', $title);
	$bodyHtml = str_replace('"', '\\"', $bodyHtml);
	$bodyText = str_replace('"', '\\"', $bodyText);

	$key = strtoupper(basename($file, '.html'));

	echo 'COM_DATACOMPLIANCE_MAIL_' . $key . '_SUBJECT="' . $title . "\"\n";
	echo 'COM_DATACOMPLIANCE_MAIL_' . $key . '_BODY="' . $bodyText . "\"\n";
	echo 'COM_DATACOMPLIANCE_MAIL_' . $key . '_BODY_HTML="' . $bodyHtml . "\"\n";
}

ob_start();

$di = new DirectoryIterator(__DIR__);

/** @var DirectoryIterator $file */
foreach ($di as $file)
{
	if (!$file->isFile())
	{
		continue;
	}

	if ($file->getExtension() != 'html')
	{
		continue;
	}

	parseHtml($file->getPathname());

	echo "\n";
}

$contents = ob_get_clean();
file_put_contents('en-GB.ini', $contents);

echo $contents;
