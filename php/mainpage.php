<?php
	require_once("themes.php");
	require_once("scripts.php");
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>ИС расписания колледжа</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("mainpage"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
	</head>
	<body>
		<?php include("header.php"); ?>
		<div id="title">Добро пожаловать в информационную систему расписания колледжа!</div>

		<section>
			<div>Доступные редакторы для работы</div>
			<div>
				<?php foreach (GetAvailableEditors() as $editor) { ?>
					<a href="<?php echo $editor[4]; ?>">
						<h3><?php echo $editor[2]; ?></h3>
						<div><?php echo $editor[3]; ?></div>
					</a>
				<?php } ?>
			</div>
		</section>

		<section>
			<div>Доступные для редактирования коллекции регистров</div>
			<div>
				<?php foreach (GetRegistryCollections() as $regcol) { if ($regcol[2] == 0) { ?>
					<a href="collections.php?collection=<?php echo $regcol[0]; ?>">
						<h3><?php echo $regcol[1]; ?></h3>
					</a>
				<?php } } ?>
			</div>
		</section>
	</body>
</html>