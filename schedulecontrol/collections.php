<?php
	use ScheduleControl\Core\{DBTableRegistryCollections as Collections};

	require_once("php/session.php");

	$fkey = isset($_GET["fkeyselect"]);

	Session::Begin();
	if (Session::CurrentUser() === null) Session::DropUser($fkey);

	require_once("php/themes.php");
	require_once("php/scripts.php");

	$colname = $_GET["collection"] ?? null;
	if (!isset($colname)) Session::DropUser($fkey);

	$collection = Collections::GetCollection($colname);
	if (!isset($collection)) Session::DropUser($fkey);

	$table = $collection->GetTable();
	$access = $collection->HasAccess(Session::CurrentUser());
	$config = $colname == "Config";
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico">

		<title>Редактор регистров: <?php echo $table->GetInfo()->GetNiceName(); ?></title>

		<?php Themes::LoadThemeFile("shared"); ?>
		<?php if (!$fkey) Themes::LoadThemeFile("header"); ?>
		<?php Themes::LoadThemeFile("collections"); ?>
	</head>
	<body>
		<?php if (!$fkey) include("php/header.php"); ?>

		<main>
			<div id="page-header">
				<span id="collection-name" data-name="<?php echo $colname; ?>">Коллекция регистров <?php echo $table->GetInfo()->GetNiceName(); ?></span>
				<ul id="collection-actions" class="iconbtn-list">
					<?php if ($access) { ?>
						<li><button class="iconbtn" title="Сохранить выделенное" id="collection-actions-save"></button></li>
						<?php if (!$config) { ?><li><button class="iconbtn" title="Добавить новую строку" id="collection-actions-addrow"></button></li> <?php } ?>
						<li><button class="iconbtn" title="Показать/скрыть ключевые поля" id="collection-actions-pkeys"></button></li>
					<?php } ?>

					<li><button class="iconbtn" title="Отображение список/таблица" id="collection-actions-view" data-view="<?php echo ($access && !$fkey) ? "table" : "list"; ?>"></button></li>
					<li><button class="iconbtn" title="Расширение таблицы" id="collection-actions-width"></button></li>
					<li><button class="iconbtn" title="Обновить данные" id="collection-actions-update"></button></li>
				</span>
			</div>
			<div id="collection-options">
				<span>
					<?php if ($fkey) { ?>
						<label for="collection-options-ignorespecfilter">Игнор. спец. фильтров: </label>
						<input type="checkbox" id="collection-options-ignorespecfilter">
					<?php } ?>
					<label for="collection-options-limit">Макс. кол-во строк на страницу: </label>
					<select id="collection-options-limit">
						<option>10</option>
						<option>25</option>
						<option>50</option>
						<option selected>100</option>
						<option>200</option>
						<option>500</option>
					</select>
					<label for="collection-options-page">Страница: </label>
					<input type="number" id="collection-options-page" min="1" value="1">
				</span>
			</div>
			<div id="message-container"></div>

			<table id="collection-table" class="table-width" <?php if ($fkey) { ?> data-fkey="1" <?php } ?>>
				<colgroup><col id="collection-table-colgroup-actions"></colgroup>
				<colgroup><col id="collection-table-colgroup-name"></colgroup>
				<colgroup>
					<?php foreach ($table->GetColumns() as $name => $column) { ?>
						<col class="collection-table-colgroup-column">
					<?php } ?>
				</colgroup>
				<thead>
					<tr id="collection-table-columns">
						<th scope="col">Действия</th>
						<th scope="col" id="collection-table-object">
							<div>
								<span>Объект</span>
								<button class="collection-table-sort-btn" id="collection-table-sort-object">▬</button>
							</div>
						</th>
						<?php foreach ($table->GetColumns() as $name => $column) { ?>
							<th scope="col" data-column="<?php echo $name; ?>" <?php if (!$collection->IsDataColumn($name)) { ?> data-pkey="1" <?php } ?>>
								<div>
									<span><?php echo $column->GetNiceName(); ?></span>
									<button class="collection-table-sort-btn" data-column="<?php echo $name; ?>">▬</button>
								</div>
							</th>
						<?php } ?>
					</tr>
					<tr id="collection-table-filters">
						<th scope="col">
							<div class="collection-table-row-actions">
								<input type="checkbox" id="collection-table-actions-select" class="iconbtn collection-table-row-select" title="Выбрать все строки">
								<?php if ($access && !$config) { ?>
									<button id="collection-table-actions-delete" class="iconbtn collection-table-row-delete" title="Пометить на удаление всё"></button>
								<?php } ?>
								<button id="collection-table-actions-hide" class="iconbtn collection-table-row-hide" title="Скрыть выделенные строки"></button>
								<?php if ($access && !$config) { ?>
									<button id="collection-table-actions-copy" class="iconbtn collection-table-row-copy" title="Скопировать выделенные строки"></button>
								<?php } ?>
							</div>
						</th>
						<td scope="col">
							<input type="text" class="collection-table-filter" id="collection-table-search" placeholder="Поиск объектов">
						</td>
						<?php foreach ($table->GetColumns() as $name => $column) { ?>
							<td scope="col">
								<input type="text" class="collection-table-filter" data-column="<?php echo $name; ?>" placeholder="Фильтр по полю <?php echo $column->GetNiceName(); ?>" value="<?php echo $_GET["filter-$name"] ?? ""; ?>">
							</td>
						<?php } ?>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</main>

		<?php JavaScript::LoadFile("collections"); ?>
	</body>
</html>