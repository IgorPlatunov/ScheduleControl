<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\{Utils, Core\DBTableRegistryCollections as Collections, UserConfig, Core\Logs};
	use DateTime, DateInterval, RuntimeException;

	final class Constructor
	{
		static PriorityAlgorithm $algorithm;
		static PriorityAlgorithm $occalg;
		static PriorityAlgorithm $houralg;

		public static function ConstructSchedule(DateTime $date, DBBells $bells, ?Schedule $lock = null): ?Schedule
		{
			self::PrepareAlgorithm($date, $bells, $lock);
			self::ProcessAlgorithm();
			self::SetupScheduleHours();

			return self::$algorithm->GetProcValue("Schedule");
		
			/* $fhour = null;
			while (($phour = self::GetPriorityHour($fhour)) !== null)
			{
				foreach (self::$groupinfo[$phour["group"]]["Loads"]->GetGroups() as $group)
				{
					if (!isset(self::$groupinfo[$group->GetID()])) continue;
					if ($phour["group"] != $group->GetID() && (self::$groupinfo[$group->GetID()]["NoSchedule"] || self::$groupinfo[$phour["group"]]["NoSchedule"])) continue;

					$said = $phour["occupation"]->GetID();
					if ($phour["occupation"] instanceof DBLoadSubject)
					{
						if (!isset(self::$groupinfo[$group->GetID()]["Subjects"][$said]) || self::$groupinfo[$group->GetID()]["SubjectLeftHours"][$said] <= 0)
							continue;
					}

					self::SetScheduleHour($group->GetID(), $phour["hour"], $said, $phour["occupation"] instanceof DBActivity);
				}

				$fhour = !isset($fhour) && $phour["hour"] % 2 == 1 ? $phour : null;
			}

			self::SetupScheduleRooms();
			self::ApplyScheduleHours();

			return $schedule; */
		}

		public static function GetLecturerRoomsForScheduleHour(Schedule $schedule, DBGroup $group, int $hour): array
		{
			$occupation = $schedule->GetGroup($group)?->GetHour($hour)?->GetOccupation();
			$schedule->GetGroup($group)?->RemoveHour($hour);

			self::PrepareAlgorithm($schedule->GetDate(), $schedule->GetBellsSchedule(), $schedule);

			return self::GetLecturerRoomsForHour($group, $hour, $occupation);
		}

		public static function GetOccupationPriorityInfo(Schedule $schedule, DBGroup $group, int $hour): array
		{
			$info = array("Нет информации по формированию");

			$occupation = $schedule->GetGroup($group)?->GetHour($hour)?->GetOccupation();
			$schedule->GetGroup($group)?->RemoveHour($hour);

			self::PrepareAlgorithm($schedule->GetDate(), $schedule->GetBellsSchedule(), $schedule);

			$object = self::$algorithm->AddObjectToProcess($group);
			$object->SetProcValue("PriorityOccupation", $occupation);
			$object->SetProcValue("PriorityHour", $hour);
			$object->UpdatePriority();
			$pinfo = $object->GetPriorityInfo();

			$info = array("Отдельное формирование. Приоритет часа: ".$pinfo["priority"]);
			foreach ($pinfo["criterions"] as $criterion) $info[] = $criterion;

			return $info;
		}

		private static function PrepareAlgorithm(DateTime $date, DBBells $bells, ?Schedule $lock = null): bool
		{
			$year = Utilities::GetStartYear($date);
			$yeargraph = YearGraph::GetFromDatabase($year);
			if (!isset($yeargraph)) return false;

			$yearload = YearLoad::GetFromDatabase($year);
			if (!isset($yearload)) return false;

			$yearweeks = Utilities::GetYearWeeks($year);
			$weeknum = (int)(Utilities::GetWeekNumber($yearweeks, $date) ?? 0);

			[,,$weekday] = Utils::GetDateWeek($date);

			self::$algorithm->PrepareProcess();

			self::$algorithm->SetProcValue("GroupBusyness", array());
			self::$algorithm->SetProcValue("LecturerBusyness", array());
			self::$algorithm->SetProcValue("RoomBusyness", array());
			self::$algorithm->SetProcValue("SemesterSchedules", array());

			self::$algorithm->SetProcValue("GroupInfo", array());
			self::$algorithm->SetProcValue("LecturerInfo", array());
			self::$algorithm->SetProcValue("PriorityInfo", array());
			self::$algorithm->SetProcValue("AddOrder", 1);
			self::$algorithm->SetProcValue("Schedule", $schedule = new Schedule($date, $bells));

			foreach (Collections::GetCollection("Groups")->GetAllLastChanges() as $gobj)
			{
				$group = new DBGroup($gid = $gobj->GetData()->GetValue("ID"));
				if ($group->GetArea() === null) continue;

				$info = self::BuildGroupInfo($group, $date, $year, $yearweeks, $yearload);
				if (!isset($info)) continue;

				$info["SubjectInitLeftHours"] = array();
				$semester = $info["Semester"];

				foreach ($info["SubjectLeftHours"] as $sid => $hours)
				{
					$info["SubjectInitLeftHours"][$sid] = $hours;

					foreach (($subject = new DBLoadSubject($sid))->GetLecturers(false) as $loadlect)
					{
						$lid = $loadlect->GetLecturer()?->GetID();
						if (!isset($lid)) continue;

						$linfo = &self::$algorithm->GetProcValue("LecturerInfo");

						$linfo[$lid]["Semesters"][$semester]["Subjects"][$sid] = ($linfo[$lid]["Semesters"][$semester]["Subjects"][$sid] ?? 0) + $hours;
						$linfo[$lid]["Semesters"][$semester]["Sum"] = ($linfo[$lid]["Semesters"][$semester]["Sum"] ?? 0) + $hours;
					}
				}

				$info["SubjectAllInitLeftHours"] = $info["SubjectAllLeftHours"];
				$info["HoursPerDay"] = round($info["SubjectAllLeftHours"] / 2 / max(1, $info["ActivityDaysLeft"])) * 2;

				self::$algorithm->GetProcValue("GroupInfo")[$gid] = $info;
				self::$algorithm->AddObjectToProcess($group);
			}

			unset($linfo);

			foreach (self::$algorithm->GetProcValue("LecturerInfo") as $lid => $linfo)
			{
				$lecturer = new DBLecturer($lid);

				foreach ($linfo["Semesters"] as $s => $semester)
					foreach ($lecturer->GetAttachedSubjects($year, $s, false) as $subject)
						if ($subject->GetSubject()->GetValue()->GetValue("Practic") == 1)
							foreach (($gload = $subject->GetLoad())->GetGroups() as $group)
								if (($groupacts = $yeargraph->GetGroup($group)) !== null)
									foreach ($groupacts->GetActivities() as $act)
										if (self::IsActivitySuitableForSubject($subject, $act->GetActivity(), $gload))
										{
											[$start, $end] = Utilities::GetActivityStartEnd($act, $yearweeks);

											if ($date >= $start && $date <= $end)
												self::$algorithm->GetProcValue("LecturerInfo")[$lid]["Practic"] = $group->GetID();
											else
												self::$algorithm->GetProcValue("LecturerInfo")[$lid]["Practics"][] = array($start, $end, $group->GetID());
										}
			}

			if (isset($lock))
				foreach ($lock->GetGroups() as $gid => $gschedule)
					if (($group = new DBGroup($gid))->IsValid())
						foreach ($gschedule->GetHours() as $h => $hour)
							self::SetScheduleHour($group, $h, $hour);

			foreach(SemesterConstructor::GetSemesterSchedulesForDate($date) as $num => $schedule)
				if (($dschedule = $schedule->GetDaySchedule($weekday)) !== null)
					foreach ($dschedule->GetLoadSchedules() as $lid => $lschedule)
						if (($load = new DBLoad($lid))->IsValid())
							foreach ($lschedule->GetPairs() as $pnum => $pair)
								if ($pair instanceof SemesterDayLoadSchedulePairNormal)
								{
									$hour1 = max(UserConfig::GetParameter("ScheduleStartReserveHour"), $pnum * 2 - 1);
									$hour2 = max(UserConfig::GetParameter("ScheduleStartReserveHour"), $pnum * 2);
									$type = $pair->GetType();

									if ($pair->GetSubject2() == null)
										for ($h = ($type == SemesterDayLoadSchedulePairType::SecondHour ? $hour2 : $hour1); $h <= ($type == SemesterDayLoadSchedulePairType::FirstHour ? $hour1 : $hour2); $h++)
											self::SetScheduleHourFromSemesterSchedule($num, $load, $h, $pair->GetSubject());
									else if ($type == SemesterDayLoadSchedulePairType::HoursDivided)
									{
										self::SetScheduleHourFromSemesterSchedule($num, $load, $hour1, $pair->GetSubject());
										self::SetScheduleHourFromSemesterSchedule($num, $load, $hour2, $pair->GetSubject2());
									}
									else
										for ($h = $hour1; $h <= $hour2; $h++)
											self::SetScheduleHourFromSemesterSchedule($num, $load, $h, $weeknum % 2 == 0 ? $pair->GetSubject() : $pair->GetSubject2());
								}
			
			return true;
		}

		private static function ProcessAlgorithm(): void
		{
			set_time_limit(900);

			self::$algorithm->Process(function($hour) {
				self::SetAlgorithmScheduleHour($hour);

				return false;
			});
		}

		private static function SetScheduleHourFromSemesterSchedule(int $num, DBLoad $load, int $hour, DBLoadSubject $subject): void
		{
			if (!$subject->IsValid()) return;

			foreach ($load->GetGroups() as $group)
			{
				$ginfo = self::$algorithm->GetProcValue("GroupInfo")[$group->GetID()] ?? null;
				if (!isset($ginfo)) continue;

				self::$algorithm->GetProcValue("SemesterSchedules")[$num][$group->GetID()][$hour] = $subject->GetID();
			}
		}

		private static function SetAlgorithmScheduleHour(PriorityAlgorithmObject $object): void
		{
			Logs::Write("Расписание занятий - формирование: группа ".$object->GetObject()->GetName().", час ".$object->GetProcValue("Hour").", занятость ".($object->GetProcValue("Occupation")?->GetName() ?? "-"));

			$group = $object->GetObject();
			$occupation = $object->GetProcValue("Occupation");
			$hour = $object->GetProcValue("Hour");
			$lectrooms = $object->GetProcValue("LecturerRooms");
			$schedhour = null;

			if ($occupation instanceof DBLoadSubject)
				$schedhour = new GroupScheduleHourSubject($occupation);
			else if ($occupation instanceof DBActivity)
				$schedhour = new GroupScheduleHourActivity($occupation);
			else
				$schedhour = new GroupScheduleHourEmpty();

			foreach ($lectrooms as $lectroom)
				$schedhour->AddLecturerRoom($lectroom);

			$pinfo = $object->GetPriorityInfo();
			$info = array("Добавление занятости №".self::$algorithm->GetProcValue("AddOrder")." (полное формирование). Приоритет часа: ".$pinfo["priority"]);

			foreach ($pinfo["criterions"] as $criterion)
				$info[] = $criterion;

			self::$algorithm->SetProcValue("AddOrder", self::$algorithm->GetProcValue("AddOrder") + 1);
			$schedhour->SetPriorityInfo($info);

			self::SetScheduleHour($group, $hour, $schedhour);
		}

		private static function SetScheduleHour(DBGroup $group, int $h, DBLoadSubject|DBActivity|null|GroupScheduleHour $hour): void
		{
			$occupation = $hour;
			$lectrooms = array();
			$gid = $group->GetID();

			if ($hour instanceof GroupScheduleHour)
			{
				$occupation = $hour->GetOccupation();
				$lectrooms = $hour->GetLecturerRooms();

				if ($hour->GetPriorityInfo() !== null)
					self::$algorithm->GetProcValue("PriorityInfo")[$gid][$h] = $hour->GetPriorityInfo();
			}
			else
				$lectrooms = self::GetLecturerRoomsForHour($group, $h, $occupation);

			self::$algorithm->GetProcValue("GroupBusyness")[$gid][$h] = $occupation;

			if ($occupation instanceof DBLoadSubject)
			{
				$ginfo = &self::$algorithm->GetProcValue("GroupInfo");
				$sid = $occupation->GetID();

				if (isset($ginfo[$gid]["Subjects"][$sid]))
				{
					$ginfo[$gid]["SubjectLeftHours"][$sid]--;
					$ginfo[$gid]["SubjectAllLeftHours"]--;
				}

				foreach ($occupation->GetLecturers(false) as $loadlect)
				{
					$lid = $loadlect->GetLecturer()?->GetID();
					if (!isset($lid)) continue;

					$linfo = &self::$algorithm->GetProcValue("LecturerInfo");

					if (isset($linfo[$lid]["Semesters"][$ginfo[$gid]["Semester"]]["Subjects"][$sid]))
					{
						$linfo[$lid]["Semesters"][$ginfo[$gid]["Semester"]]["Subjects"][$sid]--;
						$linfo[$lid]["Semesters"][$ginfo[$gid]["Semester"]]["Sum"]--;
					}
				}
			}

			$flectrooms = array();

			foreach ($lectrooms as $lectroom)
			{
				if (!$lectroom->GetLecturer()->IsValid() || !$lectroom->GetRoom()->IsValid()) continue;

				$flectrooms[] = $lectroom;
				$roombusyness = self::$algorithm->GetProcValue("RoomBusyness")[$lectroom->GetRoom()->GetID()][$h] ?? null;

				self::$algorithm->GetProcValue("LecturerBusyness")[$lectroom->GetLecturer()->GetID()][$h][$gid] = true;
				self::$algorithm->GetProcValue("RoomBusyness")[$lectroom->GetRoom()->GetID()][$h] = $lectroom->GetLecturer();

				if (isset($roombusyness) && $roombusyness->GetID() != $lectroom->GetLecturer()->GetID())
				{
					$room = self::FindRoomForHourLecturer($group, $h, $occupation);
					self::$algorithm->GetProcValue("RoomBusyness")[$room->GetID()][$h] = $roombusyness;

					Logs::Write("Смена кабинета - преподаватель ".$roombusyness->GetName().", прежний кабинет ".$lectroom->GetRoom()->GetName().", новый кабинет ".$room->GetName());
				}
			}
		}

		private static function SetupScheduleHours(): void
		{
			foreach (self::$algorithm->GetProcValue("GroupBusyness") as $gid => $hours)
				foreach ($hours as $h => $occupation)
				{
					$hour = null;

					if ($occupation instanceof DBLoadSubject)
					{
						$hour = new GroupScheduleHourSubject($occupation);

						foreach (self::$algorithm->GetProcValue("LecturerBusyness") as $lid => $lhours)
							if (isset($lhours[$h][$gid]))
								foreach (self::$algorithm->GetProcValue("RoomBusyness") as $rid => $rhours)
									if (isset($rhours[$h]) && $rhours[$h]->GetID() == $lid)
									{ $hour->AddLecturerRoom(new GroupScheduleHourRoom(null, new DBLecturer($lid), new DBRoom($rid))); break; }
					}
					else if ($occupation instanceof DBActivity)
						$hour = new GroupScheduleHourActivity($occupation);
					else
						$hour = new GroupScheduleHourEmpty();

					$pinfo = self::$algorithm->GetProcValue("PriorityInfo")[$gid][$h] ?? null;
					if (isset($pinfo)) $hour->SetPriorityInfo($pinfo);

					self::$algorithm->GetProcValue("Schedule")->GetGroup(new DBGroup($gid), true)->AddHour($h, $hour);
				}
		}

		private static function GetHourTimes(int $hour): array
		{
			$schedule = self::$algorithm->GetProcValue("Schedule");
			$start = clone $schedule->GetDate();
			$end = clone $start;

			$times = Collections::GetCollection("BellsScheduleTimes")->GetLastChange(array($schedule->GetBellsSchedule()->GetID(), $hour));
			if (isset($times))
			{
				$from = new DateTime($times->GetData()->GetValue("StartTime"));
				$to = new DateTime($times->GetData()->GetValue("EndTime"));

				$start->setTime($from->format("H"), $from->format("i"));
				$end->setTime($to->format("H"), $to->format("i"));
			}
			else
				date_add($end, new DateInterval("P1D"));

			return [$start, $end];
		}

		private static function GetLecturersForHour(DBGroup $group, int $hour, DBLoadSubject|DBActivity|null $occupation): array
		{
			[$start, $end] = self::GetHourTimes($hour);
			$lecturers = array();

			if ($occupation instanceof DBLoadSubject)
				foreach ($occupation->GetLecturers(false) as $loadlect)
				{
					$lecturer = $loadlect->GetLecturer();
					if (!Utilities::IsLecturerAvailable($lecturer, $start, $end)) continue;

					$groups = self::$algorithm->GetProcValue("LecturerBusyness")[$lecturer->GetID()][$hour] ?? null;
					$busy = false;

					if (isset($groups))
						foreach ($groups as $gid => $_)
							if (!$group->IsFromSameLoad(new DBGroup($gid)))
								{ $busy = true; break; }
					
					if (!$busy)
						$lecturers[] = $lecturer;
				}

			return $lecturers;
		}

		public static function GetLecturerRoomsForHour(DBGroup $group, int $hour, DBLoadSubject|DBActivity|null $occupation): array
		{
			$lectrooms = array();

			foreach (self::GetLecturersForHour($group, $hour, $occupation) as $lecturer)
			{
				$attrooms = array();
				
				foreach ($lecturer->GetAttachedRooms() as $room)
					if ($room->GetArea()?->GetID() == $group->GetArea()?->GetID())
						$attrooms[] = $room;

				if (count($attrooms) > 0)
					foreach ($attrooms as $room)
					{
						/* $busyness = self::$algorithm->GetProcValue("RoomBusyness")[$room->GetID()][$hour] ?? null;

						if (!isset($busyness) || $busyness->GetID() == $lecturer->GetID())
						{  */$lectrooms[] = new GroupScheduleHourRoom(null, $lecturer, $room);/*  break; } */
					}
				else
					$lectrooms[] = new GroupScheduleHourRoom(null, $lecturer, self::FindRoomForHourLecturer($group, $hour, $occupation, $lectrooms));
			}
				
			return $lectrooms;
		}

		private static function FindRoomForHourLecturer(DBGroup $group, int $hour, ?DBLoadSubject $subject, ?array $ignore = null): DBRoom
		{
			[$start, $end] = self::GetHourTimes($hour);

			$subj = $subject?->GetSubject();
			$reserverooms = array();
			$rignore = array();

			if (isset($ignore))
				foreach ($ignore as $lectroom)
					$rignore[$lectroom->GetRoom()->GetID()] = true;

			foreach (Collections::GetCollection("Rooms")->GetAllLastChanges(array("Area" => $group->GetArea()->GetID())) as $obj)
			{
				if (isset($rignore[($room = new DBRoom($obj->GetData()->GetValue("ID")))->GetID()])) continue;

				if (!isset(self::$algorithm->GetProcValue("RoomBusyness")[$room->GetID()][$hour]) && Utilities::IsRoomAvailable($room, $start, $end))
					if (!isset($subj) || $subj->IsRoomSuitable($room))
						return $room;
					else
						$reserverooms[] = $room;
			}
				

			if (count($reserverooms) > 0)
				return $reserverooms[0];

			throw new RuntimeException("Не удалось найти кабинет для преподавателя (все доступные кабинеты заняты?)", 500);
		}

		private static function IsActivityForDefaultSubjects(DBLoad $gload, DBActivity $activity): bool
		{
			foreach ($gload->GetSubjects() as $subject)
				if (($act = $subject->GetSubject()->GetActivity()) !== null && $act->GetID() == $activity->GetID())
					return false;

			return true;
		}

		private static function IsActivitySuitableForSubject(DBLoadSubject $subject, DBActivity $activity, bool|DBLoad $isdef): bool
		{
			if (($subjact = $subject->GetSubject()->GetActivity()) != null)
				return $activity->GetID() == $subjact->GetID();

			if ($isdef instanceof DBLoad)
				$isdef = self::IsActivityForDefaultSubjects($isdef, $activity);

			return $isdef;
		}

		public static function GetGroupInfo(DBGroup $group, DateTime $date): ?array
		{
			$year = Utilities::GetStartYear($date);
			$yearload = YearLoad::GetFromDatabase($year);
			$yearweeks = Utilities::GetYearWeeks($year);

			return self::BuildGroupInfo($group, $date, $year, $yearweeks, $yearload, true);
		}

		private static function BuildGroupInfo(DBGroup $group, DateTime $date, int $year, array &$yearweeks, ?YearLoad $yearload = null, bool $countcurday = false): ?array
		{
			$course = Utilities::GetGroupCourse($group, $year);
			if (!isset($course)) return null;

			$activity = Utilities::GetGroupActivity($group, $date);
			if (!isset($activity)) return null;

			if (count($group->GetLoads($year)) == 0) return null;

			if (!isset($yearload)) $yearload = YearLoad::GetFromDatabase($year);
			if (!isset($yearload)) return null;

			$activities = Utilities::GetGroupSemesterActivities($group, $year, $activity->GetSemester());
			$usesaturdays = Utilities::IsGroupUseSaturdays($group, $year);

			$activitydays = 0;
			$activitydaysleft = 0;
			$actleftdays = array();

			foreach ($activities as $act)
			{
				if ($act->GetActivity()->GetID() != $activity->GetActivity()->GetID()) continue;

				[$start, $end] = Utilities::GetActivityStartEnd($act, $yearweeks);
				while ($start <= $end)
				{
					if (Utilities::IsWorkDay($start, $usesaturdays))
					{
						$activitydays++;

						if ($countcurday ? ($start > $date) : ($start >= $date))
						{
							$activitydaysleft++;
							$actleftdays[] = clone $start;
						}
					}

					date_add($start, new DateInterval("P1D"));
				}
			}

			$info = array(
				"Group" => $group,
				"Loads" => array(),
				"Course" => $course,
				"Activity" => $activity,
				"NoSchedule" => $activity->GetActivity()->GetValue()->GetValue("NoSchedule") == 1,
				"Activities" => $activities,
				"ActivityLeftDays" => $actleftdays,
				"Semester" => $activity->GetSemester(),
				"Saturdays" => $usesaturdays,
				"ActivityDays" => $activitydays,
				"ActivityDaysLeft" => $activitydaysleft,
				"Subjects" => array(),
				"SubjectLeftHours" => array(),
				"SubjectAllHours" => 0,
				"SubjectAllLeftHours" => 0,
			);

			if (isset($yearload))
				foreach ($yearload->GetLoads() as $gload)
				{
					$noload = true;

					foreach ($gload->GetGroups() as $gloadgroup)
						if ($gloadgroup->GetGroup()->GetID() == $group->GetID())
							{ $noload = false; break; }

					if ($noload) continue;

					$info["Loads"][] = $gload;

					if (!$info["NoSchedule"])
					{
						$isdef = self::IsActivityForDefaultSubjects($gload->GetID(), $activity->GetActivity());

						foreach ($gload->GetSubjects() as $subject)
						{
							if (!self::IsActivitySuitableForSubject($subject->GetID(), $activity->GetActivity(), $isdef)) continue;
							if ($subject->GetSemester() != $info["Semester"]) continue;

							$sid = $subject->GetID()->GetID();
							$hours = $subject->GetHours();
							$passhours = 0;
							
							foreach (Collections::GetCollection("ScheduleHours")->GetAllLastChanges(array("Group" => $group->GetID(), "Subject" => $sid)) as $hour)
							{
								$hdate = new DateTime($hour->GetData()->GetValue("Schedule")->GetValue("Date"));

								if ($countcurday ? ($hdate <= $date) : ($hdate < $date))
									$passhours++;
							}

							$lefthours = $hours - $passhours;

							if ($lefthours <= 0) continue;

							$info["Subjects"][$sid] = $subject;
							$info["SubjectLeftHours"][$sid] = $lefthours;
							$info["SubjectAllHours"] += $hours;
							$info["SubjectAllLeftHours"] += $lefthours;
						}
					}
				}

			return $info;
		}

		public static function InitializeAlgorithm(): void
		{
			self::$algorithm = new PriorityAlgorithm(array(
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Рабочий день для группы"; }
					public function GetDescription(): string { return "Проверяет, что текущая дата не является выходным для текущей группы"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$pinfo = $algorithm->GetProcValue("GetPriorityInfo");
						if (isset($pinfo) && $object->GetObject()->GetID() != $pinfo[0]->GetID()) return null;

						$object->SetProcValue("Info", $algorithm->GetProcValue("GroupInfo")[$object->GetObject()->GetID()]);

						if (Utilities::IsWorkDay($algorithm->GetProcValue("Schedule")->GetDate(), $object->GetProcValue("Info")["Saturdays"]))
							return true;

						return null;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Нужно формировать расписание) Остаток часов у группы (часов)"; }
					public function GetDescription(): string { return "Увеличивает приоритет прямопропорционально количеству оставшихся часов на все предметы у группы"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("Info")["NoSchedule"]; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$left = $object->GetProcValue("Info")["SubjectAllLeftHours"];

						return new PriorityAlgorithmCriterionData($left, $left / 100);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Нужно формировать расписание) Процент остатка часов у группы (осталось / всего)"; }
					public function GetDescription(): string { return "Уменьшает приоритет при вычитке всех предметов текущей группы"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("Info")["NoSchedule"]; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$left = $object->GetProcValue("Info")["SubjectAllLeftHours"];
						$init = $object->GetProcValue("Info")["SubjectAllHours"];

						return new PriorityAlgorithmCriterionData($left." / ".$init, $left / $init);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Не нужно формировать расписание) Выставление текущей деятельности (выставлено / всего, часов)"; }
					public function GetDescription(): string { return "Устанавливает часы деятельности до тех пор, пока они не начнут превышать последний час, являющийся нормой в день"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $object->GetProcValue("Info")["NoSchedule"]; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$pocc = $object->GetProcValue("PriorityOccupation");
						if (isset($pocc) && (!($pocc instanceof DBActivity) || $pocc->GetID() != $object->GetProcValue("Info")["Activity"]->GetActivity()->GetID()))
							return false;

						$busyness = $algorithm->GetProcValue("GroupBusyness")[$object->GetObject()->GetID()] ?? null;
						$hours = isset($busyness) ? count($busyness) : 0;
						$hoursperday = 1 + UserConfig::GetParameter("ScheduleEndDefaultHour") - UserConfig::GetParameter("ScheduleStartDefaultHour");

						$hour = UserConfig::GetParameter("ScheduleStartDefaultHour");
						while (isset($busyness[$hour])) $hour++;

						$object->SetProcValue("Hour", $hour);
						$object->SetProcValue("LecturerRooms", array());
						$object->SetProcValue("Occupation", $object->GetProcValue("Info")["Activity"]->GetActivity());

						return new PriorityAlgorithmCriterionData($hours." / ".$hoursperday, $hours < $hoursperday ? 10000 : 0);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Нужно формировать расписание) Выполнение нормы часов в день (выставлено / расчётное кол-во, часов)"; }
					public function GetDescription(): string { return "Уменьшает приоритет при уменьшении количества оставшихся часов, которые должны быть выставлены группе в день"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("Info")["NoSchedule"]; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$busyness = $algorithm->GetProcValue("GroupBusyness")[$object->GetObject()->GetID()] ?? null;
						$object->SetProcValue("Busyness", $busyness);

						$hours = 0;

						if (isset($busyness))
							foreach ($busyness as $h => $occupation)
								if ($occupation !== null) $hours++;
						
						$hoursperday = $object->GetProcValue("Info")["HoursPerDay"];

						return new PriorityAlgorithmCriterionData($hours." / ".$hoursperday, $hours < $hoursperday ? 1 : 0/* max(0, 1 - $hours / $hoursperday) */);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Нужно формировать расписание) Определение приоритетного предмета"; }
					public function GetDescription(): string { return "Определяет приоритетный предмет и модифицирует общий приоритет в соответствии с приоритетом этого предмета"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("Info")["NoSchedule"]; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$occalg = Constructor::$occalg;
						$occalg->PrepareProcess();

						$occalg->SetProcValue("MainAlg", $algorithm);
						$occalg->SetProcValue("MainObj", $object);

						$pocc = $object->GetProcValue("PriorityOccupation");
						foreach ($object->GetProcValue("Info")["Subjects"] as $sid => $subject)
							if (isset($pocc) ? $pocc instanceof DBLoadSubject && $pocc->GetID() == $sid : $object->GetProcValue("Info")["SubjectLeftHours"][$sid] > 0)
								$occalg->AddObjectToProcess($subject);

						$priorocc = null;
						$occalg->Process(function($occ) use (&$priorocc) {
							$priorocc = $occ;

							return true;
						});

						if (!isset($priorocc))
							if (isset($pocc, $occalg->GetObjects()[0]))
								return new PriorityAlgorithmCriterionData(($pobj = $occalg->GetObjects()[0])->GetObject()->GetID()->GetName(), 0, $pobj);
							else
								return false;

						$object->SetProcValue("Occupation", $priorocc->GetObject()->GetID());
						$object->SetProcValue("Hour", $priorocc->GetProcValue("Hour"));
						$object->SetProcValue("LecturerRooms", $priorocc->GetProcValue("LecturerRooms"));

						return new PriorityAlgorithmCriterionData($priorocc->GetObject()->GetID()->GetName(), $priorocc->GetPriority() / 1000, $priorocc);
					}
				}
			));

			self::$occalg = new PriorityAlgorithm(array(
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Предмет является практикой"; }
					public function GetDescription(): string { return "Сильно увеличивает приоритет предметов, которые представляют собой практику"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$practic = $object->GetObject()->GetSubject()->GetValue()->GetValue("Practic") == 1;
						$object->SetProcValue("Practic", $practic);

						if ($practic)
							return new PriorityAlgorithmCriterionData("Да", 10000);
						
						return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Предмет не является практикой) Остаток часов у группы на предмет (часов)"; }
					public function GetDescription(): string { return "Увеличивает приоритет прямопропорционально количеству оставшихся часов на текущий предмет у текущей группы"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("Practic"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$left = $algorithm->GetProcValue("MainObj")->GetProcValue("Info")["SubjectLeftHours"][$object->GetObject()->GetID()->GetID()];

						return new PriorityAlgorithmCriterionData($left, $left / 100);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Предмет не является практикой) Процент остатка часов у группы на предмет (осталось / всего)"; }
					public function GetDescription(): string { return "Уменьшает приоритет при вычитке часов текущего предмета у текущей группы"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("Practic"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$left = $algorithm->GetProcValue("MainObj")->GetProcValue("Info")["SubjectLeftHours"][$object->GetObject()->GetID()->GetID()];
						$init = $object->GetObject()->GetHours();

						return new PriorityAlgorithmCriterionData($left." / ".$init, $left / $init);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Предмет из нагрузки для сборной группы"; }
					public function GetDescription(): string { return "Уменьшает приоритет предметов, нагрузка которых включает несколько групп сразу"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$load = $object->GetObject()->GetID()->GetLoad();

						if (count($load->GetGroups()) > 1)
							return new PriorityAlgorithmCriterionData("Да", 0.05);
						
						return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Не является практикой) Повторение предмета у группы в день (пар)"; }
					public function GetDescription(): string { return "Уменьшает приоритет при повторении пар одного и того же предмета"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("Practic"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$busyness = $algorithm->GetProcValue("MainObj")->GetProcValue("Busyness") ?? array();
						$hours = 0;

						foreach ($busyness as $h => $occupation)
							if ($occupation instanceof DBLoadSubject && $occupation->GetID() == $object->GetObject()->GetID()->GetID())
								$hours++;
						
						$pairs = floor($hours / 2);
						if ($pairs > 0)
							return new PriorityAlgorithmCriterionData($pairs, 1 / ($pairs * 20));
						
						return new PriorityAlgorithmCriterionData("-", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Преподавателей назначено"; }
					public function GetDescription(): string { return "Проверяет, что предмету назначены преподаватели"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$lecturers = $object->GetObject()->GetID()->GetLecturers(false);
						$object->SetProcValue("Lecturers", $lecturers);
						$count = count($lecturers);

						return new PriorityAlgorithmCriterionData($count, $count == 0 ? 0 : 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Определение приоритетного часа"; }
					public function GetDescription(): string { return "Определяет приоритетный номер часа и модифицирует общий приоритет соответственно приоритету этого часа"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$prehour = $algorithm->GetProcValue("MainObj")->GetProcValue("PriorityHour");

						$houralg = Constructor::$houralg;
						$houralg->PrepareProcess();

						$houralg->SetProcValue("MainAlg", $algorithm->GetProcValue("MainAlg"));
						$houralg->SetProcValue("MainObj", $algorithm->GetProcValue("MainObj"));
						$houralg->SetProcValue("Lecturers", $object->GetProcValue("Lecturers"));
						$houralg->SetProcValue("Practic", $object->GetProcValue("Practic"));
						$houralg->SetProcValue("Subject", $object->GetObject());

						$info = $algorithm->GetProcValue("MainObj")->GetProcValue("Info");
						$maxhour = match(true) {
							isset($prehour) => $prehour,
							$info["ActivityDaysLeft"] > 0 => UserConfig::GetParameter("ScheduleEndDefaultHour") * 2,
							default => ceil(max(UserConfig::GetParameter("ScheduleEndDefaultHour"), $info["HoursPerDay"])),
						};

						for ($h = $prehour ?? UserConfig::GetParameter("ScheduleStartReserveHour"); $h <= $maxhour; $h++)
							$houralg->AddObjectToProcess($h);

						$phour = null;
						$houralg->Process(function($hour) use (&$phour) {
							$phour = $hour;

							return true;
						});

						if (!isset($phour))
							if (isset($prehour, $houralg->GetObjects()[0]))
								return new PriorityAlgorithmCriterionData(($phour = $houralg->GetObjects()[0])->GetObject(), 0, $phour);
							else
								return false;

						$object->SetProcValue("Hour", $phour->GetObject());
						$object->SetProcValue("LecturerRooms", $phour->GetProcValue("LecturerRooms"));

						return new PriorityAlgorithmCriterionData($phour->GetObject(), $phour->GetPriority() / 1000, $phour);
					}
				},
			));

			self::$houralg = new PriorityAlgorithm(array(
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Час не занят группой"; }
					public function GetDescription(): string { return "Проверяет, занят ли текущий час текущей группой"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						return !isset($algorithm->GetProcValue("MainObj")->GetProcValue("Busyness")[$object->GetObject()]);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Предмет не является практикой) Соответствует расписанию на семестр"; }
					public function GetDescription(): string { return "Значительно увеличивает приоритет, если данный предмет на данном часу предусмотрен расписанием на семестр у текущей группы"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$algorithm->GetProcValue("Practic"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$sids = array();

						foreach ($algorithm->GetProcValue("MainAlg")->GetProcValue("SemesterSchedules") as $schedule)
						{
							$sid = $schedule[$algorithm->GetProcValue("MainObj")->GetObject()->GetID()][$object->GetObject()] ?? null;

							if (isset($sid)) $sids[] = $sid;
						}

						if (count($sids) == 0)
							return new PriorityAlgorithmCriterionData("Нет данных по расписанию", 1);

						$num = array_search($algorithm->GetProcValue("Subject")->GetID()->GetID(), $sids);

						if ($num === false)
							return new PriorityAlgorithmCriterionData("Нет", 1);

						$object->SetProcValue("SemesterHour", true);
						return new PriorityAlgorithmCriterionData("Да", 10000 / (1 + $num));
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Является резервным часом"; }
					public function GetDescription(): string { return "Уменьшает приоритет, если текущий час является резервным (идёт до первого основного часа, с которого обычно выставляются занятия)"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						if ($object->GetObject() < UserConfig::GetParameter("ScheduleStartDefaultHour"))
							return new PriorityAlgorithmCriterionData("Да", 0.001);
						
						return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Второй час пары, не практика) Соответствие первому часу пары"; }
					public function GetDescription(): string { return "Увеличивает приоритет, если первый час пары существует и текущий предмет ему соответствует. Уменьшает приоритет, если первого часа пары нет"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool {
						return $object->GetObject() % 2 == 0 && $object->GetObject() > UserConfig::GetParameter("ScheduleStartReserveHour") && !$algorithm->GetProcValue("Practic");
					}
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hour1 = $object->GetObject() - 1;
						$occ = $algorithm->GetProcValue("MainObj")->GetProcValue("Busyness")[$hour1] ?? null;

						if (!isset($occ))
							return new PriorityAlgorithmCriterionData("Не установлен", 0.01);
						else if ($occ instanceof DBLoadSubject && $occ->GetID() == $algorithm->GetProcValue("Subject")->GetID()->GetID())
							return new PriorityAlgorithmCriterionData("Да", 100);
						
						return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Превышение последнего часа в день"; }
					public function GetDescription(): string { return "Уменьшает приоритет, если текущий час больше последнего часа, который считается нормой в день"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$diff = $object->GetObject() - UserConfig::GetParameter("ScheduleEndDefaultHour");

						if ($diff > 0)
							return new PriorityAlgorithmCriterionData($diff, 1 / ($diff * 20));
						
						return new PriorityAlgorithmCriterionData("-", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Количество доступных преподавателей с доступными кабинетами (свободно / всего)"; }
					public function GetDescription(): string { return "Уменьшает приоритет, если не все преподаватели могут вести предмет в текущее время"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$freelects = Constructor::GetLecturerRoomsForHour($algorithm->GetProcValue("MainObj")->GetObject(), $object->GetObject(), $algorithm->GetProcValue("Subject")->GetID());
						$alllects = count($algorithm->GetProcValue("Lecturers"));

						$object->SetProcValue("LecturerRooms", $freelects);

						return new PriorityAlgorithmCriterionData(count($freelects)." / ".$alllects, count($freelects) / $alllects);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Не предусмотрен расписанием на семестр) Разница между первым незанятым и текущим часами группы"; }
					public function GetDescription(): string { return "Уменьшает приоритет текущего часа, если он не заполняет пустые часы между существующими часами группы, если они есть"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("SemesterHour"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hour = UserConfig::GetParameter("ScheduleStartDefaultHour");

						while (isset($algorithm->GetProcValue("MainObj")->GetProcValue("Busyness")[$hour]))
							$hour++;

						$diff = $object->GetObject() - $hour;

						if ($diff > 0)
							return new PriorityAlgorithmCriterionData($diff, 1 / ($diff * 10));
						
						return new PriorityAlgorithmCriterionData("-", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Не предусмотрен расписанием на семестр) Разница между первым незанятым и текущим часами преподавателей"; }
					public function GetDescription(): string { return "Уменьшает приоритет текущего часа, если он не заполняет пустые часы между существующими часами преподавателей, если они есть"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$object->GetProcValue("SemesterHour"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$diff = 0;

						foreach ($object->GetProcValue("LecturerRooms") as $lectroom)
						{
							$hour = UserConfig::GetParameter("ScheduleStartDefaultHour");

							while (isset($algorithm->GetProcValue("MainAlg")->GetProcValue("LecturerBusyness")[$lectroom->GetLecturer()->GetID()][$hour]))
								$hour++;

							$diff += max(0, $object->GetObject() - $hour);
						}

						$diff /= count($object->GetProcValue("LecturerRooms"));

						if ($diff > 0)
							return new PriorityAlgorithmCriterionData($diff, 1 / ($diff * 2));
						
						return new PriorityAlgorithmCriterionData("-", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Предмет не является практикой) Остаток часов у преподавателей в семестре"; }
					public function GetDescription(): string { return "Увеличивает приоритет прямопропорционально количеству оставшихся часов у преподавателей на все предметы в семестре, в котором изучается текущий предмет"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$algorithm->GetProcValue("Practic"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hours = 0;
						$semester = $algorithm->GetProcValue("MainObj")->GetProcValue("Info")["Semester"];

						foreach ($object->GetProcValue("LecturerRooms") as $lectroom)
							$hours += $algorithm->GetProcValue("MainAlg")->GetProcValue("LecturerInfo")[$lectroom->GetLecturer()->GetID()]["Semesters"][$semester]["Sum"] ?? 0;

						$hours /= count($object->GetProcValue("LecturerRooms"));

						return new PriorityAlgorithmCriterionData($hours, $hours / 100);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Предмет не является практикой) Остаток часов у преподавателей в семестре на предмет"; }
					public function GetDescription(): string { return "Увеличивает приоритет прямопропорционально количеству оставшихся часов у преподавателей на текущий предмет в семестре, в котором изучается текущий предмет"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$algorithm->GetProcValue("Practic"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hours = 0;
						$semester = $algorithm->GetProcValue("MainObj")->GetProcValue("Info")["Semester"];
						$sid = $algorithm->GetProcValue("Subject")->GetID()->GetID();

						foreach ($object->GetProcValue("LecturerRooms") as $lectroom)
							$hours += $algorithm->GetProcValue("MainAlg")->GetProcValue("LecturerInfo")[$lectroom->GetLecturer()->GetID()]["Semesters"][$semester]["Subjects"][$sid] ?? 0;

						$hours /= count($object->GetProcValue("LecturerRooms"));

						return new PriorityAlgorithmCriterionData($hours, $hours / 100);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Предмет не является практикой) Преподаватели ведут практику"; }
					public function GetDescription(): string { return "Сильно уменьшает приоритет, если преподаватели заняты практикой у другой группы"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$algorithm->GetProcValue("Practic"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						foreach ($object->GetProcValue("LecturerRooms") as $lectroom)
						{
							$practic = $algorithm->GetProcValue("MainAlg")->GetProcValue("LecturerInfo")[$lectroom->GetLecturer()->GetID()]["Practic"] ?? null;

							if (isset($practic) && $practic != $algorithm->GetProcValue("MainObj")->GetObject()->GetID())
								return new PriorityAlgorithmCriterionData("Да", 0.0001);
						}

						return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Предмет не является практикой) Количество недоступных дней у преподавателей для текущей группы (недоступно дней / осталось доступных дней / осталось часов на предмет)"; }
					public function GetDescription(): string { return "Увеличивает приоритет, если у преподавателей остается меньше доступных дней из оставшихся для данной группы (обычно из-за практик других групп или отсутствий)"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return !$algorithm->GetProcValue("Practic"); }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$days = 0;
						$info = &$algorithm->GetProcValue("MainObj")->GetProcValue("Info");

						foreach ($object->GetProcValue("LecturerRooms") as $lectroom)
						{
							$lecturer = $lectroom->GetLecturer();
							$ldays = $info["LecturerNotAvailableDays"][$lecturer->GetID()] ?? null;

							if (isset($ldays))
							{
								$days += $ldays;
								continue;
							}

							$ldays = array();
							$practics = $algorithm->GetProcValue("MainAlg")->GetProcValue("LecturerInfo")[$lecturer->GetID()]["Practics"] ?? null;

							foreach ($info["ActivityLeftDays"] as $day)
								if (!Utilities::IsLecturerAvailable($lecturer, $day))
									$ldays[$day->format("Y-m-d")] = true;
								else if (isset($practics))
									foreach ($practics as $practic)
										if ($practic[2] != $algorithm->GetProcValue("MainObj")->GetObject()->GetID() && $day >= $practic[0] && $day <= $practic[1])
											$ldays[$day->format("Y-m-d")] = true;
								
							$days += count($ldays);
							$info["LecturerNotAvailableDays"][$lecturer->GetID()] = count($ldays);
						}

						$busydays = $days / count($object->GetProcValue("LecturerRooms"));
						$dleft = $info["ActivityDaysLeft"];
						$avdays = max(0, $dleft - $busydays);
						$hleft = $info["SubjectLeftHours"][$algorithm->GetProcValue("Subject")->GetID()->GetID()];

						return new PriorityAlgorithmCriterionData($busydays." / ".$dleft." / ".$hleft, $busydays <= 0 ? 1 : $hleft / max(0.00001, $avdays) * $busydays * 100);
					}
				},
			));

			foreach (self::$algorithm->GetCriterions() as $id => $criterion)
				if ($criterion->GetModifierRange() !== null && ($mod = $_COOKIE["editor-schedule-config-main-$id"] ?? null) !== null)
					$criterion->SetModifier($mod);

			foreach (self::$occalg->GetCriterions() as $id => $criterion)
				if ($criterion->GetModifierRange() !== null && ($mod = $_COOKIE["editor-schedule-config-occupation-$id"] ?? null) !== null)
					$criterion->SetModifier($mod);
			
			foreach (self::$houralg->GetCriterions() as $id => $criterion)
				if ($criterion->GetModifierRange() !== null && ($mod = $_COOKIE["editor-schedule-config-hournum-$id"] ?? null) !== null)
					$criterion->SetModifier($mod);
		}
	}
	Constructor::InitializeAlgorithm();
?>