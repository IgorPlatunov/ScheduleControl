<?php
	namespace ScheduleControl\Logic;

	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use DateTime, RuntimeException;

	final class Curriculum implements JSONAdaptive, DatabaseAdaptive
	{
		use DatabaseTransaction;

		private array $graphs = array();
		private array $subjects = array();

		public function __construct(
			private ?DBCurriculum $id,
			private int $year,
			private DBEducationLevel $educationlevel,
			private DBQualification $qualification,
			private string $name,
			private bool $course2,
		) {}

		public function GetID(): ?DBCurriculum { return $this->id; }
		public function GetYear(): int { return $this->year; }
		public function GetEducationLevel(): DBEducationLevel { return $this->educationlevel; }
		public function GetQualification(): DBQualification { return $this->qualification; }
		public function GetName(): string { return $this->name; }
		public function IsCourse2(): bool { return $this->course2; }

		public function SetYear(int $year): void { $this->year = $year; }
		public function SetEducationLevel(DBEducationLevel $level): void { $this->educationlevel = $level; }
		public function SetQualification(DBQualification $qualif): void { $this->qualification = $qualif; }
		public function SetName(string $name): void { $this->name = $name; }
		public function SetCourse2(bool $course2): void { $this->course2 = $course2; }

		public function GetGraphs(): array { return $this->graphs; }
		public function GetSubjects(): array { return $this->subjects; }
		
		public function GetGraph(int $course, bool $create = false): ?YearActivities
		{ return $this->graphs[$course] ?? ($create ? $this->AddGraph($course) : null); }

		public function AddGraph(int $course): YearActivities
		{ return $this->graphs[$course] = new YearActivities(); }

		public function SetGraph(int $course, YearActivities $graph): void
		{ $this->graphs[$course] = $graph; }

		public function RemoveGraph(int $course): void
		{ unset($this->graphs[$course]); }

		public function GetSubject(DBSubject $subject, bool $create = false): ?CurriculumSubject
		{ return $this->subjects[$subject->GetID()] ?? ($create ? $this->AddSubject($subject) : null); }

		public function AddSubject(DBSubject $subject): CurriculumSubject
		{ return $this->subjects[$subject->GetID()] = new CurriculumSubject($subject); }

		public function SetSubject(CurriculumSubject $subject): void
		{ $this->subjects[$subject->GetSubject()->GetID()] = $subject; }

		public function RemoveSubject(DBSubject $subject): void
		{ unset($this->subjects[$subject->GetID()]); }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"id" => $this->id?->ToJSON($names),
				"year" => $this->year,
				"educationlevel" => $this->educationlevel->ToJSON($names),
				"qualification" => $this->qualification->ToJSON($names),
				"name" => $this->name,
				"course2" => $this->course2 ? 1 : 0,
				"graphs" => array(),
				"subjects" => array(),
			);

			foreach ($this->graphs as $course => $graph)
				$json["graphs"][$course] = $graph->ToJSON($names);

			foreach ($this->subjects as $sid => $subject)
				if ((new DBSubject($sid))->IsValid())
					$json["subjects"][] = $subject->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): Curriculum
		{
			$obj = new Curriculum(
				isset($data["id"]) ? DBCurriculum::FromJSON($data["id"]) : null,
				$data["year"],
				DBEducationLevel::FromJSON($data["educationlevel"]),
				DBQualification::FromJSON($data["qualification"]),
				$data["name"],
				$data["course2"],
			);

			foreach ($data["graphs"] as $course => $gdata)
				$obj->SetGraph($course, YearActivities::FromJSON($gdata));

			foreach ($data["subjects"] as $subject)
				$obj->SetSubject(CurriculumSubject::FromJSON($subject));

			return $obj;
		}

		public function SaveToDB(): void
		{
			$colcurrs = Collections::GetCollection("Curriculums");
			$colacts = Collections::GetCollection("CurriculumActivities");
			$colsubjs = Collections::GetCollection("CurriculumSubjects");

			$this->DeleteFromDatabase();

			$insid = $colcurrs->AddChange(array($this->id?->GetID()), array(
				"Year" => $this->year,
				"Name" => $this->name,
				"Course2" => $this->course2 ? 1 : 0,
				"Qualification" => $this->qualification->GetID(),
				"EducationLevel" => $this->educationlevel->GetID(),
			));

			if (!isset($this->id))
				if (isset($insid)) $this->id = new DBCurriculum($insid);
				else throw new RuntimeException("Не удалось получить идентификатор созданного учебного плана (не удалось создать учебный план?)", 500);

			foreach ($this->graphs as $course => $graph)
				foreach ($graph->GetActivities() as $act)
				{
					if (!$act->GetActivity()->IsValid()) continue;

					$aid = $colacts->AddChange(array($act->GetID()), array(
						"Curriculum" => $this->id->GetID(),
						"Activity" => $act->GetActivity()->GetID(),
						"Course" => $course,
						"Semester" => $act->GetSemester(),
						"Week" => $act->GetWeek(),
						"Length" => $act->GetLength(),
					));

					if ($act->GetID() === null)
						if (isset($aid)) $act->SetID($aid);
							else throw new RuntimeException("Не удалось получить идентификатор созданной деятельности графика учебного плана (не удалось создать деятельность?)", 500);
				}

				foreach ($this->subjects as $sid => $subject)
				{
					if (!$subject->GetSubject()->IsValid()) continue;

					foreach ($subject->GetCourses() as $course => $semesters)
						foreach ($semesters->GetSemesters() as $semester => $info)
							$colsubjs->AddChange(array($this->id->GetID(), $course, $semester, $sid), array(
								"Hours" => $info->GetHours(),
								"WHours" => $info->GetWHours(),
								"LPHours" => $info->GetLPHours(),
								"CDHours" => $info->GetCDHours(),
								"Exam" => $info->HasExam() ? 1 : 0,
							));
				}
		}

		public function DeleteFromDB(): void
		{
			if (!isset($this->id)) return;

			$colcurrs = Collections::GetCollection("Curriculums");
			$colacts = Collections::GetCollection("CurriculumActivities");
			$colsubjs = Collections::GetCollection("CurriculumSubjects");

			$curriculum = $colcurrs->GetRegistry($this->id->GetID());
			if (!isset($curriculum)) return;

			$curriculum->AddChange(null);

			foreach ($colacts->GetAllLastChanges(array("Curriculum" => $this->id->GetID())) as $act)
				$colacts->AddChange($act->GetID(), null);

			foreach ($colsubjs->GetAllLastChanges(array("Curriculum" => $this->id->GetID())) as $subj)
				$colsubjs->AddChange($subj->GetID(), null);
		}

		public static function GetFromDatabase(mixed $param): ?Curriculum
		{
			$colcurrs = Collections::GetCollection("Curriculums");
			$colacts = Collections::GetCollection("CurriculumActivities");
			$colsubjs = Collections::GetCollection("CurriculumSubjects");

			$curr = $colcurrs->GetLastChange($param->GetID());
			if (!isset($curr)) return null;

			$curriculum = new Curriculum(
				$param,
				$curr->GetData()->GetValue("Year"),
				new DBEducationLevel($curr->GetData()->GetDBValue("EducationLevel")),
				new DBQualification($curr->GetData()->GetDBValue("Qualification")),
				$curr->GetData()->GetValue("Name"),
				$curr->GetData()->GetValue("Course2"),
			);

			if (!$curriculum->educationlevel->IsValid() || !$curriculum->qualification->IsValid()) return null;

			foreach ($colacts->GetAllLastChanges(array("Curriculum" => $param->GetID())) as $act)
			{
				$activity = new DBActivity($act->GetData()->GetDBValue("Activity"));
				if (!$activity->IsValid()) continue;

				$aid = $act->GetData()->GetValue("ID");
				$course = $act->GetData()->GetValue("Course");
				$semester = $act->GetData()->GetValue("Semester");
				$week = $act->GetData()->GetValue("Week");
				$length = $act->GetData()->GetValue("Length");

				$curriculum->GetGraph($course, true)->AddActivity($aid, $activity, $week, $length, $semester);
			}

			foreach ($colsubjs->GetAllLastChanges(array("Curriculum" => $param->GetID())) as $subj)
			{
				$subject = new DBSubject($subj->GetData()->GetDBValue("Subject"));
				if (!$subject->IsValid()) continue;

				$course = $subj->GetData()->GetValue("Course");
				$semester = $subj->GetData()->GetValue("Semester");
				$hours = $subj->GetData()->GetValue("Hours");
				$whours = $subj->GetData()->GetValue("WHours");
				$lphours = $subj->GetData()->GetValue("LPHours");
				$cdhours = $subj->GetData()->GetValue("CDHours");
				$exam = $subj->GetData()->GetValue("Exam") == 1;

				$info = new CurriculumSubjectSemesterInfo($hours, $whours, $lphours, $cdhours, $exam);
				$curriculum->GetSubject($subject, true)->GetCourse($course, true)->SetSemester($semester, $info);
			}

			return $curriculum;
		}
	}

	final class CurriculumSubject implements JSONAdaptive
	{
		private array $courses = array();

		public function __construct(private DBSubject $subject) {}

		public function GetSubject(): DBSubject { return $this->subject; }
		public function GetCourses(): array { return $this->courses; }

		public function GetCourse(int $course, bool $create = false): ?CurriculumSubjectCourse
		{ return $this->courses[$course] ?? ($create ? $this->AddCourse($course) : null); }

		public function AddCourse(int $course): CurriculumSubjectCourse
		{ return $this->courses[$course] = new CurriculumSubjectCourse(); }

		public function SetCourse(int $course, CurriculumSubjectCourse $data): void
		{ $this->courses[$course] = $data; }

		public function RemoveCourse(int $course): void
		{ unset($this->courses[$course]); }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"subject" => $this->subject->ToJSON($names),
				"courses" => array(),
			);

			foreach ($this->courses as $course => $cobj)
				$json["courses"][$course] = $cobj->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): CurriculumSubject
		{
			$obj = new CurriculumSubject(DBSubject::FromJSON($data["subject"]));

			foreach ($data["courses"] as $course => $cdata)
				$obj->SetCourse($course, CurriculumSubjectCourse::FromJSON($cdata));

			return $obj;
		}
	}

	final class CurriculumSubjectCourse implements JSONAdaptive
	{
		private array $semesters = array();

		public function GetSemesters(): array { return $this->semesters; }

		public function GetSemester(int $semester): ?CurriculumSubjectSemesterInfo
		{ return $this->semesters[$semester] ?? null; }

		public function SetSemester(int $semester, CurriculumSubjectSemesterInfo $info): void
		{ $this->semesters[$semester] = $info; }

		public function RemoveSemester(int $semester): void
		{ unset($this->semesters[$semester]); }

		public function ToJSON(bool $names = false): array
		{
			$json = array();

			foreach ($this->semesters as $semester => $info)
				$json[$semester] = $info->ToJSON($names);

			return $json;
		}

		public static function FromJSON(array $data): CurriculumSubjectCourse
		{
			$obj = new CurriculumSubjectCourse();

			foreach ($data as $semester => $info)
				$obj->SetSemester($semester, CurriculumSubjectSemesterInfo::FromJSON($info));

			return $obj;
		}
	}

	final class CurriculumSubjectSemesterInfo implements JSONAdaptive
	{
		public function __construct(
			private int $hours,
			private int $whours,
			private int $lphours = 0,
			private int $cdhours = 0,
			private bool $exam = false,
		) {}

		public function GetHours(): int { return $this->hours; }
		public function GetWHours(): int { return $this->whours; }
		public function GetLPHours(): int { return $this->lphours; }
		public function GetCDHours(): int { return $this->cdhours; }
		public function HasExam(): int { return $this->exam; }

		public function ToJSON(bool $names = false): array
		{
			return array(
				"hours" => $this->hours,
				"whours" => $this->whours,
				"lphours" => $this->lphours,
				"cdhours" => $this->cdhours,
				"exam" => $this->exam ? 1 : 0,
			);
		}

		public static function FromJSON(array $data): CurriculumSubjectSemesterInfo
		{
			return new CurriculumSubjectSemesterInfo(
				$data["hours"],
				$data["whours"],
				$data["lphours"],
				$data["cdhours"],
				$data["exam"],
			);
		}
	}
?>