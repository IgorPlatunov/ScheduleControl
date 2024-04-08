<?php
	use ScheduleControl\Core\{Logs, DBTableRegistryCollections as Collections};
	use ScheduleControl\Logic\{Constructor, DBBells, DBGroup, GroupScheduleHourActivity, GroupScheduleHourSubject, Schedule, Utilities};
	use ScheduleControl\Utils;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("ScheduleRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$date = AJAXRequest::GetDateTimeParameter("date", false) ?? false;

		if ($date)
		{
			$date->setTime(0, 0);

			$schedule = Schedule::GetFromDatabase($date);
			if (!isset($schedule)) AJAXRequest::ClientError(0, "Расписание занятий не найдено");

			AJAXRequest::Response($schedule->ToJSON(true));
		}
		else
		{
			$year = AJAXRequest::GetIntParameter("year", false) ?? false;
			$month = AJAXRequest::GetIntParameter("month", false) ?? false;

			$response = array();
			$cache = array();

			foreach (Collections::GetCollection("Schedules")->GetAllLastChanges() as $schedule)
			{
				$date = new DateTime($schedule->GetData()->GetValue("Date"));
				$cval = $year && $month ? $date->format("d") : $date->format($year ? "m" : "Y");
			
				if (!isset($cache[$cval]) && (!$year || $date->format("Y") == $year && (!$month || $date->format("m") == $month + 1)))
				{
					$cache[$cval] = true;
					$response[] = Utils::SqlDate($date, true);
				}
			}

			usort($response, function($a, $b) { return new DateTime($a) <=> new DateTime($b); });

			AJAXRequest::Response($response);
		}
	},
	function($data) {
		AJAXRequest::CheckUserAccess("ScheduleWrite");

		$type = AJAXRequest::GetParameter("type");
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		if ($type == "construct")
		{
			$date = AJAXRequest::GetDateTimeParameter("date");
			$bells = new DBBells(AJAXRequest::GetIntParameter("bells"));
			if (!$bells->IsValid()) AJAXRequest::ClientError(1, "Некорректное расписание звонков");

			$lock = count($data) > 0 ? Schedule::FromJSON($data) : null;

			$schedule = Constructor::ConstructSchedule($date, $bells, $lock);
			if (!isset($schedule)) AJAXRequest::ServerError("Не удалось сконструировать расписание");

			AJAXRequest::Response($schedule->ToJSON(true));
		}
		else if ($type == "save" || $type == "check")
		{
			$schedule = Schedule::FromJSON($data);

			if (!$schedule->GetBellsSchedule()->IsValid())
				AJAXRequest::ClientError(3, "Некорректное расписание звонков");

			if (count($schedule->GetGroups()) == 0)
				AJAXRequest::ClientError(4, "Расписание занятий пустое");

			$lectbusyness = array();
			$roombusyness = array();
			$year = Utilities::GetStartYear($schedule->GetDate());

			foreach ($schedule->GetGroups() as $gid => $gschedule)
			{
				if (!($group = new DBGroup($gid))->IsValid())
					AJAXRequest::ClientError(5, "Расписание имеет некорректную группу");
				
				$loads = $group->GetLoads($year);
				if (count($loads) == 0)
					AJAXRequest::ClientError(6, "Группа ".$group->GetName()." не принадлежит ни одной нагрузке");

				if (count($gschedule->GetHours()) == 0)
					AJAXRequest::ClientError(7, "Расписание группы ".$group->GetName()." пустое");

				foreach ($gschedule->GetHours() as $h => $hour)
				{
					if ($hour instanceof GroupScheduleHourActivity)
					{
						if (!$hour->GetOccupation()->IsValid())
							AJAXRequest::ClientError(7, "Некорректная деятельность у группы ".$group->GetName()." (час $h)");
					}
					else if ($hour instanceof GroupScheduleHourSubject)
					{
						if (!$hour->GetOccupation()->IsValid())
							AJAXRequest::ClientError(10, "Некорректный предмет у группы ".$group->GetName()." (час $h)");

						$exists = false;

						foreach ($hour->GetOccupation()->GetLoad()->GetGroups() as $sgroup)
							if ($sgroup->GetID() == $group->GetID()) { $exists = true; break; }

						if (!$exists)
							AJAXRequest::ClientError(11, "Предмет ".$hour->GetOccupation()->GetName()." не предназначен для группы ".$group->GetName()." (предмет не найден в нагрузках группы) (час $h)");

						if (count($hour->GetLecturerRooms()) == 0)
							AJAXRequest::ClientError(12, "Для предмета ".$hour->GetOccupation()->GetName()." группы ".$group->GetName()." не установлены преподаватели (час $h)");
					}

					foreach ($hour->GetLecturerRooms() as $lrid => $lectroom)
					{
						if ($lectroom->GetID() !== null && !$lectroom->GetID()->IsValid())
							AJAXRequest::ClientError(13, "Для часа $h группы ".$group->GetName()." установлена некорректная связь преподавателя и кабинета #$lrid");
						
						if (!$lectroom->GetLecturer()->IsValid())
							AJAXRequest::ClientError(14, "Для часа $h группы ".$group->GetName()." установлен некорректный преподаватель #$lrid");

						if (!$lectroom->GetRoom()->IsValid())
							AJAXRequest::ClientError(15, "Для часа $h группы ".$group->GetName()." установлен некорректный кабинет #$lrid");

						if ($group->GetArea()->GetID() != $lectroom->GetRoom()->GetArea()->GetID())
							AJAXRequest::ClientError(16, "Для часа $h группы ".$group->GetName()." установлен кабинет ".$lectroom->GetRoom()->GetName()." площадки ".$lectroom->GetRoom()->GetArea()->GetName().", которая не соответствует группе (должна быть ".$group->GetArea()->GetName().")");

						$lgroup = $lectbusyness[$lectroom->GetLecturer()->GetID()][$h] ?? null;
						if (isset($lgroup))
						{
							$exists = false;

							foreach ($lgroup->GetLoads($year) as $load)
								foreach ($loads as $gload)
									if ($load->GetID() == $gload->GetID())
									{ $exists = true; break; }

							if (!$exists)
								AJAXRequest::ClientError(17, "Преподаватель ".$lectroom->GetLecturer()->GetName()." не может вести занятие у группы ".$group->GetName().", так как ведет занятие у группы ".$lgroup->GetName()." (час $h)");
						}

						$lectbusyness[$lectroom->GetLecturer()->GetID()][$h] = $group;

						$rgroup = $roombusyness[$lectroom->GetRoom()->GetID()][$h] ?? null;
						if (isset($rgroup))
							AJAXRequest::ClientError(18, "Занятие группы ".$group->GetName()." не может проводиться в кабинете ".$lectroom->GetRoom()->GetName().", так как он занят группой ".$rgroup->GetName()." (час $h)");

						$roombusyness[$lectroom->GetRoom()->GetID()][$h] = $group;
					}
				}
			}

			if ($type == "save")
			{
				$schedule->SaveToDatabase();

				Logs::Write("Сохранено расписание занятий на ".$schedule->GetDate()->format("d.m.Y")." (пользователь: ".Session::CurrentUser()->GetLogin().")");
			}
		}
		else if ($type == "delete")
		{
			$date = AJAXRequest::GetDateTimeParameter("date");

			$schedule = Schedule::GetFromDatabase($date);
			if (!isset($schedule)) AJAXRequest::ClientError(2, "Расписание не найдено");
			
			$schedule->DeleteFromDatabase();
			Logs::Write("Удалено расписание занятий на ".$schedule->GetDate()->format("d.m.Y")." (пользователь: ".Session::CurrentUser()->GetLogin().")");
		}
		else if ($type == "lectrooms" || $type == "priorityinfo")
		{
			$group = new DBGroup(AJAXRequest::GetIntParameter("group"));
			if (!$group->IsValid()) AJAXRequest::ClientError(3, "Некорректная группа");

			$hour = AJAXRequest::GetIntParameter("hour");
			$schedule = Schedule::FromJSON($data);	

			if ($type == "lectrooms")
			{
				$lectrooms = Constructor::GetLecturerRoomsForScheduleHour($schedule, $group, $hour);
				$response = array();

				foreach ($lectrooms as $lectroom)
					$response[] = $lectroom->ToJSON(true);

				AJAXRequest::Response($response);
			}
			else
				AJAXRequest::Response(Constructor::GetOccupationPriorityInfo($schedule, $group, $hour));
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>