<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\{Core as Core, Core\DBTableRegistryCollections as Collections, UserConfig};

	abstract class DBValueWrapper implements JSONAdaptive
	{
		protected static string $collection = "";
		public function __construct(private string $id) {}

		public function GetID(): string { return $this->id; }

		public function GetObject(): ?Core\RegistryObject
		{
			$collection = Collections::GetCollection(static::$collection);
			if (!isset($collection)) return null;

			return $collection->GetLastChange($this->id);
		}

		public function GetValue(): mixed
		{ return $this->GetObject()?->GetData(); }

		public function GetName(): string
		{ return $this->GetValue()?->GetName() ?? "???"; }

		public function GetFullName(): string
		{ return $this->GetValue()?->GetFullName() ?? "???"; }

		public function IsValid(): bool
		{ return $this->GetObject() !== null; }

		public static function IsValidValue(mixed $id): bool
		{
			$collection = Collections::GetCollection(static::$collection);
			if (!isset($collection)) return null;

			return $collection->GetLastChange($id) !== null;
		}

		protected function GetForeign(string $column, string $typeclass): ?DBValueWrapper
		{
			$foreign = $this->GetValue()?->GetDBValue($column);

			return isset($foreign) ? new $typeclass($foreign) : null;
		}

		public function ToJSON(bool $names = false): array
		{
			$json = array("id" => $this->id);

			if ($names)
			{
				$json["name"] = $this->GetName();
				$json["fullname"] = $this->GetFullName();
			}

			return $json;
		}

		public static function FromJSON(array $data): DBValueWrapper
		{ return static::class::FromID($data["id"]); }

		protected static function FromID(mixed $id): DBValueWrapper
		{ return new (static::class)($id); }
	}

	final class DBArea extends DBValueWrapper
	{ protected static string $collection = "Areas"; }

	final class DBQualification extends DBValueWrapper
	{ protected static string $collection = "Qualifications"; }

	final class DBEducationLevel extends DBValueWrapper
	{ protected static string $collection = "EducationLevels"; }

	final class DBSubject extends DBValueWrapper
	{
		protected static string $collection = "Subjects";

		public function GetActivity(): ?DBActivity
		{
			$subject = Collections::GetCollection("ActivitySubjects")->GetLastChange($this->GetID());
			if (!isset($subject)) return null;

			$activity = new DBActivity($subject->GetData()->GetDBValue("Activity"));
			return $activity->IsValid() ? $activity : null;
		}

		public function IsRoomSuitable(DBRoom $room): bool
		{
			foreach (Collections::GetCollection("SubjectRoomCategorySubjects")->GetAllLastChanges(array("Subject" => $this->GetID())) as $category)
				if (Collections::GetCollection("SubjectRoomCategoryRooms")->GetRegistry(array($category->GetData()->GetDBValue("Category"), $room->GetID())) !== null)
					return true;

			return false;
		}
	}

	final class DBRoom extends DBValueWrapper
	{
		protected static string $collection = "Rooms";

		public function GetArea(): ?DBArea
		{ return $this->GetForeign("Area", DBArea::class); }

		public function GetLecturer(): ?DBLecturer
		{
			$obj = Collections::GetCollection("AttachedRooms")->GetLastChange($this->GetID());

			if (isset($obj) && ($lecturer = new DBLecturer($obj->GetData()->GetDBValue("Lecturer")))->IsValid())
				return $lecturer;

			return null;
		}
	}

	final class DBActivity extends DBValueWrapper
	{ protected static string $collection = "Activities"; }

	final class DBLecturer extends DBValueWrapper
	{
		protected static string $collection = "Lecturers";

		public function GetAttachedRooms(): array
		{
			$rooms = array();

			foreach (Collections::GetCollection("AttachedRooms")->GetAllLastChanges(array("Lecturer" => $this->GetID())) as $rdata)
				if (($room = new DBRoom($rdata->GetData()->GetDBValue("Room")))->IsValid())
					$rooms[] = $room;

			return $rooms;
		}

		public function GetAttachedSubjects(int $year, int $semester, ?bool $mainonly = null): array
		{
			$subjects = array();

			foreach (Collections::GetCollection("YearGroupLoadSubjectLecturers")->GetAllLastChanges(array("Lecturer" => $this->GetID())) as $linfo)
				if (
					($subjlect = new DBLoadSubjectLecturer($linfo->GetData()->GetValue("ID")))->IsValid() && ($subject = $subjlect->GetSubject())->IsValid() &&
					$subject->GetValue()->GetValue("Semester") == $semester && ($load = $subject->GetLoad())->IsValid() &&
					$load->GetValue()->GetValue("Year") == $year && $subjlect->IsCountedLecturer($mainonly)
				)
				$subjects[] = $subject;

			return $subjects;
		}
	}

	final class DBCurriculum extends DBValueWrapper
	{
		protected static string $collection = "Curriculums";

		public function GetEducationLevel(): ?DBEducationLevel
		{ return $this->GetForeign("EducationLevel", DBEducationLevel::class); }

		public function GetQualification(): ?DBQualification
		{ return $this->GetForeign("Qualification", DBQualification::class); }
	}

	final class DBGroup extends DBValueWrapper
	{
		protected static string $collection = "Groups";

		public function GetCurriculum(): ?DBCurriculum
		{ return $this->GetForeign("Curriculum", DBCurriculum::class); }

		public function GetArea(): ?DBArea
		{ return $this->GetForeign("Area", DBArea::class); }

		public function GetLoads(?int $year = null): array
		{
			$loads = array();
			$cache = array();

			foreach (Collections::GetCollection("YearGroupLoadGroups")->GetAllLastChanges(array("Group" => $this->GetID())) as $load)
				if (!isset($cache[$id = $load->GetData()->GetDBValue("Load")]) && ($gload = new DBLoad($id))->IsValid() && (!isset($year) || $year == $gload->GetValue()->GetValue("Year")))
				{
					$loads[] = $gload;
					$cache[$id] = true;
				}

			return $loads;
		}

		public function IsFromSameLoad(DBGroup $group): bool
		{
			$loads = $this->GetLoads();
			$gloads = $group->GetLoads();

			foreach ($loads as $load)
				foreach ($gloads as $gload)
					if ($load->GetID() == $gload->GetID())
						return true;

			return false;
		}
	}

	final class DBCurriculumActivity extends DBValueWrapper
	{
		protected static string $collection = "CurriculumActivities";

		public function GetCurriculum(): ?DBCurriculum
		{ return $this->GetForeign("Curriculum", DBCurriculum::class); }

		public function GetActivity(): ?DBActivity
		{ return $this->GetForeign("Activity", DBActivity::class); }
	}

	final class DBYearGraphActivity extends DBValueWrapper
	{
		protected static string $collection = "YearGraphActivities";

		public function GetGroup(): ?DBGroup
		{ return $this->GetForeign("Group", DBGroup::class); }

		public function GetActivity(): ?DBActivity
		{ return $this->GetForeign("Activity", DBActivity::class); }
	}

	final class DBLoad extends DBValueWrapper
	{
		protected static string $collection = "YearGroupLoads";

		public function GetGroups(): array
		{
			$groups = array();

			foreach (Collections::GetCollection("YearGroupLoadGroups")->GetAllLastChanges(array("Load" => $this->GetID())) as $gdata)
				if (($group = new DBGroup($gdata->GetData()->GetDBValue("Group")))->IsValid())
					$groups[] = $group;

			return $groups;
		}

		public function GetSubjects(): array
		{
			$subjects = array();

			foreach (Collections::GetCollection("YearGroupLoadSubjects")->GetAllLastChanges(array("Load" => $this->GetID())) as $sdata)
				if (($subject = new DBLoadSubject($sdata->GetData()->GetValue("ID")))->IsValid())
					$subjects[] = $subject;

			return $subjects;
		}
	}

	final class DBLoadGroup extends DBValueWrapper
	{
		protected static string $collection = "YearGroupLoadGroups";
	
		public function GetLoad(): ?DBLoad
		{ return $this->GetForeign("Load", DBLoad::class); }

		public function GetGroup(): ?DBGroup
		{ return $this->GetForeign("Group", DBGroup::class); }
	}

	final class DBLoadSubject extends DBValueWrapper
	{
		protected static string $collection = "YearGroupLoadSubjects";

		public function GetLoad(): ?DBLoad
		{ return $this->GetForeign("Load", DBLoad::class); }

		public function GetSubject(): ?DBSubject
		{ return $this->GetForeign("Subject", DBSubject::class); }

		public function GetLecturers(?bool $mainonly = null): array
		{
			$lecturers = array();

			foreach (Collections::GetCollection("YearGroupLoadSubjectLecturers")->GetAllLastChanges(array("Subject" => $this->GetID())) as $lect)
				if (($lecturer = new DBLoadSubjectLecturer($lect->GetData()->GetValue("ID")))->IsValid() && $lecturer->IsCountedLecturer($mainonly))
					$lecturers[] = $lecturer;

			return $lecturers;
		}
	}

	final class DBLoadSubjectLecturer extends DBValueWrapper
	{
		protected static string $collection = "YearGroupLoadSubjectLecturers";
	
		public function GetSubject(): ?DBLoadSubject
		{ return $this->GetForeign("Subject", DBLoadSubject::class); }

		public function GetLecturer(): ?DBLecturer
		{ return $this->GetForeign("Lecturer", DBLecturer::class); }

		public function IsCountedLecturer(?bool $mainonly = null): bool
		{
			if ($this->GetValue()->GetValue("Main") == 1) return true;

			return match($mainonly) {
				null => true,
				true => false,
				default => ($subjval = $this->GetSubject()->GetValue())->GetValue("LPHours") / max(1, $subjval->GetValue("Hours")) >= UserConfig::GetParameter("UseAdditionalLecturersLPFraction"),
			};
		}

		public function GetHours(): int
		{
			if ($this->GetValue()->GetValue("Main") == 1) return $this->GetSubject()->GetValue()->GetValue("Hours");

			return ($value = $this->GetSubject()->GetValue())->GetValue("LPHours") + $value->GetValue("CDHours") + $this->GetCDPHours();
		}

		public function GetWHours(): int
		{
			return $this->IsCountedLecturer(false) ? $this->GetSubject()->GetValue()->GetValue("WHours") : 0;
		}

		public function GetStudentsCount(): int
		{
			$sid = $this->GetSubject()?->GetID();
			if (!isset($sid)) return 0;

			$lects = Collections::GetCollection("YearGroupLoadSubjectLecturers")->GetAllLastChanges(array("Subject" => $sid));
			usort($lects, function($a, $b) { return $b->GetData()->GetValue("Main") <=> $a->GetData()->GetValue("Main"); });

			$pos = null;

			foreach ($lects as $num => $lect)
				if ($lect->GetData()->GetValue("ID") == $this->GetID())
				{ $pos = $num; break; }

			if (!isset($pos)) return 0;

			$groups = $this->GetSubject()?->GetLoad()?->GetGroups();
			if (!isset($groups)) return 0;

			$count = 0;
			$lectc = count($lects);

			foreach ($groups as $group)
			{
				$allstudents = ($value = $group->GetValue())->GetValue("BudgetStudents") + $value->GetValue("PaidStudents");
				$count += ceil($allstudents / $lectc * ($pos + 1)) - ceil($allstudents / $lectc * $pos);
			}

			return $count;
		}

		public function GetCDPHours(): int
		{
			$hours = $this->GetSubject()?->GetValue()->GetValue("CDPHours") ?? 0;

			return $hours > 0 ? ceil($this->GetStudentsCount() * UserConfig::GetParameter("HoursCDPerStudent")) : 0;
		}

		public function GetEHours(): int
		{
			return $this->GetValue()->GetValue("Main") == 1 ? $this->GetSubject()->GetValue()->GetValue("EHours") : 0;
		}
	}

	final class DBBells extends DBValueWrapper
	{ protected static string $collection = "BellsSchedules"; }

	final class DBSchedule extends DBValueWrapper
	{ protected static string $collection = "Schedules"; }

	final class DBScheduleHourRoom extends DBValueWrapper
	{
		protected static string $collection = "ScheduleHourRooms";

		public function GetSchedule(): ?DBSchedule
		{ return $this->GetForeign("Schedule", DBSchedule::class); }

		public function GetGroup(): ?DBGroup
		{ return $this->GetForeign("Group", DBGroup::class); }

		public function GetLecturer(): ?DBLecturer
		{ return $this->GetForeign("Lecturer", DBLecturer::class); }

		public function GetRoom(): ?DBRoom
		{ return $this->GetForeign("Room", DBRoom::class); }
	}

	final class DBSubjectRoomCategory extends DBValueWrapper
	{ protected static string $collection = "SubjectRoomCategories"; }
?>