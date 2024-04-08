<?php
	require_once("php/session.php");

	Session::Begin();
	if (Session::CurrentUser() === null) Session::DropUser();

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$statisticSubjectColumns = array(
		array("Предмет", "fullname", "%s", array("name", "fullname")),
		array("Всего часов", "hours", "%s"),
		array("Часов вычитано", "passedhours", "%s"),
		array("Часов осталось", "lefthours", "%s"),
		array("Процент вычитки", "percent", "%s%"),
		array("Расчётное кол-во часов в день", "hoursperday", "%s"),
	);
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Статистика по учебным группам</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("statisticgroups"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>
		
		<main>
			<div id="page-header">
				<span>Статистика по учебным группам</span>
				<ul class="iconbtn-list">
					<li><button id="update-btn" class="iconbtn" title="Обновить статистику группы"></button></li>
					<li><button id="export-btn" class="iconbtn" title="Экспорт статистики группы..."></button></li>
				</ul>
				<span>
					<label for="group-date">Дата: </label>
					<input type="date" id="group-date" value="<?php echo (new DateTime())->format("Y-m-d"); ?>" required>

					<label for="group">Группа: </label>
					<span id="group-container"></span>
				</span>
			</div>

			<div id="message-container"></div>

			<table id="general-info">
				<thead>
					<tr>
						<th colspan="2">
							<div>
								<span>Общая информация о группе</span>
								<button id="general-info-collapse" title="Свернуть/развернуть">▼</button>
							</div>
						</th>
					</tr>
					<tr>
						<th>Критерий</th>
						<th>Значение</th>
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
									<button class="subject-sort-btn" data-id="<?php echo $id; ?>" data-value="<?php echo $column[1]; ?>" data-format="<?php echo $column[2]; ?>" <?php if (isset($column[3])) { ?> data-filter="<?php echo implode(";", $column[3]); ?>" <?php } ?>>▬</button>
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

		<?php JavaScript::LoadFile("statisticgroups"); ?>
	</body>
</html>