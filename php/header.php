<?php
	use ScheduleControl\Core;

	function GetAccountRoleName(): string
	{
		$user = Session::CurrentUser();
		$roles = $user->GetRoles();
		$main = isset($roles[0]) ? Core\UserRoles::GetNiceName($roles[0]) : "Нет ролей";
		$additional = count($roles) > 1 ? "<br>Ролей: ".count($roles) : "";

		return $main.$additional;
	}

	function GetAvailableEditors(): array
	{
		$editors = array(
			array("CurriculumRead", "CurriculumWrite", "Учебные планы", "Создание новых учебных планов, удаление и редактированиие действующих учебных планов", "curriculums.php"),
			array("YearGraphRead", "YearGraphWrite", "Графики образовательного процесса", "Формирование графиков образовательного процесса на год по группам", "yeargraphs.php"),
			array("YearLoadRead", "YearLoadWrite", "Нагрузки учебных групп", "Создание новых нагрузок групп на год, удаление и редактирование имеющихся нагрузок групп", "yearloads.php"),
			array("LoadSubjectLecturerRead", "LoadSubjectLecturerWrite", "Закрепления преподавателей и предметов", "Назначение закреплений преподавателей за предметами для ведения занятий", "loadsubjectlecturers.php"),
			array("SemesterScheduleRead", "SemesterScheduleWrite", "Расписания на семестр", "Составление постоянного недельного расписания на семестр", "semesterschedules.php"),
			array("ScheduleRead", "ScheduleWrite", "Расписания занятий", "Составление конечных расписаний занятий на конкретные даты", "schedules.php"),
			array("ScheduleRead", "ScheduleWrite", "Отсутствия преподавателей", "Внесение информации об отсутствиях преподавателей в конкретные периоды", "unavlecturers.php"),
			array("ScheduleRead", "ScheduleWrite", "Недоступности кабинетов", "Внесение информации о недоступностях кабинетов в конкретные периоды", "unavrooms.php"),
			array("ScheduleRead", "ScheduleWrite", "Категории предметов и кабинетов", "Распределение предметов и кабинетов по категориям для создания соответствий между ними", "subjectroomcategory.php"),
		);

		$aveditors = array();

		foreach ($editors as $editor)
			if (Session::HasAccess($editor[0]))
				$aveditors[] = $editor;

		return $aveditors;
	}

	function GetRegistryCollections(): array
	{
		$collections = array();

		foreach (Core\DBTableRegistryCollections::GetCollections() as $name => $collection)
			if (!$collection->IsInternal() || $collection->HasAccess(Session::CurrentUser()))
				$collections[] = array($name, $collection->GetTable()->GetInfo()->GetNiceName(), match(true) {
					$collection->IsInternal() => 2,
					$collection->HasAccess(Session::CurrentUser()) => 0,
					default => 1,
				});

		usort($collections, function($a, $b) {
			if ($a[2] == $b[2])
				return $a[1] <=> $b[1];

			return $a[2] <=> $b[2];
		});

		return $collections;
	}
?>

<header>
	<ul id="header-menu">
		<li><a href="index.php">Главная</a></li>

		<li>
			<span>Статистика</span>
			<ul>
				<li><a href="statisticgroups.php">Статистика по учебным группам</a></li>
				<li><a href="statisticlecturers.php">Статистика по преподавателям</a></li>
			</ul>
		</li>

		<?php if (count($aveditors = GetAvailableEditors()) > 0) { ?>
		<li>
			<span>Редакторы</span>
			<ul>
				<?php foreach ($aveditors as $editor) { ?>

					<li class="header-menu-li-icon header-editor-<?php echo Session::HasAccess($editor[1]) ? "write" : "read"; ?>">
						<a href="<?php echo $editor[4]; ?>" title="<?php echo $editor[3]; ?>"><?php echo $editor[2]; ?></a>
					</li>

				<?php } ?>
			</ul>
		</li>
		<?php } ?>

		<li>
			<span>Регистры</span>
			<ul>
				<?php foreach (GetRegistryCollections() as $hcollection) { ?>
					<li class="header-menu-li-icon header-registry-type-<?php echo $hcollection[2]; ?>">
						<a href="collections.php?collection=<?php echo $hcollection[0]; ?>"><?php echo $hcollection[1]; ?></a>
					</li>
				<?php } ?>
			</ul>
		</li>

		<li>
			<span>Время данных</span>
			<ul id="header-actdate-container">
				<div>Актуальное время изменений данных</div>

				<div id="header-actdate-info">
					<input type="datetime-local" id="header-actdate" name="date" step="1">

					<span>
						<label type="text" for="header-actdate-auto">Авто</label>
						<input type="checkbox" id="header-actdate-auto">
					</span>
				</div>
			</ul>
		</li>

		<li>
			<span>Масштаб</span>
			<ul id="header-scale-container">
				<div>Масштаб интерфейса</div>

				<div id="header-scale-info">
					<input type="range" min="50" max="200" step="5" value="100" id="header-scale">
					<label id="header-scale-label" for="header-scale">100%</label>
				</div>
			</ul>
		</li>
	</ul>

	<span id="header-account">
		<span id="header-account-info">
			<?php echo Session::CurrentUser()->GetLogin(); ?>	
			<br>
			<?php echo GetAccountRoleName(); ?>
		</span>
		
		<ul id="header-account-btns">
			<li>
				<form method="POST" action="index.php">
					<input type="hidden" name="type" value="logout">
					<input type="submit" id="header-logout-btn" class="iconbtn header-account-btn" title="Выход" value="">
				</form>
			</li>
			<li><a href="settings.php" id="header-settings-btn" class="iconbtn header-account-btn" title="Настройки"></a></li>
		</ul>
	</span>
</header>

<?php JavaScript::LoadFile("header"); ?>