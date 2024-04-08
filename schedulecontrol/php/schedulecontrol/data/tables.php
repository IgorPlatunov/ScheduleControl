<?php
	namespace ScheduleControl\Data;
	use ScheduleControl\{Core as Core, Core\DBTableRegistryCollections as Collections};

	$f = array();

	function CreateTableCollection($tabname, $nicename, $columns, $ids, $date, $tabcreate, $internal, $privileges)
	{
		global $f;

		$t = new Core\DBTableInfo($tabname, $nicename);
		$c = array();
		$idcs = array();

		foreach ($columns as $column) $c[$column->GetName()] = $column;
		foreach (is_array($ids) ? $ids : array($ids) as $id) $idcs[] = $c[$id];

		$tab = $tabcreate($t, $c);

		$f[$tabname] = array();
		foreach ($c as $name => $column) $f[$tabname][$name] = new Core\DBTableForeignKeyInfo($column);

		Collections::RegisterCollection($tabname, $tab, $idcs, $c[$date], $internal, $privileges);
	}

	CreateTableCollection("Qualifications", "Специальности", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Name", "Наименование", 80),
		new TextDBTableColumnInfo("Abbreviation", "Сокращение", 20),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Abbreviation"], $c["Name"]);
	}, false, "CurriculumWrite");

	CreateTableCollection("Areas", "Площадки", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Name", "Наименование", 60),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Name"], null);
	}, false, array("ScheduleWrite", "LoadSubjectLecturerWrite"));

	CreateTableCollection("Rooms", "Кабинеты", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Area", "Площадка", $f["Areas"]["ID"]),
		new TextDBTableColumnInfo("Name", "Наименование", 80),
		new TextDBTableColumnInfo("Number", "Номер", 20),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Number"], $c["Name"]);
	}, false, array("ScheduleWrite", "LoadSubjectLecturerWrite"));

	CreateTableCollection("Subjects", "Предметы", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Name", "Наименование", 80),
		new TextDBTableColumnInfo("Abbreviation", "Сокращение", 30),
		new BoolDBTableColumnInfo("Practic", "Практика"),
		new BoolDBTableColumnInfo("Optional", "Факультатив"),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Abbreviation"], $c["Name"]);
	}, false, array("CurriculumWrite", "YearLoadWrite"));

	CreateTableCollection("Lecturers", "Преподаватели", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Surname", "Фамилия", 30),
		new TextDBTableColumnInfo("Name", "Имя", 30),
		new TextDBTableColumnInfo("Patronymic", "Отчество", 30),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableUnique($t, $c, DBTableRowLecturer::class);
	}, false, "LoadSubjectLecturerWrite");

	CreateTableCollection("Activities", "Деятельности", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Name", "Наименование", 80),
		new TextDBTableColumnInfo("Abbreviation", "Сокращение", 30),
		new TextDBTableColumnInfo("Designation", "Обозначение", 5),
		new BoolDBTableColumnInfo("NoSchedule", "Без расписания"),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Designation"], $c["Name"]);
	}, false, "CurriculumWrite");

	CreateTableCollection("ActivitySubjects", "Предметы-деятельности", array(
		new ForeignDBTableColumnInfo("Subject", "Предмет", $f["Subjects"]["ID"], true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Activity", "Деятельность", $f["Activities"]["ID"]),
	), "Subject", "ChangeDate", function($t, $c) {
		return new DBTableConcatName($t, $c, array($c["Subject"], $c["Activity"]), " <=> ", null, " относится к ");
	}, false, "CurriculumWrite");

	CreateTableCollection("EducationLevels", "Уровни образования", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Name", "Наименование", 60),
		new TextDBTableColumnInfo("Abbreviation", "Сокращение", 20),
		new IntDBTableColumnInfo("Level", "Уровень", false, 1),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Abbreviation"], $c["Name"]);
	}, false, "CurriculumWrite");

	CreateTableCollection("Curriculums", "Учебные планы", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Name", "Наименование", 60),
		new IntDBTableColumnInfo("Year", "Год", false, 0, 9999),
		new ForeignDBTableColumnInfo("Qualification", "Специальность", $f["Qualifications"]["ID"]),
		new ForeignDBTableColumnInfo("EducationLevel", "Уровень образования", $f["EducationLevels"]["ID"]),
		new BoolDBTableColumnInfo("Course2", "С 2 курса"),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableUnique($t, $c, DBTableRowCurriculum::class);
	}, true, "Super");

	CreateTableCollection("CurriculumActivities", "Деятельности учебного плана", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Curriculum", "Учебный план", $f["Curriculums"]["ID"]),
		new ForeignDBTableColumnInfo("Activity", "Деятельность", $f["Activities"]["ID"]),
		new IntDBTableColumnInfo("Course", "Курс", false, 1),
		new IntDBTableColumnInfo("Semester", "Семестр", false, 1, 2),
		new DecimalDBTableColumnInfo("Week", "Неделя", false, 0),
		new DecimalDBTableColumnInfo("Length", "Длительность", false, 0, 99, 3, 3, array([">", 0])),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Activity"], null);
	}, true, "Super");

	CreateTableCollection("CurriculumSubjects", "Предметы учебного плана", array(
		new ForeignDBTableColumnInfo("Curriculum", "Учебный план", $f["Curriculums"]["ID"], true),
		new IntDBTableColumnInfo("Course", "Курс", true, 1),
		new IntDBTableColumnInfo("Semester", "Семестр", true, 1, 2),
		new ForeignDBTableColumnInfo("Subject", "Предмет", $f["Subjects"]["ID"], true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new IntDBTableColumnInfo("Hours", "Всего часов", false, 1),
		new IntDBTableColumnInfo("WHours", "Часов в неделю", false, 1),
		new IntDBTableColumnInfo("LPHours", "Часов на ЛПЗ", false, 0),
		new IntDBTableColumnInfo("CDHours", "Часов на КП", false, 0),
		new BoolDBTableColumnInfo("Exam", "Экзамен"),
	), array("Curriculum", "Course", "Semester", "Subject"), "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Subject"], null);
	}, true, "Super");

	CreateTableCollection("Groups", "Учебные группы", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Curriculum", "Учебный план", $f["Curriculums"]["ID"]),
		new IntDBTableColumnInfo("Number", "Номер", false, 1),
		new ForeignDBTableColumnInfo("Area", "Площадка", $f["Areas"]["ID"]),
		new IntDBTableColumnInfo("BudgetStudents", "Студентов на бюджете", false, 0),
		new IntDBTableColumnInfo("PaidStudents", "Студентов на платном", false, 0),
		new BoolDBTableColumnInfo("Extramural", "Заочная"),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableUnique($t, $c, DBTableRowGroup::class);
	}, false, "YearLoadWrite");

	CreateTableCollection("YearGraphsActivities", "Деятельности графиков", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new IntDBTableColumnInfo("Year", "Год", false, 0, 9999),
		new ForeignDBTableColumnInfo("Group", "Группа", $f["Groups"]["ID"]),
		new ForeignDBTableColumnInfo("Activity", "Деятельность", $f["Activities"]["ID"]),
		new IntDBTableColumnInfo("Semester", "Семестр", false, 1, 2),
		new DecimalDBTableColumnInfo("Week", "Неделя", false, 0),
		new DecimalDBTableColumnInfo("Length", "Длительность", false, 0, 99, 3, 3, array([">", 0])),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableConcatName($t, $c, array($c["Year"], $c["Semester"], $c["Group"], $c["Activity"], $c["Week"], $c["Length"]), " | ");
	}, true, "Super");

	CreateTableCollection("YearGroupLoads", "Нагрузки групп", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new IntDBTableColumnInfo("Year", "Год", false, 0),
		new TextDBTableColumnInfo("Name", "Наименование", 20),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableUnique($t, $c, DBTableRowLoad::class);
	}, true, "Super");

	CreateTableCollection("YearGroupLoadGroups", "Группы нагрузок групп", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Load", "Нагрузка", $f["YearGroupLoads"]["ID"]),
		new ForeignDBTableColumnInfo("Group", "Группа", $f["Groups"]["ID"]),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Group"], null);
	}, true, "Super");

	CreateTableCollection("YearGroupLoadSubjects", "Предметы нагрузок групп", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Load", "Нагрузка", $f["YearGroupLoads"]["ID"]),
		new ForeignDBTableColumnInfo("Subject", "Предмет", $f["Subjects"]["ID"]),
		new TextDBTableColumnInfo("Name", "Наименование", 80),
		new TextDBTableColumnInfo("Abbreviation", "Сокращение", 30),
		new BoolDBTableColumnInfo("OneHourPrior", "Приоритет 1 час"),
		new IntDBTableColumnInfo("Semester", "Семестр", false, 1, 2),
		new IntDBTableColumnInfo("Hours", "Всего часов", false, 1),
		new IntDBTableColumnInfo("WHours", "Часов в неделю", false, 1),
		new IntDBTableColumnInfo("LPHours", "Часов на ЛПЗ", false, 0),
		new IntDBTableColumnInfo("CDHours", "Часов на КП", false, 0),
		new IntDBTableColumnInfo("CDPHours", "Часов на ЗКП", false, 0),
		new IntDBTableColumnInfo("EHours", "Часов на Э", false, 0),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Abbreviation"], $c["Name"]);
	}, true, "Super");

	CreateTableCollection("YearGroupLoadSubjectLecturers", "Преподаватели предметов нагрузок групп", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Subject", "Предмет", $f["YearGroupLoadSubjects"]["ID"]),
		new ForeignDBTableColumnInfo("Lecturer", "Преподаватель", $f["Lecturers"]["ID"]),
		new BoolDBTableColumnInfo("Main", "Основной"),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Lecturer"], null);
	}, true, "Super");

	CreateTableCollection("BellsSchedules", "Расписания звонков", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Name", "Наименование", 20),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Name"], null);
	}, false, "ScheduleWrite");

	CreateTableCollection("BellsScheduleTimes", "Время расписаний звонков", array(
		new ForeignDBTableColumnInfo("Schedule", "Расписание", $f["BellsSchedules"]["ID"], true),
		new IntDBTableColumnInfo("Hour", "Час", true, 0),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TimeDBTableColumnInfo("StartTime", "Время начала"),
		new TimeDBTableColumnInfo("EndTime", "Время окончания"),
	), array("Schedule", "Hour"), "ChangeDate", function($t, $c) {
		return new DBTableUnique($t, $c, DBTableRowBells::class);
	}, false, "ScheduleWrite");

	CreateTableCollection("Schedules", "Расписания", array(
		new DateODBTableColumnInfo("Date", "Дата расписания", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Bells", "Расписание звонков", $f["BellsSchedules"]["ID"]),
	), "Date", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Date"], null);
	}, true, "Super");

	CreateTableCollection("ScheduleHours", "Часы предметов расписания", array(
		new ForeignDBTableColumnInfo("Schedule", "Расписание", $f["Schedules"]["Date"], true),
		new ForeignDBTableColumnInfo("Group", "Группа", $f["Groups"]["ID"], true),
		new IntDBTableColumnInfo("Hour", "Час", true, 0),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Subject", "Предмет", $f["YearGroupLoadSubjects"]["ID"]),
	), array("Schedule", "Group", "Hour"), "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Subject"], null);
	}, true, "Super");

	CreateTableCollection("ScheduleActHours", "Часы деятельностей расписания", array(
		new ForeignDBTableColumnInfo("Schedule", "Расписание", $f["Schedules"]["Date"], true),
		new ForeignDBTableColumnInfo("Group", "Группа", $f["Groups"]["ID"], true),
		new IntDBTableColumnInfo("Hour", "Час", true, 0),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Activity", "Деятельность", $f["Activities"]["ID"]),
	), array("Schedule", "Group", "Hour"), "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Activity"], null);
	}, true, "Super");

	CreateTableCollection("ScheduleHourRooms", "Кабинеты часов расписания", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Schedule", "Расписание", $f["Schedules"]["Date"]),
		new ForeignDBTableColumnInfo("Group", "Группа", $f["Groups"]["ID"]),
		new IntDBTableColumnInfo("Hour", "Час", false, 0),
		new ForeignDBTableColumnInfo("Lecturer", "Преподаватель", $f["Lecturers"]["ID"]),
		new ForeignDBTableColumnInfo("Room", "Кабинет", $f["Rooms"]["ID"]),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Room"], null);
	}, true, "Super");

	CreateTableCollection("SemesterSchedules", "Расписания на семестры", array(
		new IntDBTableColumnInfo("Year", "Год", true, 0, 9999),
		new IntDBTableColumnInfo("Semester", "Семестр", true, 1, 2),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new DateODBTableColumnInfo("StartDate", "Дата начала"),
		new DateODBTableColumnInfo("EndDate", "Дата окончания"),
	), array("Year", "Semester"), "ChangeDate", function($t, $c) {
		return new DBTableConcatName($t, $c, array($c["Year"], $c["Semester"]), " | ");
	}, true, "Super");

	CreateTableCollection("SemesterSchedulePairs", "Пары расписания на семестр", array(
		new ForeignDBTableColumnInfo("Year", "Год", $f["SemesterSchedules"]["Year"], true),
		new ForeignDBTableColumnInfo("Semester", "Семестр", $f["SemesterSchedules"]["Semester"], true),
		new IntDBTableColumnInfo("Day", "День", true, 0, 6),
		new ForeignDBTableColumnInfo("Load", "Нагрузка", $f["YearGroupLoads"]["ID"], true),
		new IntDBTableColumnInfo("Pair", "Пара", true, 0, 9),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Subject", "Предмет", $f["YearGroupLoadSubjects"]["ID"]),
		new ForeignDBTableColumnInfo("Subject2", "Доп. предмет", $f["YearGroupLoadSubjects"]["ID"]),
		new IntDBTableColumnInfo("Type", "Тип пары", false, 0, 4),
	), array("Year", "Semester", "Day", "Load", "Pair"), "ChangeDate", function($t, $c) {
		return new DBTableConcatName($t, $c, array($c["Year"], $c["Semester"], $c["Day"], $c["Load"], $c["Pair"], $c["Subject"]), " | ");
	}, true, "Super");

	CreateTableCollection("AttachedRooms", "Закрепленные кабинеты", array(
		new ForeignDBTableColumnInfo("Room", "Кабинет", $f["Rooms"]["ID"], true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Lecturer", "Преподаватель", $f["Lecturers"]["ID"]),
	), "Room", "ChangeDate", function($t, $c) {
		return new DBTableUnique($t, $c, DBTableRowAttachedRoom::class);
	}, false, "Super");

	CreateTableCollection("NotAvailableLecturers", "Недоступности преподавателей", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new IntDBTableColumnInfo("NotAvalID", "Недоступность", true, 0),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Lecturer", "Преподаватель", $f["Lecturers"]["ID"]),
		new DateDBTableColumnInfo("StartDate", "Дата начала"),
		new DateDBTableColumnInfo("EndDate", "Дата окончания"),
		new TextDBTableColumnInfo("Comment", "Комментарий"),
	), array("ID", "NotAvalID"), "ChangeDate", function($t, $c) {
		return new DBTableUnique($t, $c, DBTableRowNotAvailableLecturer::class);
	}, false, "ScheduleWrite");

	CreateTableCollection("NotAvailableRooms", "Недоступности кабинетов", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new IntDBTableColumnInfo("NotAvalID", "Недоступность", true, 0),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new ForeignDBTableColumnInfo("Room", "Кабинет", $f["Rooms"]["ID"]),
		new DateDBTableColumnInfo("StartDate", "Дата начала"),
		new DateDBTableColumnInfo("EndDate", "Дата окончания"),
		new TextDBTableColumnInfo("Comment", "Комментарий"),
	), array("ID", "NotAvalID"), "ChangeDate", function($t, $c) {
		return new DBTableUnique($t, $c, DBTableRowNotAvailableRoom::class);
	}, false, "ScheduleWrite");

	CreateTableCollection("SubjectRoomCategories", "Категории предметов и кабинетов", array(
		new IntDBTableColumnInfo("ID", "Код", true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("Name", "Наименование", 80),
		new TextDBTableColumnInfo("Abbreviation", "Сокращение", 30),
	), "ID", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["Abbreviation"], $c["Name"]);
	}, true, "Super");

	CreateTableCollection("SubjectRoomCategoryRooms", "Кабинеты категории предметов и кабинетов", array(
		new ForeignDBTableColumnInfo("Category", "Категория", $f["SubjectRoomCategories"]["ID"], true),
		new ForeignDBTableColumnInfo("Room", "Кабинет", $f["Rooms"]["ID"], true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new BoolDBTableColumnInfo("Exists", "Существует"),
	), array("Category", "Room"), "ChangeDate", function($t, $c) {
		return new DBTableConcatName($t, $c, array($c["Category"], $c["Room"]), " => ");
	}, true, "Super");

	CreateTableCollection("SubjectRoomCategorySubjects", "Предметы категории предметов и кабинетов", array(
		new ForeignDBTableColumnInfo("Category", "Категория", $f["SubjectRoomCategories"]["ID"], true),
		new ForeignDBTableColumnInfo("Subject", "Предмет", $f["Subjects"]["ID"], true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new BoolDBTableColumnInfo("Exists", "Существует"),
	), array("Category", "Subject"), "ChangeDate", function($t, $c) {
		return new DBTableConcatName($t, $c, array($c["Category"], $c["Subject"]), " => ");
	}, true, "Super");

	CreateTableCollection("Config", "Пользовательская конфигурация", array(
		new TextDBTableColumnInfo("Parameter", "Параметр", 40, true),
		new DateDBTableColumnInfo("ChangeDate", "Дата изменения", true),
		new TextDBTableColumnInfo("ParameterName", "Наименование параметра", 150),
		new TextDBTableColumnInfo("Value", "Значение", 100),
	), "Parameter", "ChangeDate", function($t, $c) {
		return new DBTableColumn($t, $c, $c["ParameterName"], $c["Parameter"]);
	}, true, "Super");
?>