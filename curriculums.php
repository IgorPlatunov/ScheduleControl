<?php
	require_once("php/session.php");

	Session::Begin();

	if (Session::CurrentUser() === null || !Session::CurrentUser()->HasPrivilege("CurriculumRead"))
		Session::DropUser();

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$write = Session::CurrentUser()->HasPrivilege("CurriculumWrite");
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Редактор учебных планов</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("curriculums"); ?>
		<?php Themes::LoadThemeFile("yearactivities"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span>Редактор учебных планов</span>
				<ul class="iconbtn-list">
					<?php if ($write) { ?>
						<li><button id="add-btn" class="iconbtn" title="Добавить новый курс в план"></button></li>
						<li><button id="remove-btn" class="iconbtn" title="Удалить текущий курс из плана"></button></li>
						<li><button id="clear-btn" class="iconbtn" title="Очистить и начать создание нового учебного плана"></button></li>
						<li><button id="copy-btn" class="iconbtn" title="Создать копию текущего учебного плана"></button></li>
					<?php } ?>

					<li><button id="open-btn" class="iconbtn" title="Загрузить существующий учебный план..."></button></li>

					<?php if ($write) { ?>
						<li><button id="save-btn" class="iconbtn" title="Сохранить учебный план"></button></li>
						<li><button id="delete-btn" class="iconbtn" title="Удалить учебный план"></button></li>
					<?php } ?>
				</ul>
			</div>

			<div id="message-container"></div>

			<div id="editor-info">
				<div>
					<label for="editor-info-course">Текущий курс: </label>
					<select id="editor-info-course"></select>

					<label for="editor-info-course2">Обучение со 2 курса: </label>
					<input type="checkbox" id="editor-info-course2">
				</div>
				<div>
					<label for="editor-info-name">Наименование: </label>
					<input type="text" id="editor-info-name" required>

					<label for="editor-info-year">Год начального курса: </label>
					<input type="number" id="editor-info-year" min="2000" max="3000" required value="<?php echo (new DateTime())->format("Y"); ?>">
					
					<label for="editor-info-qualification">Специальность: </label>
					<span id="editor-info-qualif-container"></span>

					<label for="editor-info-educationlevel">Уровень образования: </label>
					<span id="editor-info-edlevel-container"></span>
				</div>
			</div>
			
			<div id="budget-info">
				<table id="budget-table">
					<thead>
						<tr>
							<th colspan="3">Данные по бюджету времени</th>
						</tr>
						<tr>
							<th>Учебная деятельность</th>
							<th>Количество недель на выбранный курс</th>
							<th>Длительность</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
				<table id="budget-designations">
					<thead>
						<tr>
							<th colspan="2">Условные обозначения деятельностей</th>
						</tr>
						<tr>
							<th>Наименование</th>
							<th>Сокращение</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>

			<div id="graph-container"></div>

			<table id="subjects-table">
				<thead>
					<tr>
						<th colspan="11">План образовательного процесса</th>
					</tr>
					<tr>
						<th rowspan="2">Предмет</th>
						<th colspan="10">Распределение часов на выбранный курс</th>
					</tr>
					<tr>
						<th colspan="5">Семестр 1</th>
						<th colspan="5">Семестр 2</th>
					</tr>
					<tr>
						<td>
							<input type="text" placeholder="Фильтр по предметам" id="subjects-filter">
						</td>
						<th>Всего часов</th>
						<th>Часов в неделю</th>
						<th>Часов на ЛПЗ</th>
						<th>Часов на КП</th>
						<th>Экзамен</th>
						<th>Всего часов</th>
						<th>Часов в неделю</th>
						<th>Часов на ЛПЗ</th>
						<th>Часов на КП</th>
						<th>Экзамен</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</main>

		<?php JavaScript::LoadFile("yearactivities"); ?>
		<?php JavaScript::LoadFile("curriculums"); ?>
	</body>
</html>