<?php
	use ScheduleControl\Logic\{SemesterSchedule, SemesterConstructor, DBLoad, SemesterDayLoadSchedulePairType};
	use ScheduleControl\Core\{Logs, DBTableRegistryCollections as Collections};
	use ScheduleControl\UserConfig;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("SemesterScheduleRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$year = AJAXRequest::GetIntParameter("year", false) ?? false;
		$semester = AJAXRequest::GetIntParameter("semester", false) ?? false;

		if ($year && $semester)
		{
			$schedule = SemesterSchedule::GetFromDatabase(array($year, $semester));
			if (!isset($schedule)) AJAXRequest::ClientError(0, "Расписание на семестр не найдено");

			AJAXRequest::Response($schedule->ToJSON(true));
		}
		else
		{
			$response = array();
			$cache = array();

			foreach (Collections::GetCollection("SemesterSchedules")->GetAllLastChanges() as $schedule)
				if ($year ? $schedule->GetData()->GetValue("Year") == $year : !isset($cache[$syear = $schedule->GetData()->GetValue("Year")]))
					$response[] = $year ? $schedule->GetData()->GetValue("Semester") : $cache[$syear] = $syear;

			sort($response);

			AJAXRequest::Response($response);
		}
	}, 
	function($data) {
		AJAXRequest::CheckUserAccess("SemesterScheduleWrite");

		$type = AJAXRequest::GetParameter("type");
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		if ($type == "construct" || $type == "delete")
		{
			$year = AJAXRequest::GetIntParameter("year");
			$semester = AJAXRequest::GetIntParameter("semester");
			$schedule = null;

			if ($type == "delete")
			{
				$schedule = SemesterSchedule::GetFromDatabase(array($year, $semester));
				if (!isset($schedule)) AJAXRequest::ClientError(1, "Расписание на семестр не найдено");

				$schedule->DeleteFromDatabase();

				Logs::Write("Удалено расписание на семестр (".$schedule->GetYear()." год, ".$schedule->GetSemester()." семестр) (пользователь: ".Session::CurrentUser()->GetLogin().")");
			}
			else
			{
				$lock = count($data) > 0 ? SemesterSchedule::FromJSON($data) : null;
				$schedule = SemesterConstructor::ConstructSchedule($year, $semester, $lock);
				if (!isset($schedule)) AJAXRequest::ServerError("Не удалось сконструировать расписание на семестр (нагрузка на год не найдена)");

				AJAXRequest::Response($schedule->ToJSON(true));
			}
		}
		else if ($type == "check" || $type == "save")
		{
			$schedule = SemesterSchedule::FromJSON($data);

			if ($schedule->GetStartDate() > $schedule->GetEndDate())
				AJAXRequest::ClientError(2, "Расписание имеет некорректный период действия");

			$empty = true;
			foreach ($schedule->GetDaySchedules() as $day => $dschedule)
				if (count($dschedule->GetLoadSchedules()) != 0)
				{
					$empty = false;

					foreach ($dschedule->GetLoadSchedules() as $lid => $lschedule)
					{
						if (!($load = new DBLoad($lid))->IsValid())
						AJAXRequest::ClientError(4, "Расписание имеет некорректную нагрузку группы");

						if (count($lschedule->GetPairs()) == 0)
							AJAXRequest::ClientError(5, "Расписание нагрузки группы ".(new DBLoad($lid))->GetName()." пустое (день ".($day + 1).")");
					}
				}

			if ($empty)
				AJAXRequest::ClientError(3, "Расписание пустое");

			$lectbusyness = array();
			$lectbusynesswd = array();

			$checkLecturers = function($day, $hour, $pair, $subject, $weekdiv) use (&$lectbusyness, &$lectbusynesswd)
			{
				$lecturers = $subject->GetLecturers(true);
				$sid = $subject->GetID();
				$lects = 0;

				foreach ($lecturers as $lecturer)
				{
					$lid = $lecturer->GetLecturer()->GetID();

					if (
						isset($lectbusyness[$lid][$day][$hour]) && $lectbusyness[$lid][$day][$hour] != $sid ||
						($weekdiv === null || $weekdiv === false) && isset($lectbusynesswd[0][$lid][$day][$hour]) && $lectbusynesswd[0][$lid][$day][$hour] != $sid ||
						($weekdiv === null || $weekdiv === true) && isset($lectbusynesswd[1][$lid][$day][$hour]) && $lectbusynesswd[1][$lid][$day][$hour] != $sid
					) $lects++;

					if ($weekdiv === null)
						$lectbusyness[$lid][$day][$hour] = $sid;
					else if ($weekdiv === false)
						$lectbusynesswd[0][$lid][$day][$hour] = $sid;
					else if ($weekdiv === true)
						$lectbusynesswd[1][$lid][$day][$hour] = $sid;
				}

				if ($lects == count($lecturers))
					AJAXRequest::ClientError(9, "Предмет ".$subject->GetName()." нагрузки ".$subject->GetLoad()->GetName()." не удается провести, так как нет свободных преподавателей (день ".($day + 1).", пара $pair, час $hour)");
			};

			$checkSubject = function($day, $hour, $pair, $lid, $subject, $weekdivsubj = null) use (&$checkLecturers, &$lectbusyness, &$lectbusynesswd, &$schedule)
			{
				$load = new DBLoad($lid);

				if (!$subject->IsValid())
					AJAXRequest::ClientError(6, "Обнаружен некорректный предмет в нагрузке ".$load->GetName()." (день ".($day + 1).", пара $pair, час $hour)");

				if ($lid != $subject->GetLoad()->GetID())
					AJAXRequest::ClientError(7, "Предмет ".$subject->GetName()." нагрузки ".$subject->GetLoad()->GetName()." обнаружен в нагрузке ".$load->GetName());

				if (($semester = $subject->GetValue()->GetValue("Semester")) != $schedule->GetSemester())
					AJAXRequest::ClientError(8, "Предмет ".$subject->GetName()." (семестр $semester) нагрузки ".$subject->GetLoad()->GetName()." не может быть установлен в расписание для семестра ".$schedule->GetSemester());

				if (!isset($weekdivsubj))
					$checkLecturers($day, $hour, $pair, $subject, null);
				else
				{
					$checkLecturers($day, $hour, $pair, $subject, false);
					$checkLecturers($day, $hour, $pair, $weekdivsubj, true);
				}
			};

			foreach ($schedule->GetDaySchedules() as $day => $dschedule)
				foreach ($dschedule->GetLoadSchedules() as $lid => $lschedule)
					foreach ($lschedule->GetPairs() as $p => $pair)
					{
						$hour1 = max(UserConfig::GetParameter("ScheduleStartReserveHour"), $p * 2 - 1);
						$hour2 = max(UserConfig::GetParameter("ScheduleStartReserveHour"), $p * 2);

						if ($pair->GetType() == SemesterDayLoadSchedulePairType::FullPair)
						{
							$checkSubject($day, $hour1, $p, $lid, $pair->GetSubject());
							$checkSubject($day, $hour2, $p, $lid, $pair->GetSubject());
						}
						else if ($pair->GetType() == SemesterDayLoadSchedulePairType::FirstHour || $pair->GetType() == SemesterDayLoadSchedulePairType::SecondHour)
							$checkSubject($day, $pair->GetType() == SemesterDayLoadSchedulePairType::FirstHour ? $hour1 : $hour2, $p, $lid, $pair->GetSubject());
						else if ($pair->GetType() == SemesterDayLoadSchedulePairType::HoursDivided)
						{
							$checkSubject($day, $hour1, $p, $lid, $pair->GetSubject());
							$checkSubject($day, $hour2, $p, $lid, $pair->GetSubject2());
						}
						else
						{
							$checkSubject($day, $hour1, $p, $lid, $pair->GetSubject(), $pair->GetSubject2());
							$checkSubject($day, $hour2, $p, $lid, $pair->GetSubject(), $pair->GetSubject2());
						}
					}

			$pairs = SemesterConstructor::FindUnallocPairs($schedule);
			if ($type == "save" && count($pairs) == 0)
			{
				$schedule->SaveToDatabase();

				Logs::Write("Сохранено расписание на семестр (".$schedule->GetYear()." год, ".$schedule->GetSemester()." семестр) (пользователь: ".Session::CurrentUser()->GetLogin().")");
			}

			$unalloc = array();

			foreach ($pairs as $pair)
				$unalloc[] = array(
					"load" => $pair->GetSubject()->GetLoad()->ToJSON(true),
					"subject" => $pair->GetSubject()->ToJSON(true),
					"hours" => $pair->GetHours(),
				);

			AJAXRequest::Response($unalloc);
		}
		else if ($type == "priorityinfo")
		{
			$schedule = SemesterSchedule::FromJSON($data);
			$day = AJAXRequest::GetIntParameter("day");
			$load = new DBLoad(AJAXRequest::GetIntParameter("load"));
			$pnum = AJAXRequest::GetIntParameter("pair");

			AJAXRequest::Response(SemesterConstructor::GetPairPriorityInfo($schedule, $day, $load, $pnum));
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>