<?php
	namespace ScheduleControl\Data;
	use ScheduleControl\{Core as Core, Utils};
	use DateTime;

	final class DBTableRowColumn extends Core\DBTableRow
	{
		public function __construct(
			Core\DBTable $table,
			array $values,
			public string|Core\DBTableColumnInfo $namecolumn,
			public string|Core\DBTableColumnInfo $fullnamecolumn,
		) { parent::__construct($table, $values); }

		public function GetName(): string
		{
			$column = ($this->namecolumn instanceof Core\DBTableColumnInfo) ? $this->namecolumn->GetName() : $this->namecolumn;

			return $this->GetNiceValue($column);
		}

		public function GetFullName(): string
		{
			$column = ($this->fullnamecolumn instanceof Core\DBTableColumnInfo) ? $this->fullnamecolumn->GetName() : $this->fullnamecolumn;

			return $this->GetNiceValue($column);
		}
	}

	final class DBTableRowConcatName extends Core\DBTableRow
	{
		public function __construct(
			Core\DBTable $table,
			array $values,
			public array $concat,
			public string $separator = " ",
			public ?array $concatfull = null,
			public ?string $fullsep = null,
		) { parent::__construct($table, $values); }

		public function GetName(): string
		{
			$arr = array();

			foreach ($this->concat as $column)
			{
				$c = ($column instanceof Core\DBTableColumnInfo) ? $column->GetName() : $column;
				$arr[] = $this->GetNiceValue($c);
			}
			
			return Utils::ConcatArray($arr, $this->separator);
		}

		public function GetFullName(): string
		{
			if (!isset($this->concatfull)) return $this->GetName();

			$arr = array();

			foreach ($this->concatfull as $column)
			{
				$c = ($column instanceof Core\DBTableColumnInfo) ? $column->GetName() : $column;
				$arr[] = $this->GetNiceValue($c);
			}
			
			return Utils::ConcatArray($arr, $this->fullsep ?? $this->separator);
		}
	}

	final class DBTableRowGroup extends Core\DBTableRow
	{
		public function GetName(): string
		{
			$qualification = $this->GetValue("Curriculum")?->GetValue("Qualification");

			return ($qualification?->GetName() ?? "???")."-".$this->GetNiceValue("Number");
		}

		public function GetFullName(): string
		{
			$qualification = $this->GetValue("Curriculum")?->GetValue("Qualification");
			$course2 = $this->GetValue("Curriculum")?->GetValue("Course2") == 1;
			$extramural = $this->GetValue("Extramural") == 1;
			$paid = (int)$this->GetValue("PaidStudents") > (int)$this->GetValue("BudgetStudents");

			return ($course2 ? "11" : "9").($qualification?->GetName() ?? "???").($paid ? "п" : "").($extramural ? "з" : "")."-".$this->GetNiceValue("Number");
		}
	}

	final class DBTableRowLecturer extends Core\DBTableRow
	{
		public function GetName(): string
		{
			$surname = $this->GetNiceValue("Surname");
			$name = $this->GetNiceValue("Name");
			$patronymic = $this->GetNiceValue("Patronymic");

			return "$surname ".(mb_strlen($name) > 1 ? mb_substr($name, 0, 1)."." : "$name ").(mb_strlen($patronymic) > 1 ? mb_substr($patronymic, 0, 1)."." : $patronymic);
		}

		public function GetFullName(): string
		{
			$surname = $this->GetNiceValue("Surname");
			$name = $this->GetNiceValue("Name");
			$patronymic = $this->GetNiceValue("Patronymic");

			return "$surname $name $patronymic";
		}
	}

	final class DBTableRowCurriculum extends Core\DBTableRow
	{
		public function GetName(): string
		{
			$year = $this->GetNiceValue("Year");
			$educationlevel = $this->GetNiceValue("EducationLevel");
			$qualification = $this->GetNiceValue("Qualification");
			$name = $this->GetNiceValue("Name");

			return "[$year][$educationlevel][$qualification] $name";
		}

		public function GetFullName(): string
		{
			$year = $this->GetNiceValue("Year");
			$educationlevel = $this->GetValue("EducationLevel")?->GetFullName() ?? "???";
			$qualification = $this->GetValue("Qualification")?->GetFullName() ?? "???";
			$course2 = $this->GetValue("Course2") == 1 ? "2" : "1";
			$name = $this->GetNiceValue("Name");

			return "$name ($course2 курс, $year год, специальность $qualification, $educationlevel)";
		}
	}

	final class DBTableRowBells extends Core\DBTableRow
	{
		public function GetName(): string
		{
			$start = new DateTime($this->GetValue("StartTime"));
			$end = new DateTime($this->GetValue("EndTime"));

			return $start->format("H:i")." - ".$end->format("H:i");
		}

		public function GetFullName(): string
		{
			$start = new DateTime($this->GetValue("StartTime"));
			$end = new DateTime($this->GetValue("EndTime"));

			return "Начало занятия в ".$start->format("H:i").", конец занятия в ".$end->format("H:i");
		}
	}

	final class DBTableRowLoad extends Core\DBTableRow
	{
		public function GetName(): string
		{
			$year = $this->GetNiceValue("Year");
			$name = $this->GetNiceValue("Name");

			return "[$year] $name";
		}

		public function GetFullName(): string
		{
			$year = $this->GetNiceValue("Year");
			$name = $this->GetNiceValue("Name");

			return "Нагрузка $name ($year год)";
		}
	}

	final class DBTableRowAttachedRoom extends Core\DBTableRow
	{
		public function GetName(): string
		{
			return $this->GetNiceValue("Lecturer")." <=> ".$this->GetNiceValue("Room");
		}

		public function GetFullName(): string
		{
			$lecturer = $this->GetValue("Lecturer")?->GetFullName() ?? "???";
			$room = $this->GetValue("Room")?->GetFullName() ?? "???";

			return "Кабинет $room закреплен за преподавателем $lecturer";
		}
	}

	final class DBTableRowNotAvailableLecturer extends Core\DBTableRow
	{
		public function GetName(): string
		{
			$start = new DateTime($this->GetValue("StartDate"));
			$end = new DateTime($this->GetValue("EndDate"));
			
			return $this->GetNiceValue("Lecturer")." => ".$start->format("d.m.Y H:i:s")." - ".$end->format("d.m.Y H:i:s");
		}

		public function GetFullName(): string
		{
			$start = new DateTime($this->GetValue("StartDate"));
			$end = new DateTime($this->GetValue("EndDate"));
			$lecturer = $this->GetValue("Lecturer")?->GetFullName() ?? "???";
			$comment = $this->GetValue("Comment");

			return "Преподаватель $lecturer недоступен с ".$start->format("d.m.Y H:i:s")." по ".$end->format("d.m.Y H:i:s")." ($comment)";
		}
	}

	final class DBTableRowNotAvailableRoom extends Core\DBTableRow
	{
		public function GetName(): string
		{
			$start = new DateTime($this->GetValue("StartDate"));
			$end = new DateTime($this->GetValue("EndDate"));
			
			return $this->GetNiceValue("Room")." => ".$start->format("d.m.Y H:i:s")." - ".$end->format("d.m.Y H:i:s");
		}

		public function GetFullName(): string
		{
			$start = new DateTime($this->GetValue("StartDate"));
			$end = new DateTime($this->GetValue("EndDate"));
			$room = $this->GetValue("Room")?->GetFullName() ?? "???";
			$comment = $this->GetValue("Comment");

			return "Кабинет $room недоступен с ".$start->format("d.m.Y H:i:s")." по ".$end->format("d.m.Y H:i:s")." ($comment)";
		}
	}
?>