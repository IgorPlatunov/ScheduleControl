<?php
	require_once("php/session.php");

	Session::Begin();

	if (Session::CurrentUser() === null || !Session::CurrentUser()->HasPrivilege("LoadSubjectLecturerRead"))
		Session::DropUser();

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$write = Session::CurrentUser()->HasPrivilege("LoadSubjectLecturerWrite");
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Редактор закреплений преподавателей и предметов нагрузки</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("loadsubjectlecturers"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span>Редактор закреплений преподавателей за предметами нагрузки групп</span>
				<ul class="iconbtn-list">
					<li><button id="open-btn" class="iconbtn" title="Загрузить существующие закрепления из нагрузки групп по году..."></button></li>

					<?php if ($write) { ?>
						<li><button id="save-btn" class="iconbtn" title="Сохранить измененные закрепления"></button></li>
					<?php } ?>
				</ul>
				<span id="loads-container">
					<label for="loads">Нагрузка группы: </label>
					<select id="loads"></select>
				</span>
			</div>
			<div id="message-container"></div>
			<table id="load-data-table">
				<colgroup>
					<col span="2">
				</colgroup>
				<colgroup>
					<col span="2">
					<col span="2">
				</colgroup>
				<thead>
					<tr>
						<th rowspan="2">Предмет нагрузки</th>
						<th colspan="4">Закрепленные преподаватели</th>
					</tr>
					<tr>
						<th colspan="2">Семестр 1</th>
						<th colspan="2">Семестр 2</th>
					</tr>
					<tr>
						<td>
							<input type="text" placeholder="Фильтр по предметам" id="load-filter">
						</td>
						<th>Преподаватель</th>
						<th class="subject-lecturers-main-cell">Основной</th>
						<th>Преподаватель</th>
						<th class="subject-lecturers-main-cell">Основной</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>

		<?php JavaScript::LoadFile("loadsubjectlecturers"); ?>
	</body>
</html>