<?php
	require_once("php/session.php");

	Session::Begin();

	if (Session::CurrentUser() === null || !Session::CurrentUser()->HasPrivilege("YearLoadRead"))
		Session::DropUser();

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$write = Session::CurrentUser()->HasPrivilege("YearLoadWrite");
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Редактор нагрузок учебных групп</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("yearloads"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span>Редактор нагрузок учебных групп на год</span>
				<ul class="iconbtn-list">
					<?php if ($write) { ?>
						<li><button id="add-btn" class="iconbtn" title="Добавить новую нагрузку группы..."></button></li>
						<li><button id="remove-btn" class="iconbtn" title="Удалить текущую нагрузку группы"></button></li>
						<li><button id="clear-btn" class="iconbtn" title="Очистить и начать создание новой нагрузки на год"></button></li>
					<?php } ?>

					<li><button id="open-btn" class="iconbtn" title="Загрузить существующую нагрузку по году..."></button></li>
					<li><button id="export-btn" class="iconbtn" title="Экспорт текущей нагрузки на год..."></button></li>

					<?php if ($write) { ?>
						<li><button id="update-btn" class="iconbtn" title="Сформировать нагрузку из учебных планов"></button></li>
						<li><button id="save-btn" class="iconbtn" title="Сохранить нагрузку на год"></button></li>
						<li><button id="delete-btn" class="iconbtn" title="Удалить нагрузку на год"></button></li>
					<?php } ?>
				</ul>
			</div>
			<div id="message-container"></div>
			<div id="groups-and-info">
				<div id="editor-info">
					<div>
						<label for="editor-info-year">Год: </label>
						<input type="number" id="editor-info-year" min="2000" max="3000" placeholder="Учебный год (относительно 1 сентября)" value="<?php echo date_format(new DateTime(), "Y"); ?>" required>
					</div>
					<div>
						<label for="editor-info-loads">Нагрузка группы: </label>
						<select id="editor-info-loads"></select>
					</div>
					<div>
						<label for="editor-info-name">Наименование нагрузки: </label>
						<input type="text" id="editor-info-name" required>
					</div>
				</div>
				<table id="groups-table">
					<thead>
						<tr><th>Группы, входящие в нагрузку</th></tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
			<table id="load-data-table">
				<colgroup>
					<col>
					<col span="3">
				</colgroup>
				<colgroup>
					<col span="6">
					<col span="6">
				</colgroup>
				<thead>
					<tr>
						<th colspan="16">Данные по предметам, входящих в нагрузку</th>
					</tr>
					<tr>
						<th rowspan="2">Предмет</th>
						<th colspan="3">Уточнения</th>
						<th colspan="12">Количество часов по семестрам</th>
					</tr>
					<tr>
						<th rowspan="2">Наименование</th>
						<th rowspan="2">Сокращение</th>
						<th rowspan="2">Выставлять 1 час по возможности</th>
						<th colspan="6" class="load-semester1">1</th>
						<th colspan="6" class="load-semester2">2</th>
					</tr>
					<tr>
						<td>
							<input type="text" placeholder="Фильтр по предметам" id="load-filter">
						</td>
						<th class="load-semester1">Всего часов</th>
						<th class="load-semester1">Часов в неделю</th>
						<th class="load-semester1">Часов на ЛПЗ</th>
						<th class="load-semester1">Часов на КП</th>
						<th class="load-semester1">Часов на ЗКП</th>
						<th class="load-semester1">Часов на Э</th>
						<th class="load-semester2">Всего часов</th>
						<th class="load-semester2">Часов в неделю</th>
						<th class="load-semester2">Часов на ЛПЗ</th>
						<th class="load-semester2">Часов на КП</th>
						<th class="load-semester2">Часов на ЗКП</th>
						<th class="load-semester2">Часов на Э</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</main>

		<?php JavaScript::LoadFile("yearloads"); ?>
	</body>
</html>