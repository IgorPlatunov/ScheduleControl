<?php
	require_once("php/session.php");

	Session::Begin();
	if (Session::CurrentUser() === null) Session::DropUser();

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$statisticSubjectColumns = array(
		array("Предмет", "fullname", "%s", array("name", "fullname"), true),
		array("Группа", "fullname", "%s", array("name", "fullname")),
		array("Всего часов", "hours", "%s"),
		array("Часов вычитано", "passedhours", "%s"),
		array("Часов осталось", "lefthours", "%s"),
		array("Процент вычитки", "percent", "%s%"),
	);
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Статистика по преподавателям</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("statisticlecturers"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>
		
		<main>
			<div id="page-header">
				<span>Статистика по преподавателям</span>
				<ul class="iconbtn-list">
					<li><button id="update-btn" class="iconbtn" title="Обновить статистику преподавателя"></button></li>
					<li><button id="export-btn" class="iconbtn" title="Экспорт статистики преподавателя..."></button></li>
				</ul>
				<span>
					<label for="lecturer-date">Дата: </label>
					<input type="date" id="group-date" value="<?php echo (new DateTime())->format("Y-m-d"); ?>" required>

					<label for="lecturer">Преподаватель: </label>
					<span id="lecturer-container"></span>
				</span>
			</div>

			<div id="message-container"></div>

			<table id="lecturer-load">
				<thead>
					<tr>
						<th colspan="18">
							<div>
								<span>Нагрузка преподавателя на год</span>
								<button id="lecturer-load-collapse" title="Свернуть/развернуть">▼</button>
							</div>
						</th>
					</tr>
					<tr>
						<th rowspan="3">Предмет</th>
						<th rowspan="3">Группа</th>
						<th colspan="4">Количество часов</th>
						<th colspan="12">Часы по нагрузке</th>
					</tr>
					<tr>
						<th rowspan="2">По плану</th>
						<th rowspan="2">По нагрузке</th>
						<th colspan="2">Снято</th>
						<th colspan="6">Семестр 1</th>
						<th colspan="6">Семестр 2</th>
					</tr>
					<tr>
						<th>Семестр 1</th>
						<th>Семестр 2</th>
						<th>Всего</th>
						<th>В неделю</th>
						<th>На ЛПЗ</th>
						<th>На КП</th>
						<th>На ЗКП</th>
						<th>На Э</th>
						<th>Всего</th>
						<th>В неделю</th>
						<th>На ЛПЗ</th>
						<th>На КП</th>
						<th>На ЗКП</th>
						<th>На Э</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>

			<table id="subjects-info">
				<thead>
					<tr>
						<th colspan="<?php echo count($statisticSubjectColumns); ?>">Статистика по вычитке предметов за семестр</th>
					</tr>
					<tr>
						<?php foreach ($statisticSubjectColumns as $id => $column) { ?>
							<th <?php if (!isset($column[3])) {?> rowspan="2" <?php } ?>>
								<div>
									<span><?php echo $column[0]; ?></span>
									<button class="subject-sort-btn" data-id="<?php echo $id; ?>" data-value="<?php echo $column[1]; ?>" data-format="<?php echo $column[2]; ?>" <?php if (isset($column[3])) { ?> data-filter="<?php echo implode(";", $column[3]); ?>" <?php } ?> <?php if (isset($column[4]) && $column[4]) { ?> data-subject="1" <?php } ?>>▬</button>
								</div>
							</th>
						<?php } ?>
					</tr>
					<tr>
						<?php foreach ($statisticSubjectColumns as $id => $column) { if (isset($column[3])) { ?>
							<td>
								<input type="text" placeholder="Фильтр по полю <?php echo $column[0]; ?>" class="subject-filter" data-id="<?php echo $id; ?>">
							</td>
						<?php } } ?>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</main>

		<?php JavaScript::LoadFile("statisticlecturers"); ?>
	</body>
</html>