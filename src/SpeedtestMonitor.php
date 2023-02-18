<?php

date_default_timezone_set('Europe/Moscow');
$org = 'Sijeko';
$bucket = 'Sijeko';
$token = 'Ваш токен…';
$server = 'danoxv';
$filename = createDirectoriesAndFiles();
$speed = writeToFile($filename);
$data = 'download_speedtest,host=' . $server . ' value=' . $speed['download'] . PHP_EOL . 'upload_speedtest,host=' . $server . ' value=' . $speed['upload'] . PHP_EOL;
try {
	writeToInflux($data, $org, $bucket, $token);
} catch (Throwable $e) {
	echo 'Невозможно записать данные `¯\_(ツ)_/¯` (⌣̀_⌣́)', PHP_EOL;
}

function createDirectoriesAndFiles(): ?string
{
	$todayNumber = date('Y-m-d');
	$filename = date('H:i:s') . '.txt';
	$launchPath = '/home/denis/project/network-monitor/launches' . DIRECTORY_SEPARATOR . $todayNumber;
	$filename = $launchPath . DIRECTORY_SEPARATOR . $filename;
	if (!is_dir($launchPath) && !mkdir($launchPath, 0777, true) && !is_dir($launchPath)) {
		throw new RuntimeException(sprintf('Directory "%s" was not created', $launchPath));
	}
	if (file_put_contents($filename, '') === false) {
		return null;
	}
	return $filename;
}

function writeToFile(string $filename): array
{
	exec('speedtest cli > ' . $filename);
	$content = file_get_contents($filename);
	$resultStringArray = explode(PHP_EOL, $content);
	preg_match('/([0-9.]+) Mbps/', $resultStringArray[6], $download);
	preg_match('/([0-9.]+) Mbps/', $resultStringArray[8], $upload);
	return ['download' => $download[1], 'upload' => $upload[1]];
}

/**
 * @throws JsonException
 */
function writeToInflux(string $data, string $org, string $bucket, string $token): bool
{
	$url = 'http://localhost:8086/api/v2/write' .
		'?org=' . urlencode($org) . '&bucket=' . urlencode($bucket);
	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_URL => $url,
		CURLOPT_HTTPHEADER => ['Authorization: Token ' . $token],
		CURLOPT_POSTFIELDS => $data,
		CURLOPT_POST => true,
	]);
	$response = json_encode(curl_exec($curl), JSON_THROW_ON_ERROR);
	$httpCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

	curl_close($curl);
	if ($httpCode !== 204) {
		throw new RuntimeException(
			'Could not write to InfluxDb' .
			'; data: ' . $data .
			'; response: ', (int)$response
		);
	}
	return true;
}
