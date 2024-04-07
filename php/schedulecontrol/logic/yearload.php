<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use DateTime, RuntimeException;

	final class YearLoad implements JSONAdaptive, DatabaseAdaptive
	{
		use DatabaseTransaction;

		private array $loads = array();

		public function __construct(private int $year) {}
		public function GetYear(): int { return $this->year; }

		public function GetLoads(): array { return $this->loads; }
		public function AddLoad(YearGroupLoad $load): void { $this->loads[] = $load; }

		public function ToJSON(bool $names = false): array
		{
			$json = array("year" => $this->year, "loads" => array());

			foreach ($this->GetLoads() as $load)
				$json["loads"][] = $load->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): YearLoad
		{
			$obj = new YearLoad($data["year"]);

			foreach ($data["loads"] as $gdata)
				$obj->AddLoad(YearGroupLoad::FromJSON($gdata));

			return $obj;
		}

		public function SaveToDB(): void
		{
			$this->DeleteFromDatabase();
			$year = $this->year;

			$colloads = Collections::GetCollection("YearGroupLoads");
			$colgroups = Collections::GetCollection("YearGroupLoadGroups");
			$colsubjects = Collections::GetCollection("YearGroupLoadSubjects");

			foreach ($this->GetLoads() as $load)
			{
				$lid = $colloads->AddChange(array($load->GetID()?->GetID()), array(
					"Year" => $year,
					"Name" => $load->GetName(),
				));

				if ($load->GetID() === null)
					if (isset($lid)) $load->SetID(new DBLoad($lid));
					else throw new RuntimeException("Не удалось получить идентификатор созданной нагрузки (Не удалось создать нагрузку?)", 500);

				foreach ($load->GetGroups() as $group)
				{
					$gid = $colgroups->AddChange(array($group->GetID()?->GetID()), array(
						"Load" => $load->GetID()->GetID(),
						"Group" => $group->GetGroup()->GetID(),
					));

					if ($group->GetID() === null)
						if (isset($gid)) $group->SetID(new DBLoadGroup($gid));
						else throw new RuntimeException("Не удалось получить идентификатор созданной группы нагрузки (Не удалось создать группу нагрузки?)", 500);
				}
				
				foreach ($load->GetSubjects() as $subject)
				{
					$sid = $colsubjects->AddChange(array($subject->GetID()?->GetID()), array(
						"Load" => $load->GetID()->GetID(),
						"Name" => $subject->GetName(),
						"Subject" => $subject->GetSubject()->GetID(),
						"Abbreviation" => $subject->GetAbbreviation(),
						"Semester" => $subject->GetSemester(),
						"Hours" => $subject->GetHours(),
						"WHours" => $subject->GetWHours(),
						"LPHours" => $subject->GetLPHours(),
						"CDHours" => $subject->GetCDHours(),
						"CDPHours" => $subject->GetCDPHours(),
						"EHours" => $subject->GetEHours(),
						"OneHourPrior" => $subject->IsOneHourPriority() ? 1 : 0,
					));

					if ($subject->GetID() === null)
						if (isset($sid)) $subject->SetID(new DBLoadSubject($sid));
						else throw new RuntimeException("Не удалось получить идентификатор созданного предмета нагрузки (Не удалось создать предмет нагрузки?)", 500);
				}
			}
		}

		public function DeleteFromDB(): void
		{
			$year = $this->year;

			$colloads = Collections::GetCollection("YearGroupLoads");
			$colgroups = Collections::GetCollection("YearGroupLoadGroups");
			$colsubjects = Collections::GetCollection("YearGroupLoadSubjects");

			foreach ($colloads->GetAllLastChanges(array("Year" => $year)) as $load)
			{
				$colloads->AddChange($load->GetID(), null);

				foreach ($colgroups->GetAllLastChanges(array("Load" => $load->GetData()->GetValue("ID"))) as $group)
					$colgroups->AddChange($group->GetID(), null);
				
				foreach ($colsubjects->GetAllLastChanges(array("Load" => $load->GetData()->GetValue("ID"))) as $subject)
					$colsubjects->AddChange($subject->GetID(), null);
			}
		}

		public static function GetFromDatabase(mixed $param): ?YearLoad
		{
			$colloads = Collections::GetCollection("YearGroupLoads");
			$colgroups = Collections::GetCollection("YearGroupLoadGroups");
			$colsubjects = Collections::GetCollection("YearGroupLoadSubjects");

			$yload = new YearLoad($param);

			foreach ($colloads->GetAllLastChanges(array("Year" => $param)) as $load)
			{
				$lid = $load->GetData()->GetValue("ID");
				$gload = new YearGroupLoad(new DBLoad($lid), $load->GetData()->GetValue("Name"));

				foreach ($colgroups->GetAllLastChanges(array("Load" => $lid)) as $group)
					if (($gobj = new DBGroup($group->GetData()->GetDBValue("Group")))->IsValid())
						$gload->AddGroup(new YearGroupLoadGroup(
							new DBLoadGroup($group->GetData()->GetDBValue("ID")),
							$gobj
						));

				foreach ($colsubjects->GetAllLastChanges(array("Load" => $lid)) as $subject)
					if (($s = new DBSubject($subject->GetData()->GetDBValue("Subject")))->IsValid())
						$gload->AddSubject(new YearGroupLoadSubject(
							new DBLoadSubject($subject->GetData()->GetValue("ID")),
							$s,
							$subject->GetData()->GetValue("Name"),
							$subject->GetData()->GetValue("Abbreviation"),
							$subject->GetData()->GetValue("Semester"),
							$subject->GetData()->GetValue("Hours"),
							$subject->GetData()->GetValue("WHours"),
							$subject->GetData()->GetValue("LPHours"),
							$subject->GetData()->GetValue("CDHours"),
							$subject->GetData()->GetValue("CDPHours"),
							$subject->GetData()->GetValue("EHours"),
							$subject->GetData()->GetValue("OneHourPrior") == 1,
						));

				if (count($gload->GetGroups()) > 0 && count($gload->GetSubjects()) > 0) $yload->AddLoad($gload);
			}

			return count($yload->GetLoads()) > 0 ? $yload : null;
		}
	}

	final class YearGroupLoad implements JSONAdaptive
	{
		private array $subjects = array();
		private array $groups = array();

		public function __construct(private ?DBLoad $id, private string $name) {}

		public function SetID(DBLoad $id) { $this->id = $id; }
		public function GetID(): ?DBLoad { return $this->id; }
		public function GetName(): string { return $this->name; }

		public function GetSubjects(): array { return $this->subjects; }
		public function AddSubject(YearGroupLoadSubject $subject): void { $this->subjects[] = $subject; }

		public function GetGroups(): array { return $this->groups; }
		public function AddGroup(YearGroupLoadGroup $group): void { $this->groups[] = $group; }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"id" => $this->id?->ToJSON($names),
				"name" => $this->name,
				"subjects" => array(),
				"groups" => array(),
			);

			foreach ($this->subjects as $subject)
				$json["subjects"][] = $subject->ToJSON($names);

			foreach ($this->groups as $group)
				$json["groups"][] = $group->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): YearGroupLoad
		{
			$obj = new YearGroupLoad(
				isset($data["id"]) ? DBLoad::FromJSON($data["id"]) : null,
				$data["name"],
			);

			foreach ($data["groups"] as $gdata)
				$obj->AddGroup(YearGroupLoadGroup::FromJSON($gdata));

			foreach ($data["subjects"] as $sdata)
				$obj->AddSubject(YearGroupLoadSubject::FromJSON($sdata));

			return $obj;
		}
	}

	final class YearGroupLoadGroup implements JSONAdaptive
	{
		public function __construct(private ?DBLoadGroup $id, private DBGroup $group) {}

		public function SetID(DBLoadGroup $id): void { $this->id = $id; }
		public function GetID(): ?DBLoadGroup { return $this->id; }

		public function GetGroup(): DBGroup { return $this->group; }
		public function SetGroup(DBGroup $group) { $this->group = $group; }

		public function ToJSON(bool $names = false): ?array
		{
			return array(
				"id" => $this->id?->ToJSON($names),
				"group" => $this->group->ToJSON($names),
			);
		}

		public static function FromJSON(array $data): YearGroupLoadGroup
		{
			return new YearGroupLoadGroup(
				isset($data["id"]) ? DBLoadGroup::FromJSON($data["id"]) : null,
				DBGroup::FromJSON($data["group"]),
			);
		}
	}

	final class YearGroupLoadSubject implements JSONAdaptive
	{
		public function __construct(
			private ?DBLoadSubject $id,
			private DBSubject $subject,
			private string $subjname,
			private string $subjabbr,
			private int $semester,
			private int $hours,
			private int $whours,
			private int $lphours,
			private int $cdhours,
			private int $cdphours,
			private int $ehours,
			private bool $onehour = false,
		) {}

		public function SetID(DBLoadSubject $id) { $this->id = $id; }
		public function GetID(): ?DBLoadSubject { return $this->id; }
		public function GetSubject(): DBSubject { return $this->subject; }
		public function GetName(): string { return $this->subjname; }
		public function GetAbbreviation(): string { return $this->subjabbr; }
		public function GetSemester(): int { return $this->semester; }
		public function GetHours(): int { return $this->hours; }
		public function GetWHours(): int { return $this->whours; }
		public function GetLPHours(): int { return $this->lphours; }
		public function GetCDHours(): int { return $this->cdhours; }
		public function GetCDPHours(): int { return $this->cdphours; }
		public function GetEHours(): int { return $this->ehours; }
		public function IsOneHourPriority(): bool { return $this->onehour; }

		public function GetLecturers(?bool $mainonly = null): array
		{
			if (!isset($this->id)) return array();

			$lecturers = array();

			foreach ($this->id->GetLecturers($mainonly) as $subjlect)
				$lecturers[] = new YearGroupLoadSubjectLecturer(
					$subjlect,
					$subjlect->GetSubject(),
					$subjlect->GetLecturer(),
					$subjlect->GetValue()->GetValue("Main") == 1,
				);
			
			return $lecturers;
		}

		public function ToJSON(bool $names = false): array
		{
			return array(
				"id" => $this->id?->ToJSON($names),
				"subject" => $this->subject->ToJSON($names),
				"subjname" => $this->subjname,
				"subjabbr" => $this->subjabbr,
				"semester" => $this->semester,
				"hours" => $this->hours,
				"whours" => $this->whours,
				"lphours" => $this->lphours,
				"cdhours" => $this->cdhours,
				"cdphours" => $this->cdphours,
				"ehours" => $this->ehours,
				"onehour" => $this->onehour ? 1 : 0,
			);
		}

		public static function FromJSON(array $data): YearGroupLoadSubject
		{
			return new YearGroupLoadSubject(
				isset($data["id"]) ? DBLoadSubject::FromJSON($data["id"]) : null,
				DBSubject::FromJSON($data["subject"]),
				$data["subjname"],
				$data["subjabbr"],
				$data["semester"],
				$data["hours"],
				$data["whours"],
				$data["lphours"],
				$data["cdhours"],
				$data["cdphours"],
				$data["ehours"],
				$data["onehour"] == 1,
			);
		}
	}

	final class YearGroupLoadSubjectLecturer implements JSONAdaptive, DatabaseAdaptive
	{
		use DatabaseTransaction;

		public function __construct(
			private ?DBLoadSubjectLecturer $id,
			private DBLoadSubject $subject,
			private DBLecturer $lecturer,
			private bool $main,
		) {}

		public function GetID(): ?DBLoadSubjectLecturer { return $this->id; }
		public function GetSubject(): DBLoadSubject { return $this->subject; }
		public function GetLecturer(): DBLecturer { return $this->lecturer; }
		public function IsMain(): bool { return $this->main; }

		public function ToJSON(bool $names = false): ?array
		{
			if ($this->lecturer->IsValid())
				return array(
					"id" => $this->id?->ToJSON($names),
					"subject" => $this->subject->ToJSON($names),
					"lecturer" => $this->lecturer->ToJSON($names),
					"main" => $this->main ? 1 : 0,
				);

			return null;
		}

		public static function FromJSON(array $data): YearGroupLoadSubjectLecturer
		{
			return new YearGroupLoadSubjectLecturer(
				isset($data["id"]) ? DBLoadSubjectLecturer::FromJSON($data["id"]) : null,
				DBLoadSubject::FromJSON($data["subject"]),
				DBLecturer::FromJSON($data["lecturer"]),
				$data["main"] == 1,
			);
		}

		public function SaveToDB(): void
		{
			$collecturers = Collections::GetCollection("YearGroupLoadSubjectLecturers");

			$id = $collecturers->AddChange(array($this->id?->GetID()), array(
				"Subject" => $this->subject->GetID(),
				"Lecturer" => $this->lecturer->GetID(),
				"Main" => $this->main ? 1 : 0,
			));

			if (!isset($this->id))
				if (isset($id)) $this->id = new DBLoadSubjectLecturer($id);
				else throw new RuntimeException("Не удалось получить идентификатор созданной связи преподавателя и предмета (не удалось создать связь?)", 500);
		}

		public function DeleteFromDB(): void
		{
			if (!isset($this->id)) return;

			$collecturers = Collections::GetCollection("YearGroupLoadSubjectLecturers");

			$collecturers->AddChange($this->id->GetID(), null);
		}

		public static function GetFromDatabase(mixed $param): ?YearGroupLoadSubjectLecturer
		{
			$collecturers = Collections::GetCollection("YearGroupLoadSubjectLecturers");

			$data = $collecturers->GetLastChange($param->GetID());
			if (!isset($data)) return null;

			return new YearGroupLoadSubjectLecturer(
				$param,
				new DBLoadSubject($data->GetData()->GetDBValue("Subject")),
				new DBLecturer($data->GetData()->GetDBValue("Lecturer")),
				$data->GetData()->GetValue("Main") == 1,
			);
		}
	}

	final class LecturerYearLoad implements JSONAdaptive
	{
		private array $subjects = array();

		public function __construct(private DBLecturer $lecturer) {}

		public function GetLecturer(): DBLecturer { return $this->lecturer; }

		public function GetSubjects(): array { return $this->subjects; }
		public function AddSubject(LecturerYearLoadSubject $subject): void { $this->subjects[] = $subject; }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"lecturer" => $this->lecturer->ToJSON($names),
				"subjects" => array(),
			);

			foreach ($this->subjects as $subject)
				$json["subjects"][] = $subject->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): LecturerYearLoad
		{
			$obj = new LecturerYearLoad(DBLecturer::FromJSON($data["lecturer"]));

			foreach ($data["subjects"] as $subject)
				$obj->AddSubject(LecturerYearLoadSubject::FromJSON($subject));

			return $obj;
		}
	}

	final class LecturerYearLoadSubject implements JSONAdaptive
	{
		private array $groups = array();

		public function __construct(private DBLoadSubject $subject) {}
		
		public function GetSubject(): DBLoadSubject { return $this->subject; }
		public function GetGroups(): array { return $this->groups; }

		public function GetGroup(DBGroup $group, bool $create = false): ?LecturerYearLoadSubjectGroup
		{ return $this->groups[$group->GetID()] ?? ($create ? $this->AddGroup($group) : null); }

		public function AddGroup(DBGroup $group): LecturerYearLoadSubjectGroup
		{ return $this->groups[$group->GetID()] = new LecturerYearLoadSubjectGroup(); }

		public function SetGroup(DBGroup $group, LecturerYearLoadSubjectGroup $data): void
		{ $this->groups[$group->GetID()] = $data; }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"subject" => $this->subject->ToJSON($names),
				"groups" => array(),
			);

			foreach ($this->groups as $gid => $data)
				$json["groups"][] = array(
					"group" => (new DBGroup($gid))->ToJSON($names),
					"semesters" => $data->ToJSON($names),
				);

			return $json;
		}

		public static function FromJSON(array $data): LecturerYearLoadSubject
		{
			$obj = new LecturerYearLoadSubject(DBLoadSubject::FromJSON($data["subject"]));

			foreach ($data["groups"] as $data)
				$obj->SetGroup(DBGroup::FromJSON($data["group"]), LecturerYearLoadSubjectGroup::FromJSON($data["semesters"]));

			return $obj;
		}
	}

	final class LecturerYearLoadSubjectGroup implements JSONAdaptive
	{
		private array $semesters = array();

		public function GetSemesters(): array { return $this->semesters; }
		public function GetSemester(int $semester): ?LecturerYearLoadSubjectGroupSemester
		{ return $this->semesters[$semester] ?? null; }

		public function AddSemester(int $semester, LecturerYearLoadSubjectGroupSemester $data): void
		{ $this->semesters[$semester] = $data; }

		public function ToJSON(bool $names = false): array
		{
			$json = array();

			foreach ($this->semesters as $s => $semester)
				$json[$s] = $semester->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): LecturerYearLoadSubjectGroup
		{
			$obj = new LecturerYearLoadSubjectGroup();

			foreach ($data as $s => $semester)
				$obj->AddSemester($s, LecturerYearLoadSubjectGroupSemester::FromJSON($semester));

			return $obj;
		}
	}

	final class LecturerYearLoadSubjectGroupSemester implements JSONAdaptive
	{
		public function __construct(
			private int $shours,
			private int $hours,
			private int $whours,
			private int $lphours,
			private int $cdhours,
			private int $cdphours,
			private int $ehours,
		) {}

		public function GetSHours(): int { return $this->shours; }
		public function GetHours(): int { return $this->hours; }
		public function GetWHours(): int { return $this->whours; }
		public function GetLPHours(): int { return $this->lphours; }
		public function GetCDHours(): int { return $this->cdhours; }
		public function GetCDPHours(): int { return $this->cdphours; }
		public function GetEHours(): int { return $this->ehours; }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"shours" => $this->shours,
				"hours" => $this->hours,
				"whours" => $this->whours,
				"lphours" => $this->lphours,
				"cdhours" => $this->cdhours,
				"cdphours" => $this->cdphours,
				"ehours" => $this->ehours,
			);

			return $json;
		}

		public static function FromJSON(array $data): LecturerYearLoadSubjectGroupSemester
		{
			return new LecturerYearLoadSubjectGroupSemester($data["shours"], $data["hours"], $data["whours"], $data["lphours"], $data["cdhours"], $data["cdphours"], $data["ehours"]);
		}
	}
?>