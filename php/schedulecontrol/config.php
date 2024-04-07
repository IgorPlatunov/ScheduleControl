<?php
	namespace ScheduleControl;
	use RuntimeException;

	final class Config
	{
		private static array $parameters = array();

		public static function Initialize()
		{
			$fname = __DIR__."/config.ini";

			$ini = fopen($fname, "r");
			if (!$ini) return;

			$data = fread($ini, filesize($fname));
			fclose($ini);

			$lines = explode("\n", $data);
			$result = array();
			$cursection = false;

			foreach ($lines as $line)
			{
				$line = trim($line);

				if (!$line || $line[0] == ";" || $line[0] == "#") continue;

				if ($line[0] == "[" && $secindex = strpos($line, "]"))
					$cursection = substr($line, 1, $secindex - 1);
				else
				{
					if (!strpos($line, "=")) return;

					$parts = explode("=", $line, 2);
					$param = trim($parts[0]);
					$value = trim(explode(";", $parts[1], 2)[0]);

					if (preg_match("/^\".*\"$/", $value) || preg_match("/^'.*'$/", $value))
						$value = mb_substr($value, 1, mb_strlen($value) - 2);

					if (preg_match("/\[(.*?)\]/", $param, $matches))
					{
						$param = preg_replace("/\[.*\]/", "", $param);
						$arrparam = $matches[1];
						
						if ($arrparam)
							if ($cursection)
								$result[$cursection][$param][$arrparam] = $value;
							else
								$result[$param][$arrparam] = $value;
						else if ($cursection)
								$result[$cursection][$param][] = $value;
							else
								$result[$param][] = $value;
					}
					else if ($cursection)
						$result[$cursection][$param] = $value;
					else
						$result[$param] = $value;
				}
			}

			self::$parameters = $result;
		}

		public static function GetParameter(string ...$path)
		{
			$param = self::$parameters;

			foreach ($path as $part)
				if (($param = ($param[$part] ?? null)) === null)
					throw new RuntimeException("Конфигурация не имеет требуемого параметра (".implode(" => ", $path).")");

			return $param;
		}
	}
	Config::Initialize();
?>