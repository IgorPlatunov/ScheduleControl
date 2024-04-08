<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\{Utils, Core\DBTableRegistryCollections as Collections};
	use DateTime;

	final class SemesterSchedule implements JSONAdaptive, DatabaseAdaptive
	{
		use DatabaseTransaction;

		private array $dayschedules = array();

		public function __construct(
			private int $year,
			private int $semester,
			private DateTime $start,
			private DateTime $end,
		) {}

		public function GetYear(): int { return $this->year; }
		public function GetSemester(): int { return $this->semester; }
		public function GetStartDate(): DateTime { return $this->start; }
		public function GetEndDate(): DateTime { return $this->end; }

		public function SetDaySchedule(int $day, SemesterDaySchedule $schedule): void
		{ $this->dayschedules[$day] = $schedule; }

		public function GetDaySchedule(int $day, bool $create = false): ?SemesterDaySchedule
		{
			return $this->dayschedules[$day] ?? ($create ? $this->dayschedules[$day] = new SemesterDaySchedule() : null);
		}

		public function GetDaySchedules(): array { return $this->dayschedules; }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"year" => $this->year,
				"semester" => $this->semester,
				"start" => Utils::SqlDate($this->start, true),
				"end" => Utils::SqlDate($this->end, true),
				"days" => array(),
			);

			foreach ($this->dayschedules as $day => $schedule)
				if (($sjson = $schedule->ToJSON($names)) !== null)
				$json["days"][$day] = $sjson;

			return $json;
		}

		public static function FromJSON(array $data): SemesterSchedule
		{
			$obj = new SemesterSchedule(
				$data["year"],
				$data["semester"],
				new DateTime($data["start"]),
				new DateTime($data["end"]),
			);

			foreach ($data["days"] as $day => $sdata)
				$obj->SetDaySchedule($day, SemesterDaySchedule::FromJSON($sdata));

			return $obj;
		}

		public function SaveToDB(): void
		{
			$colschedules = Collections::GetCollection("SemesterSchedules");
			$colpairs = Collections::GetCollection("SemesterSchedulePairs");

			$this->DeleteFromDatabase();

			$colschedules->AddChange(array($this->year, $this->semester), array(
				"StartDate" => Utils::SqlDate($this->start),
				"EndDate" => Utils::SqlDate($this->end),
			));

			foreach ($this->GetDaySchedules() as $day => $dschedule)
				foreach ($dschedule->GetLoadSchedules() as $lid => $lschedule)
				{
					if (!DBLoad::IsValidValue($lid)) continue;

					foreach ($lschedule->GetPairs() as $pairn => $pair)
					{
						$subj = $pair->GetSubject();
						if (!$subj->IsValid()) continue;

						$subj2 = $pair->GetSubject2() ?? $subj;
						if (!$subj2->IsValid()) continue;

						$colpairs->AddChange(array($this->year, $this->semester, $day, $lid, $pairn), array(
							"Subject" => $subj->GetID(),
							"Subject2" => $subj2->GetID(),
							"Type" => $pair->GetType()->value,
						));
					}
				}
		}

		public function DeleteFromDB(): void
		{
			$colschedules = Collections::GetCollection("SemesterSchedules");
			$colpairs = Collections::GetCollection("SemesterSchedulePairs");

			foreach ($colschedules->GetAllLastChanges(array("Year" => $this->year, "Semester" => $this->semester)) as $schedule)
				$colschedules->AddChange($schedule->GetID(), null);

			foreach ($colpairs->GetAllLastChanges(array("Year" => $this->year, "Semester" => $this->semester)) as $pair)
				$colpairs->AddChange($pair->GetID(), null);
		}

		public static function GetFromDatabase(mixed $param): ?SemesterSchedule
		{
			$year = (int)$param[0];
			$semester = (int)$param[1];

			$colschedules = Collections::GetCollection("SemesterSchedules");
			$colpairs = Collections::GetCollection("SemesterSchedulePairs");

			$sched = $colschedules->GetLastChange(array($year, $semester));
			if (!isset($sched)) return null;

			$schedule = new SemesterSchedule($year, $semester, new DateTime($sched->GetData()->GetValue("StartDate")), new DateTime($sched->GetData()->GetValue("EndDate")));
			
			foreach ($colpairs->GetAllLastChanges(array("Year" => $year, "Semester" => $semester)) as $dbpair)
			{
				if (!($load = new DBLoad($dbpair->GetData()->GetDBValue("Load")))->IsValid()) continue;

				$type = SemesterDayLoadSchedulePairType::tryFrom($dbpair->GetData()->GetValue("Type"));
				if (!isset($type)) continue;

				if (!($subject = new DBLoadSubject($dbpair->GetData()->GetDBValue("Subject")))->IsValid()) continue;

				$day = $dbpair->GetData()->GetValue("Day");
				$pair = $dbpair->GetData()->GetValue("Pair");

				$subject2 = null;

				if (
					($type == SemesterDayLoadSchedulePairType::HoursDivided || $type == SemesterDayLoadSchedulePairType::WeeksDivided) &&
					!($subject2 = new DBLoadSubject($dbpair->GetData()->GetDBValue("Subject2")))->IsValid()
				) continue;

				$schedule->GetDaySchedule($day, true)->GetLoadSchedule($load, true)->SetPair($pair, new SemesterDayLoadSchedulePairNormal($subject, $subject2, $type));
			}

			return $schedule;
		}
	}

	final class SemesterDaySchedule implements JSONAdaptive
	{
		private array $schedules = array();

		public function GetLoadSchedules(): array { return $this->schedules; }
		public function AddLoadSchedule(DBLoad $load): SemesterDayLoadSchedule
		{ return $this->schedules[$load->GetID()] = new SemesterDayLoadSchedule(); }

		public function GetLoadSchedule(DBLoad $load, bool $create = false): ?SemesterDayLoadSchedule
		{ return $this->schedules[$load->GetID()] ?? ($create ? $this->AddLoadSchedule($load) : null); }

		public function SetLoadSchedule(DBLoad $load, SemesterDayLoadSchedule $schedule): void
		{ $this->schedules[$load->GetID()] = $schedule; }

		public function ToJSON(bool $names = false): ?array
		{
			if (count($this->schedules) == 0) return null;

			$json = array();

			foreach ($this->schedules as $lid => $schedule)
			{
				$load = new DBLoad($lid);
				if (!$load->IsValid()) continue;

				$sjson = $schedule->ToJSON($names);
				if (!isset($sjson)) continue;

				$json[] = array("load" => $load->ToJSON($names), "schedule" => $sjson);
			}

			return count($json) > 0 ? $json : null;
		}

		public static function FromJSON(array $data): SemesterDaySchedule
		{
			$obj = new SemesterDaySchedule();

			foreach ($data as $sdata)
			{
				$load = DBLoad::FromJSON($sdata["load"]);
				if (!$load->IsValid()) continue;

				$schedule = SemesterDayLoadSchedule::FromJSON($sdata["schedule"]);
				if (!isset($schedule)) continue;

				$obj->SetLoadSchedule($load, $schedule);
			}

			return $obj;
		}
	}

	final class SemesterDayLoadSchedule implements JSONAdaptive
	{
		private array $pairs = array();

		public function GetPairs(): array { return $this->pairs; }

		public function SetPair(int $pairnum, SemesterDayLoadSchedulePair $pair): void
		{ $this->pairs[$pairnum] = $pair; }

		public function GetPair(int $pairnum): ?SemesterDayLoadSchedulePair
		{ return $this->pairs[$pairnum] ?? null; }

		public function RemovePair(int $pairnum): void { unset($this->pairs[$pairnum]); }

		public function ToJSON(bool $names = false): ?array
		{
			if (count($this->pairs) == 0) return null;

			$json = array();

			foreach ($this->pairs as $pairnum => $pair)
			{
				$pjson = $pair->ToJSON($names);
				if (!isset($pjson)) continue;

				$json[$pairnum] = $pjson;
			}

			return count($json) > 0 ? $json : null;
		}

		public static function FromJSON(array $data): SemesterDayLoadSchedule
		{
			$obj = new SemesterDayLoadSchedule();

			foreach ($data as $pairnum => $pdata)
			{
				$pair = SemesterDayLoadSchedulePair::FromJSON($pdata);
				if (!isset($pair)) continue;

				$obj->pairs[$pairnum] = $pair;
			}

			return $obj;
		}
	}

	enum SemesterDayLoadSchedulePairType: int
	{
		case FullPair = 0;
		case FirstHour = 1;
		case SecondHour = 2;
		case HoursDivided = 3;
		case WeeksDivided = 4;
	}

	abstract class SemesterDayLoadSchedulePair implements JSONAdaptive
	{
		private ?array $priorityinfo = null;

		abstract public function GetSubject(): ?DBLoadSubject;
		abstract public function GetSubject2(): ?DBLoadSubject;
		abstract public function GetType(): SemesterDayLoadSchedulePairType;
		
		public function SetPriorityInfo(array $info): void { $this->priorityinfo = $info; }
		public function GetPriorityInfo(): ?array { return $this->priorityinfo; }

		public function ToJSON(bool $names = false): ?array
		{
			if ($this instanceof SemesterDayLoadSchedulePairNormal)
			{
				$subject = $this->GetSubject()->ToJSON($names);
				if (!isset($subject)) return null;

				$subject2 = null;
				if ($this->GetSubject2() !== null)
				{
					$subject2 = $this->GetSubject2()->ToJSON($names);
					if (!isset($subject2)) return null;
				}

				return array(
					"subject" => $subject,
					"subject2" => $subject2,
					"type" => $this->GetType()->value,
					"priorityinfo" => $this->GetPriorityInfo(),
				);
			}
			
			return array();
		}

		public static function FromJSON(array $data): SemesterDayLoadSchedulePair
		{
			if (count($data) == 0) return new SemesterDayLoadSchedulePairEmpty();

			return new SemesterDayLoadSchedulePairNormal(
				DBLoadSubject::FromJSON($data["subject"]),
				isset($data["subject2"]) ? DBLoadSubject::FromJSON($data["subject2"]) : null,
				SemesterDayLoadSchedulePairType::from($data["type"]),
			);
		}
	}

	final class SemesterDayLoadSchedulePairNormal extends SemesterDayLoadSchedulePair
	{
		public function __construct(
			private DBLoadSubject $subject,
			private ?DBLoadSubject $subject2 = null,
			private SemesterDayLoadSchedulePairType $type = SemesterDayLoadSchedulePairType::FullPair,
		) {}

		public function GetSubject(): DBLoadSubject { return $this->subject; }
		public function GetSubject2(): ?DBLoadSubject { return $this->subject2; }
		public function GetType(): SemesterDayLoadSchedulePairType { return $this->type; }
	}

	final class SemesterDayLoadSchedulePairEmpty extends SemesterDayLoadSchedulePair
	{
		public function GetSubject(): ?DBLoadSubject { return null; }
		public function GetSubject2(): ?DBLoadSubject { return null; }
		public function GetType(): SemesterDayLoadSchedulePairType { return SemesterDayLoadSchedulePairType::FullPair; }
	}

	final class SemesterScheduleUnallocPair
	{
		public function __construct(
			private DBLoadSubject $subject,
			private int $hours = 2,
		) {}

		public function GetSubject(): DBLoadSubject { return $this->subject; }
		public function GetHours(): int { return $this->hours; }

		public function SetHours(int $hours): void { $this->hours = $hours; }
	}
?>