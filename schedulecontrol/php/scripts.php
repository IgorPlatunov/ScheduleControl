<?php
	final class JavaScript
	{
		private static bool $sharedloaded = false;
		private static array $shared = array(
			"external/jquery-3.6.3.min",
			"external/jquery.table2excel.min",
			"shared",
		);

		public static function LoadFile(string $file): void
		{
			if (!self::$sharedloaded)
			{
				self::$sharedloaded = true;

				foreach (self::$shared as $sfile)
					self::LoadFile($sfile);
			}

			?>
				<script src="js/<?php echo $file; ?>.js"></script>
			<?php
		}
	}
?>