<?php

/**
 * Скрипт.
 *
 * @author Danoxv©
 * @date 2023
 * @time 01:51
 */

// TODO write README
//Время сна после каждой итерации
$timeSleep = 1;

/**
 * Инициализация переменных для функции writeToInflux()
 */
$data = '';
$org = 'Sijeko';
$bucket = 'Sijeko';
$token = 'Ваш токен…';
$server = gethostname();

/**
 * Инициализация перменных для цикла
 */
$nameInterface = 'wlp1s0';
$lastTime = null;

$bitsAfterReceiveLast = null;
$bitsAfterTransmitLast = null;

$lastBitsRecive = null;
$lastBitsTransmit = null;
$timeAfterLast = null;
$bitsReceive = null;
$bitsTransmit = null;
$receptionSpeed = null;
$recoilSpeed = null;

/**
 * RAM info
 */
$volumeRam = exec("vmstat -s | grep -i 'total memory' | sed 's/ *//'");
$memTotal = (int)preg_replace("~\D+~", '', $volumeRam);

/**
 * CPU interface
 */
$nuclearCore = exec('cat /proc/cpuinfo | grep "core id" | wc -l');

/**
 * Приветсвенная карточка системного монитора.
 */
echo "
                                         _____           _                  ___  ___             - -
                                        /  ___|         | |                 |  \/  |           (_) |
                                        \ `--. _   _ ___| |_ ___ _ __ ___   | .  . | ___  _ __  _| |_ ___  _ __
                                         `--. \ | | / __| __/ _ \ '_ ` _ \  | |\/| |/ _ \| '_ \| | __/ _ \| '__|
                                        /\__/ / |_| \__ \ ||  __/ | | | | | | |  | | (_) | | | | | || (_) | |
                                        \____/ \__, |___/\__\___|_| |_| |_| \_|  |_/\___/|_| |_|_|\__\___/|_|
                                                __/ |
                                               |___/
";

while (true) {
	$timeStart = microtime(true);
	$fileSysStr = getFileSystem();
	/**
	 * RAM info
	 */
	$getInfoRam = getMemTotal();

	$memFree = $getInfoRam['memFree'];
	$memUsed = $memTotal - $getInfoRam['memFree'];
	$memUsage = (int)(($memUsed / $memTotal) * 100);

	$swapFree = $getInfoRam['swapFree'];
	$swapTotal = $getInfoRam['swapTotal'];
	$swapUsed = $swapTotal - $swapFree;
	$swapUsage = (int)(($swapUsed / $swapTotal) * 100);

	/**
	 * CPU info
	 */
	$loadCPU = gettingTheNumberOfThreads(0);
	$loadInPercentCPU = ($loadCPU / $nuclearCore) * 100;
	$roundPercentCPU = $loadInPercentCPU;

	/**
	 * info Wi-Fi Interface.
	 */

	[$receive, $transmit] = receivingUploadingSpeed($nameInterface);

	echo 'Принято: ' . sizeConverter($receive, 'byte') . ' ';
	echo 'Отправлено: ' . sizeConverter($transmit, 'byte') . ' ';
	$timeAfter = microtime(true);

	if ($lastTime !== null) {
		$bitsReceive = $receive * 8;
		$bitsTransmit = $transmit * 8;
		$bitsAfterReceiveLast = $bitsReceive - $lastBitsRecive;
		$bitsAfterTransmitLast = $bitsTransmit - $lastBitsTransmit;
		$timeAfterLast = $timeAfter - $lastTime;
		$receptionSpeed = $bitsAfterReceiveLast / $timeAfterLast;
		$recoilSpeed = $bitsAfterTransmitLast / $timeAfterLast;
		echo 'скорость приёма: ' . sizeConverter($receptionSpeed) . '/с' . ' ';
		echo 'скорость отправки: ' . sizeConverter($recoilSpeed) . '/с' . PHP_EOL;

		$strFileData = printDataFileSystem($fileSysStr, false);
		$strFileData = trim($strFileData);
		$data =
			'network_in,host=' . $server . ' value=' . $receive . PHP_EOL .
			'network_out,host=' . $server . ' value=' . $transmit . PHP_EOL .
			'network_speed_in,host=' . $server . ' value=' . $receptionSpeed . PHP_EOL .
			'network_speed_out,host=' . $server . ' value=' . $recoilSpeed . PHP_EOL .
			'cpu_percent_load,host=' . $server . ' value=' . $roundPercentCPU . PHP_EOL .
			'mem_free,host=' . $server . ' value=' . $memFree . PHP_EOL .
			'mem_used,host=' . $server . ' value=' . $memUsed . PHP_EOL .
			'mem_usage,host=' . $server . ' value=' . $memUsage . PHP_EOL .
			'swap_free,host=' . $server . ' value=' . $swapFree . PHP_EOL .
			'swap_used,host=' . $server . ' value=' . $swapUsed . PHP_EOL .
			'swap_usage,host=' . $server . ' value=' . $swapUsage . PHP_EOL .
			$strFileData;
	} else {
		echo PHP_EOL;
	}

	$lastTime = $timeAfter;
	$lastBitsRecive = $bitsReceive;
	$lastBitsTransmit = $bitsTransmit;

	//процессор
	echo 'Нагрузка на процессора: ' . round($roundPercentCPU) . '%' . PHP_EOL;

	//память
	echo 'Cвободно памяти: ' . sizeConverter($memFree, 'kilobyte') . PHP_EOL;
	echo 'Занято: ' . sizeConverter($memUsed, 'kilobyte') . ' из ' . sizeConverter($memTotal,
			'kilobyte') . PHP_EOL;
	echo 'Процент занятости: ' . round($memUsage, 1) . '%' . PHP_EOL;

	echo 'Cвободно подкачки: ' . sizeConverter($swapFree, 'kilobyte') . PHP_EOL;
	echo 'Занято: ' . sizeConverter($swapUsed, 'kilobyte') . ' из ' . sizeConverter($swapTotal,
			'kilobyte') . PHP_EOL;
	echo 'Процент занятости подкачки: ' . round($swapUsage, 1) . '%' . PHP_EOL;
	echo PHP_EOL;

	/**
	 * FileSystem info
	 */
	printDataFileSystem($fileSysStr);

	$timeDiff = microtime(true) - $timeStart;
	$realTimeSleep = ($timeSleep - $timeDiff) * 1_000_000;
	writeToInflux($data, $org, $bucket, $token);
	if ($realTimeSleep > 0) {
		usleep($realTimeSleep);
	}
}

