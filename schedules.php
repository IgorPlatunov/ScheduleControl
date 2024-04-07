<?php
	use ScheduleControl\UserConfig;

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

		<title>Редактор расписаний занятий</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("schedules"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span>Редактор расписаний занятий</span>
				<ul class="iconbtn-list">
					<?php if ($write) { ?>
						<li><button id="add-btn" class="iconbtn" title="Добавить учебную группу в расписание..."></button></li>
						<li><button id="clear-btn" class="iconbtn" title="Очистить расписание занятий"></button></li>
					<?php } ?>

					<li><button id="open-btn" class="iconbtn" title="Загрузить существующее расписание по дате..."></button></li>
					<li><button id="view-btn" class="iconbtn" title="Количество расписаний в строке 2/3"></button></li>
					<li><button id="export-btn" class="iconbtn" title="Экспорт текущего расписания..."></button></li>

					<?php if ($write) { ?>
						<li><button id="update-btn" class="iconbtn" title="Сконструировать расписание автоматически"></button></li>
						<li><button id="configure-btn" class="iconbtn" title="Конфигурация конструктора расписаний..."></button></li>
						<li><button id="validate-btn" class="iconbtn" title="Проверить расписание на корректность"></button></li>
						<li><button id="save-btn" class="iconbtn" title="Сохранить расписание"></button></li>
						<li><button id="delete-btn" class="iconbtn" title="Удалить расписание"></button></li>
					<?php } ?>
				</ul>
			</div>
			<div id="message-container"></div>
			<div id="editor-info">
				<span>
					<label id="editor-info-bells-label" for="editor-info-bells">Расписание звонков: </label>
				</span>

				<span>
					<label for="editor-info-date">Дата расписания: </label>
					<input type="date" id="editor-info-date" value="<?php echo date_format(new DateTime(), "Y-m-d") ?>" required>
				</span>
			</div>
			<div id="schedule-container" data-starthour="<?php echo UserConfig::GetParameter("ScheduleStartReserveHour"); ?>"></div>
		</main>

		<?php JavaScript::LoadFile("schedules"); ?>
	</body>
</html>