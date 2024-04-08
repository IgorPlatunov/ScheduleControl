<?php
	require_once("php/session.php");

	Session::Begin();

	if (Session::CurrentUser() === null || !Session::CurrentUser()->HasPrivilege("YearGraphRead"))
		Session::DropUser();

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$write = Session::CurrentUser()->HasPrivilege("YearGraphWrite");
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Редактор графиков образовательного процесса</title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("yeargraphs"); ?>
		<?php Themes::LoadThemeFile("yearactivities"); ?>
	</head>
	<body>
		<?php include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span>Редактор графиков образовательного процесса</span>
				<ul class="iconbtn-list">
					<li><button id="open-btn" class="iconbtn" title="Загрузить существующий график по году..."></button></li>
					<li><button id="export-btn" class="iconbtn" title="Экспорт текущего графика образовательного процесса..."></button></li>

					<?php if ($write) { ?>
						<li><button id="update-btn" class="iconbtn" title="Обновить группы по учебным планам"></button></li>
						<li><button id="clear-btn" class="iconbtn" title="Очистить деятельности групп"></button></li>
						<li><button id="validate-btn" class="iconbtn" title="Проверить график на корректность"></button></li>
						<li><button id="save-btn" class="iconbtn" title="Сохранить график"></button></li>
						<li><button id="delete-btn" class="iconbtn" title="Удалить график"></button></li>
					<?php } ?>
				</ul>
			</div>
			<div id="editor-info">
				<span>
					<label for="editor-info-year">Год: </label>
					<input type="number" id="editor-info-year" min="2000" max="3000" placeholder="Учебный год (относительно 1 сентября)" value="<?php echo date_format(new DateTime(), "Y"); ?>">
				</span>
			</div>
			<div id="graph-container"></div>
			<div id="message-container"></div>
			<table id="discrepancy-table" hidden>
				<thead>
					<tr>
						<th colspan="6">
							Несоответствия деятельностей учебным планам
							<button id="discrepancy-table-hide">X</button>
						</th>
					</tr>
					<tr>
						<th>№</th>
						<th>Группа</th>
						<th>Деятельность</th>
						<th>Длительность требуемая</th>
						<th>Длительность текущая</th>
						<th>Суммарное различие</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</main>

		<?php JavaScript::LoadFile("yearactivities"); ?>
		<?php JavaScript::LoadFile("yeargraphs"); ?>
	</body>
</html>