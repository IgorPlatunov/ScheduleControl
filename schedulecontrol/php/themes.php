<?php
	final class Themes
	{
		private static array $themes = array();

		public static function RegisterTheme(string $name, string $nicename): void
		{ self::$themes[$name] = $nicename; }

		public static function GetThemes(): array { return self::$themes; }

		public static function GetCurrentTheme(): string
		{
			$theme = $_COOKIE["theme"] ?? "default";

			return isset(self::$themes[$theme]) ? $theme : "default";
		}

		public static function SetCurrentTheme(string $theme): void
		{
			if (isset(self::$themes[$theme]))
				setcookie("theme", $theme);
		}

		public static function LoadThemeFile(string $file): void
		{
			?>
				<link rel="stylesheet" href="css/<?php echo self::GetCurrentTheme(); ?>/<?php echo $file; ?>.css">
			<?php
		}
	}

	Themes::RegisterTheme("default", "Стандартная тема");
?>