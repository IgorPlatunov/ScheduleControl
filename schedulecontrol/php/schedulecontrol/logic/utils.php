<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\{Core as Core, Core\DBTableRegistryCollections as Collections, Utils};
	use DateTime, DateInterval;

	class Utilities
	{
		static function GetGroupCourse(DBGroup $group, int $year): ?int
		{
			$curriculum = $group->GetCurriculum()?->GetValue();
			if (!isset($curriculum)) return null;

			$start = $curriculum->GetValue("Year");
			$course = 1 + $year - $start;

			if ($curriculum->GetValue("Course2")) $course++;

			return $course > 0 ? $course : null;
		}

		static function GetGroupActivity(DBGroup $group, DateTime $date): ?YearActivity
		{
			$year = self::GetStartYear($date);

			$activities = Collections::GetCollection("YearGraphsActivities")->GetAllLastChanges(array(
				"Year" => $year,
				"Group" => $group->GetID(),
			));
			$weeks = self::GetYearWeeks($year);

			foreach ($activities as $act)
			{
				if (!($activity = new DBActivity($act->GetData()->GetDBValue("Activity")))->IsValid()) continue;

				[$start, $end] = self::GetActivityStartEnd($act, $weeks);
				if (!isset($start, $end)) continue;
				
				if ($date >= $start && $date <= $end)
					return new YearActivity(
						$act->GetData()->GetValue("ID"),
						$activity,
						$act->GetData()->GetValue("Week"),
						$act->GetData()->GetValue("Length"),
						$act->GetData()->GetValue("Semester"),
					);
			}

			return null;
		}

		static function GetGroupSemesterActivities(DBGroup $group, int $year, int $semester): array
		{
			$yearacts = Collections::GetCollection("YearGraphsActivities")->GetAllLastChanges(array(
				"Year" => $year,
				"Group" => $group->GetID(),
				"Semester" => $semester,
			));

			$activities = array();

			foreach ($yearacts as $act)
			{
				if (!($activity = new DBActivity($act->GetData()->GetDBValue("Activity")))->IsValid()) continue;

				$activities[] = new YearActivity(
					$act->GetData()->GetValue("ID"),
					$activity,
					$act->GetData()->GetValue("Week"),
					$act->GetData()->GetValue("Length"),
					$semester,
				);
			}

			usort($activities, function($a, $b) { return $a->GetWeek() <=> $b->GetWeek(); });

			return $activities;
		}

		static function GetGroupSemesterStartEnd(DBGroup $group, int $year, int $semester): ?array
		{
			$acts = self::GetGroupSemesterActivities($group, $year, $semester);
			if (count($acts) == 0) return null;

			$weeks = self::GetYearWeeks($year);
			$start = null;
			$end = null;

			foreach ($acts as $act)
			{
				[$astart, $aend] = self::GetActivityStartEnd($act, $weeks);

				if (!isset($start) || $astart < $start) $start = $astart;
				if (!isset($end) || $aend > $end) $end = $aend;
			}

			return [$start, $end];
		}

		static function GetActivityStartEnd(Core\RegistryObject|YearActivity $activity, int|array $yearorweeks): array
		{
			$weeks = is_int($yearorweeks) ? self::GetYearWeeks($yearorweeks) : $yearorweeks;

			$week = $activity instanceof YearActivity ? $activity->GetWeek() : $activity->GetData()->GetValue("Week");
			$length = $activity instanceof YearActivity ? $activity->GetLength() : $activity->GetData()->GetValue("Length");

			$start = self::GetWeekDate($weeks, $week);
			$last = self::GetWeekDate($weeks, $week + $length);
			if (isset($last)) date_sub($last, new DateInterval("P1D"));

			return [$start, $last];
		}

		static function GetStartYear(DateTime $date): int
		{ return (int)$date->format("Y") + ($date->format("n") < 9 ? -1 : 0); }

		static function GetWorkDaysCount(DateTime $start, DateTime $end, bool $countsaturdays = false): int
		{
			$date = clone $start;
			$count = 0;

			while ($date <= $end)
			{
				if (self::IsWorkDay($date, $countsaturdays))
					$count++;
				
				date_add($date, new DateInterval("P1D"));
			}

			return $count;
		}

		static function IsWorkDay(DateTime $date, bool $countsaturdays = false): bool
		{
			[,,$day] = Utils::GetDateWeek($date);

			return $day < ($countsaturdays ? 6 : 5);
		}

		static function IsLecturerAvailable(DBLecturer $lecturer, DateTime $date, ?DateTime $enddate = null): bool
		{
			$infos = Collections::GetCollection("NotAvailableLecturers")->GetAllLastChanges(array("Lecturer" => $lecturer->GetID()));
			if (!isset($enddate)) $enddate = $date;

			foreach ($infos as $info)
			{
				$start = new DateTime($info->GetData()->GetValue("StartDate"));
				$end = new DateTime($info->GetData()->GetValue("EndDate"));

				if ($date <= $end && $enddate >= $start)
					return false;
			}

			return true;
		}

		static function IsRoomAvailable(DBRoom $room, DateTime $date, ?DateTime $enddate = null): bool
		{
			$infos = Collections::GetCollection("NotAvailableRooms")->GetAllLastChanges(array("Room" => $room->GetID()));
			if (!isset($enddate)) $enddate = $date;

			foreach ($infos as $info)
			{
				$start = new DateTime($info->GetData()->GetValue("StartDate"));
				$end = new DateTime($info->GetData()->GetValue("EndDate"));

				if ($date <= $end && $enddate >= $start)
					return false;
			}

			return true;
		}

		static function IsGroupUseSaturdays(DBGroup $group, int|DateTime $dateoryear): bool
		{
			$year = is_int($dateoryear) ? $dateoryear : self::GetStartYear($dateoryear);
			$course = self::GetGroupCourse($group, $year);

			return isset($course) && $course == 1;
		}

		static function GetYearWeeks(int $year): array
		{
			$date = new DateTime();
			$date->setDate($year, 9, 1);
			$date->setTime(0, 0);

			$ndate = new DateTime();
			$ndate->setDate($year + 1, 9, 1);
			$ndate->setTime(0, 0);

			[$start,, $day] = Utils::GetDateWeek($date);
			if ($day > 4) date_add($start, new DateInterval("P1W"));
			
			$weeks = array();

			while ($start < $ndate)
			{
				$weeks[] = clone $start;

				date_add($start, new DateInterval("P1W"));
			}

			return $weeks;
		}

		static function GetWeekNumber(int|array $yearorweeks, DateTime $date): ?float
		{
			$weeks = is_int($yearorweeks) ? self::GetYearWeeks($yearorweeks) : $yearorweeks;
			[$start] = Utils::GetDateWeek($date);

			$curweek = 0;

			foreach ($weeks as $week)
				if ($week == $start) return $curweek + (date_diff($week, $date)->days / 7);
				else $curweek++;

			return null;
		}

		static function GetWeekDate(int|array $yearorweeks, float $weekn): ?DateTime
		{
			$weeks = is_int($yearorweeks) ? self::GetYearWeeks($yearorweeks) : $yearorweeks;
			$week = $weeks[floor($weekn)] ?? null;
			if (!isset($week)) return null;

			$date = clone $week;
			date_add($date, new DateInterval("P".round(fmod($weekn, 1) * 7)."D"));

			return $date;
		}

		static function GetStudentsForLecturer(DBLecturer $lecturer, DBLoadSubject $subject, null|DBGroup|array $groups = null): int
		{
			$load = $subject->GetLoad();
			if (!isset($load)) return 0;

			$lecturers = Collections::GetCollection("YearGroupLoadSubjectLecturers")->GetAllLastChanges(array("Subject" => $subject->GetID()));
			$count = count($lecturers);

			if ($count == 0) return 0;

			$positions = array();
			foreach ($lecturers as $pos => $ldata)
				if ($ldata->GetData()->GetDBValue("Lecturer") == $lecturer->GetID())
					$positions[] = $pos;

			if (count($positions) == 0) return 0;

			if (isset($groups))
			{
				if ($groups instanceof DBGroup) $groups = array($groups);

				$filter = array();

				foreach ($groups as $group)
					$filter[$group->GetID()] = true;

				$groups = $filter;
			}

			$studcount = 0;

			foreach (Collections::GetCollection("YearGroupLoadGroups")->GetAllLastChanges(array("Load" => $load->GetID())) as $gdata)
			{
				$group = new DBGroup($gdata->GetData()->GetDBValue("Group"));
				if (!$group->IsValid()) continue;

				if (isset($groups) && !isset($groups[$group->GetID()])) continue;

				$students = $group->GetValue()->GetValue("BudgetStudents") + $group->GetValue()->GetValue("PaidStudents");
				
				foreach ($positions as $pos)
					$studcount += ceil($students / $count * ($pos + 1)) - ceil($students / $count * $pos);
			}

			return $studcount;
		}
	}
?>