<?php
	require_once("php/session.php");

	Session::Begin();

	if (Session::CurrentUser() === null || !Session::CurrentUser()->HasPrivilege("ScheduleRead"))
		Session::DropUser();

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$write = Session::CurrentUser()->HasPrivilege("ScheduleWrite");
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Редактор категорий предметов и кабинетов</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("subjectroomcategory"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span>Редактор категорий предметов и кабинетов</span>
				<ul class="iconbtn-list">
					<?php if ($write) { ?>
						<li><button id="clear-btn" class="iconbtn" title="Очистить и начать создание новой категории предметов и кабинетов"></button></li>
					<?php } ?>

					<li><button id="open-btn" class="iconbtn" title="Загрузить существующую категорию предметов и кабинетов"></button></li>

					<?php if ($write) { ?>
						<li><button id="save-btn" class="iconbtn" title="Сохранить категорию предметов и кабинетов"></button></li>
						<li><button id="delete-btn" class="iconbtn" title="Удалить категорию предметов и кабинетов"></button></li>
					<?php } ?>
				</ul>
			</div>

			<div id="message-container"></div>

			<div id="editor-info">
				<div>
					<label for="editor-info-name">Наименование: </label>
					<input type="text" id="editor-info-name" required>

					<label for="editor-info-abbr">Сокращение: </label>
					<input type="text" id="editor-info-abbr" required>
				</div>
			</div>

			<div id="objects-info">
				<table id="subjects-table">
					<thead>
						<tr><th>Предметы, входящие в категорию</th></tr>
					</thead>
					<tbody></tbody>
				</table>
				<table id="rooms-table">
					<thead>
						<tr><th>Кабинеты, входящие в категорию</th></tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</main>

		<?php JavaScript::LoadFile("subjectroomcategory"); ?>
	</body>
</html>