/**
 * @throws JsonException
 */
function writeToInflux($data, $org, $bucket, $token): bool
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

function receivingUploadingSpeed(string $nameInterface): array
{
	$receive = null;
	$transmit = null;
	$lines = file('/proc/net/dev');
	foreach ($lines as $line) {
		$arr = preg_split('/\s+/', $line);
		if ($arr[0] === $nameInterface . ':') {
			[$receive, $transmit] = [$arr[1], $arr[9]];
		}
	}
	return [$receive, $transmit];
}

function sizeConverter(int $size, $unit = 'bit'): string
{
	$units = match ($unit) {
		'bit'      => ['б', 'кб', 'Mб', 'Гб', 'Тб', 'Пб', 'Эб', 'Зб', 'Йб',],
		'byte'     => ['Б', 'КиБ', 'МиБ', 'ГиБ', 'ТиБ', 'ПиБ', 'ЭиБ', 'ЗиБ', 'ЙиБ',],
		'kilobyte' => ['КиБ', 'МиБ', 'ГиБ', 'ТиБ', 'ПиБ', 'ЭиБ', 'ЗиБ', 'ЙиБ',],
		default    => throw new InvalidArgumentException('Supported units: bit, byte'),
	};

	static $decimalsMap = [
		1 => 2,
		2 => 1,
		3 => 0,
	];

	$unit = (string)reset($units);

	while ($size > 999.5) {
		$size /= ($unit === 'bit' ? 1000 : 1024);
		$numParts = explode('.', $size, 1);

		$len = strlen($numParts[0]);

		$size = (int)number_format($size, $decimalsMap[$len] ?? 0);

		$unit = (string)next($units);
	}
	return $size . ' ' . $unit;
}

/**
 * Принимает число которое, указывает какое брать число по счету, отсчет начинается с нуля.
 */
function gettingTheNumberOfThreads(int $int): string
{
	$lines = file('/proc/loadavg');
	$var = explode(' ', $lines[$int]);

	return $var[$int];
}

function getMemTotal(): array
{
	$propsRAM = [];
	$lines = file('/proc/meminfo');
	foreach ($lines as $line) {
		$replaceStr = (int)preg_replace("~\D+~", '', $line);

		if (stripos($line, 'MemFree:') !== false) {
			$propsRAM['memFree'] = $replaceStr;
		}

		if (stripos($line, 'Active:') !== false) {
			$propsRAM['active'] = $replaceStr;
		}

		//подкачка
		if (stripos($line, 'SwapTotal:') !== false) {
			$propsRAM['swapTotal'] = $replaceStr;
		}

		if (stripos($line, 'SwapFree:') !== false) {
			$propsRAM['swapFree'] = $replaceStr;
		}
	}
	return $propsRAM;
}

function getFileSystem(): string
{
	$strDev = '';
	$lines = shell_exec('df');
	$explode = explode(PHP_EOL, $lines);
	foreach ($explode as $value) {
		if (str_starts_with($value, '/dev')) {
			$strDev .= $value . PHP_EOL;
		}
	}
	return $strDev;
}

function printDataFileSystem($fileSysStr, $print = true): string
{
	$string = '';
	$expFS = explode(PHP_EOL, $fileSysStr);
	foreach ($expFS as $substr) {
		$arr = preg_split('/\s+/', $substr);
		if ($substr === "") {
			continue;
		}
		if ($print === true) {
			$diff = ((int)$arr[2] / (int)$arr[1]) * 100;
			echo 'Файл.система: ' . $arr[0] . PHP_EOL;
			echo 'Всего: ' . $arr[1] . PHP_EOL;
			echo 'Исп: ' . $arr[2] . PHP_EOL;
			echo 'Процент занятости: ' . $diff . '%' . PHP_EOL;
			echo 'Дост: ' . $arr[3] . PHP_EOL;
			echo 'Смонт' . $arr[5] . PHP_EOL;
			echo PHP_EOL;
		} else {
			$string .= 'disk_total,host=' . gethostname() . ',filesystem=' . $arr[0] . ',mount=' . $arr[5] . ' value=' . $arr[1] . PHP_EOL;
			$string .= 'disk_use,host=' . gethostname() . ',filesystem=' . $arr[0] . ',mount=' . $arr[2] . ' value=' . $arr[1] . PHP_EOL;
			$string .= 'disk_usage,host=' . gethostname() . ',filesystem=' . $arr[0] . ',mount=' . $arr[2] . ' value=' . ((int)$arr[2] / (int)$arr[1]) . PHP_EOL;
		}
	}
	return $string;
}
