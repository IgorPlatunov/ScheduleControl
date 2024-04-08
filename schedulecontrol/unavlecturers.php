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

		<title>Редактор отсутствий преподавателей</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("unavlecturers"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span>Редактор отсутствий преподавателей</span>
				<ul class="iconbtn-list">
					<?php if ($write) { ?>
						<li><button id="add-btn" class="iconbtn" title="Добавить новое отсутствие преподавателя"></button></li>
					<?php } ?>

					<li><button id="update-btn" class="iconbtn" title="Обновить список отсутствий преподавателя"></button></li>
				</ul>
				<span id="lecturer-container">
					<label for="lecturer">Преподаватель: </label>
				</span>
			</div>
			<div id="message-container"></div>

			<?php if ($write) { ?>
				<section id="new-unav">
					<h2>Добавить новое отсутствие</h2>

					<div id="new-unav-container">
						<label for="new-unav-from">Действительно с: </label>
						<input type="datetime-local" id="new-unav-from" required>

						<label for="new-unav-to">Действительно по: </label>
						<input type="datetime-local" id="new-unav-to" required>

						<label for="new-unav-period">Периодичное</label>
						<input type="checkbox" id="new-unav-period">

						<label for="new-unav-comment">Комментарий: </label>
						<input type="text" id="new-unav-comment">
					</div>

					<div id="new-unav-period-container" hidden>
						<label for="new-unav-period-length">Периодичность: </label>
						<select id="new-unav-period-length" value="0">
							<option value="0">Каждую неделю</option>
							<option value="1">Каждый день</option>
							<option value="2">Указать вручную</option>
						</select>

						<label for="new-unav-period-custom" hidden>Периодичность (в часах): </label>
						<input type="number" id="new-unav-period-custom" min="1" required hidden>
						
						<label for="new-unav-period-first">Начало первого отсутствия: </label>
						<input type="datetime-local" id="new-unav-period-first" required>

						<label for="new-unav-period-first-end">Окончание первого отсутствия: </label>
						<input type="datetime-local" id="new-unav-period-first-end" required>
					</div>
				</section>
			<?php } ?>

			<section>
				<h2>Список отсутствий преподавателя</h2>

				<table id="unavs-table">
					<thead>
						<tr>
							<th>№</th>
							<th>Количество</th>
							<th>Начало</th>
							<th>Окончание</th>
							<th>Комментарий</th>
						</tr>
					</thead>
					<tbody id="unavs-table-body"></tbody>
				</table>
			</section>
		</main>

		<?php JavaScript::LoadFile("unavlecturers"); ?>
	</body>
</html>