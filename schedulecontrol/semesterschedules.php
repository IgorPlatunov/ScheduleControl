<?php
	use ScheduleControl\UserConfig;

	require_once("php/session.php");

	Session::Begin();

	if (Session::CurrentUser() === null || !Session::CurrentUser()->HasPrivilege("SemesterScheduleRead"))
		Session::DropUser();

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$write = Session::CurrentUser()->HasPrivilege("SemesterScheduleWrite");
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Редактор расписаний на семестр</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("semesterschedules"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span>Редактор расписаний на семестр</span>
				<ul class="iconbtn-list">
					<?php if ($write) { ?>
						<li><button id="add-btn" class="iconbtn" title="Добавить нагрузку группы в расписание..."></button></li>
						<li><button id="clear-btn" class="iconbtn" title="Очистить расписание на семестр"></button></li>
					<?php } ?>
					
					<li><button id="open-btn" class="iconbtn" title="Загрузить существующее расписание по году и семестру..."></button></li>
					<li><button id="export-btn" class="iconbtn" title="Экспорт текущего расписания на семестр..."></button></li>

					<?php if ($write) { ?>
						<li><button id="update-btn" class="iconbtn" title="Сконструировать расписание на семестр автоматически"></button></li>
						<li><button id="configure-btn" class="iconbtn" title="Конфигурация конструктора расписаний..."></button></li>
						<li><button id="validate-btn" class="iconbtn" title="Проверить расписание на семестр на корректность"></button></li>
						<li><button id="save-btn" class="iconbtn" title="Сохранить расписание на семестр"></button></li>
						<li><button id="delete-btn" class="iconbtn" title="Удалить расписание на семестр"></button></li>
					<?php } ?>
				</ul>
			</div>
			<div id="message-container"></div>
			<table id="unalloc-pairs-table" hidden>
				<thead>
					<tr>
						<th colspan="3">
							Нераспределенные предметы нагрузок групп
							<button id="unalloc-pairs-table-close">✕</button>
						</th>
					</tr>
					<tr>
						<th>Нагрузка группы</th>
						<th>Предмет нагрузки</th>
						<th>Остаток часов для распределения</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
			<div id="editor-info">
				<span>
					<div>
						<label for="editor-info-start">Начало действия: </label>
						<input type="date" id="editor-info-start" required>
					</div>
					<div>
						<label for="editor-info-end">Окончание действия: </label>
						<input type="date" id="editor-info-end" required>
					</div>
					<div>
						<label for="editor-info-wday">День недели: </label>
						<select id="editor-info-wday" value="0">
							<option value="0">Понедельник</option>
							<option value="1">Вторник</option>
							<option value="2">Среда</option>
							<option value="3">Четверг</option>
							<option value="4">Пятница</option>
							<option value="5">Суббота</option>
							<option value="6">Воскресенье</option>
						</select>
					</div>
				</span>

				<span>
					<div>
						<label for="editor-info-year">Год: </label>
						<input type="number" id="editor-info-year" min="2000" max="3000" placeholder="Учебный год (относительно 1 сентября)" value="<?php echo date_format(new DateTime(), "Y"); ?>" required>
					</div>
					<div>
						<label for="editor-info-semester">Семестр: </label>
						<select id="editor-info-semester" value="1">
							<option value="1">Первый</option>
							<option value="2">Второй</option>
						</select>
					</div>
				</span>
			</div>
			<div id="schedule-container" data-startpair="<?php echo ceil(UserConfig::GetParameter("ScheduleStartReserveHour") / 2); ?>"></div>
		</main>

		<?php JavaScript::LoadFile("semesterschedules"); ?>
	</body>
</html>