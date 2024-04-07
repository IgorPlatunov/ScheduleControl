<?php
	require_once("session.php");

	final class PageRequest
	{
		private const SessionVarName = "pagerequest_actionvars";
		private static array $actionvars = array();

		public static function GetParameter(string $name): ?string
		{ return $_POST[$name] ?? null; }

		public static function GetBoolParameter(string $name): ?bool
		{ return self::GetParameter($name); }

		public static function GetIntParameter(string $name): ?int
		{ return self::GetParameter($name); }

		public static function GetFloatParameter(string $name): ?float
		{ return self::GetParameter($name); }

		public static function GetDateTimeParameter(string $name): ?DateTime
		{ return self::GetParameter($name); }

		public static function IsParameterSet(string $name): bool
		{ return self::GetParameter($name) !== null; }

		public static function SetActionVar(string $var, string|int|bool|null $value): void
		{ self::$actionvars[$var] = $value; }

		public static function GetActionVar(string $var): int|string|bool|null
		{ return self::$actionvars[$var] ?? null; }

		public static function IsActionVarSet(string $var): bool
		{ return self::GetActionVar($var) !== null; }

		public static function LoadActions(string ...$actions): void
		{
			if ($_SERVER["REQUEST_METHOD"] == "POST")
			{
				try
				{
					foreach ($actions as $action)
						require("actions/".$action.".php");
				}
				catch (Throwable $ex)
				{
					http_response_code(500);
					exit($ex->getMessage());
				}

				Session::SetVar(self::SessionVarName, json_encode(self::$actionvars));

				header("Location: ".$_SERVER["PHP_SELF"]);
				exit;
			}

			if (($varsstr = Session::GetVar(self::SessionVarName)) !== null)
			{
				Session::SetVar(self::SessionVarName);
				self::$actionvars = json_decode($varsstr, true);
			}
		}
	}
?>