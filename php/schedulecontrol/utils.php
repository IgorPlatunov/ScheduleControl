<?php
	namespace ScheduleControl;

	use DateInterval;
	use DateTime;

	final class Utils
	{
		public static function ConcatArray(array $arr, string $separator)
		{
			$str = "";

			foreach ($arr as $value)
				$str .= (strlen($str) > 0 ? $separator : "").$value;
			
			return $str;
		}

		public static function ConcatArrayKV(array $arr, string $separatorkv, string $separator)
		{
			$str = "";

			foreach ($arr as $key => $value)
				$str .= (strlen($str) > 0 ? $separator : "").$key.$separatorkv.$value;
			
			return $str;
		}

		public static function ConcatArrays(string $separator,array ...$arrs)
		{
			$arr = array();

			for ($i = 0; $i < count($arrs[0]); $i++)
			{
				$a = array();

				foreach ($arrs as $value)
					$a[] = $value[$i];
				
				$arr[] = self::ConcatArray($a, $separator);
			}

			return $arr;
		}

		public static function SqlDate(DateTime $date, bool $notime = false): string
		{
			if ($notime) return date_format($date, "Y-m-d");
			return date_format($date, "Y-m-d H:i:s");
		}

		public static function GetDateWeek(DateTime $date): array
		{
			$weekday = $date->format("w") - 1;
			if ($weekday == -1) $weekday = 6;

			$wstart = clone $date; date_sub($wstart, new DateInterval("P".$weekday."D"));
			$wend = clone $date; date_add($wend, new DateInterval("P".(6-$weekday)."D"));

			return array($wstart, $wend, $weekday);
		}

		public static function AbsMod(float $a, float $b): float
		{ return $a < 0 ? (($r = fmod(-$a, $b)) == 0 ? 0 : $b - $r) : fmod($a, $b); }
	}
?>