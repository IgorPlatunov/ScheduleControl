<?php
	namespace ScheduleControl\Core;

	use DateTime, Throwable;
	use ScheduleControl\Config;

	final class Logs
	{
		static function Write(string $log): void
		{
			if (Config::GetParameter("General", "EnableLogs") == 0) return;

			$dir = dirname(__DIR__, 3)."/logs";

			if (!is_dir($dir)) mkdir($dir);

			$date = new DateTime();
			$fname = $dir."/".$date->format("Y-m-d").".txt";

			if (!($f = fopen($fname, "a"))) return;

			fwrite($f, "[".$date->format("H:i:s")."] $log\n");
		}
	}

	set_exception_handler(function(Throwable $exception) {
		Logs::Write("Необработанное исключение: [".$exception->getCode()."] ".$exception->getMessage()." (файл ".$exception->getFile().")");
	});

	set_error_handler(function(int $errno, string $errstr, string $errfile = "", int $errline = -1, ?array $context = array()) {
		Logs::Write("Необработанная ошибка: [$errno] $errstr ($errfile:$errline)");

		http_response_code(500);
		exit("Необработанная ошибка на стороне сервера");
	});
?>