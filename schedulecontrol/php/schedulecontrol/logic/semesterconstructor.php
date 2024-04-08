<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\{UserConfig, Core\DBTableRegistryCollections as Collections, Core\Logs};
	use DateTime;

	final class SemesterConstructor
	{
		static PriorityAlgorithm $algorithm;
		static PriorityAlgorithm $hcountalg;
		static PriorityAlgorithm $hnumalg;

		private static function GetPairsForSemesterSchedule(int $year, int $semester): array
		{
			$yload = YearLoad::GetFromDatabase($year);
			$pairs = array();

			if (isset($yload))
				foreach ($yload->GetLoads() as $gload)
					foreach ($gload->GetSubjects() as $subject)
						if ($subject->GetSemester() == $semester && $subject->GetSubject()->GetValue()->GetValue("Practic") == 0)
							$pairs[] = new SemesterScheduleUnallocPair($subject->GetID(), $subject->GetWHours());

			return $pairs;
		}

		private static function GetPairsFromSemesterSchedule(SemesterSchedule $schedule): array
		{
			$pairs = array();
			$hash = array();

			foreach ($schedule->GetDaySchedules() as $dschedule)
				foreach ($dschedule->GetLoadSchedules() as $lschedule)
					foreach ($lschedule->GetPairs() as $pair)
						foreach (self::SchedulePairToUnallocPairs($pair) as $upair)
							if (!isset($hash[$phash = $upair->GetSubject()->GetID()]))
							{
								$hash[$phash] = count($pairs);
								$pairs[] = new SemesterScheduleUnallocPair($upair->GetSubject(), $upair->GetHours());
							}
							else
								$pairs[$hash[$phash]]->SetHours($pairs[$hash[$phash]]->GetHours() + $upair->GetHours());

			return $pairs;
		}

		private static function SchedulePairToUnallocPairs(SemesterDayLoadSchedulePair $pair): array
		{
			$pairs = array();

			if ($pair instanceof SemesterDayLoadSchedulePairNormal)
				if ($pair->GetType() == SemesterDayLoadSchedulePairType::FullPair)
					$pairs[] = new SemesterScheduleUnallocPair($pair->GetSubject(), 2);
				elseif ($pair->GetType() == SemesterDayLoadSchedulePairType::FirstHour || $pair->GetType() == SemesterDayLoadSchedulePairType::SecondHour)
					$pairs[] = new SemesterScheduleUnallocPair($pair->GetSubject(), 1);
				elseif ($pair->GetType() == SemesterDayLoadSchedulePairType::HoursDivided || $pair->GetType() == SemesterDayLoadSchedulePairType::WeeksDivided)
				{
					$pairs[] = new SemesterScheduleUnallocPair($pair->GetSubject(), 1);
					$pairs[] = new SemesterScheduleUnallocPair($pair->GetSubject2(), 1);
				}

			return $pairs;
		}

		public static function FindUnallocPairs(SemesterSchedule $schedule): array
		{
			$allpairs = self::GetPairsForSemesterSchedule($schedule->GetYear(), $schedule->GetSemester());
			$upairs = array();

			if (count($allpairs) > 0 && count($spairs = self::GetPairsFromSemesterSchedule($schedule)) > 0)
				foreach ($allpairs as $upair)
				{
					$hours = $upair->GetHours();

					foreach($spairs as $spair)
						if ($upair->GetSubject()->GetID() == $spair->GetSubject()->GetID())
						{ $hours -= $spair->GetHours(); break; }

					if ($hours > 0)
						$upairs[] = new SemesterScheduleUnallocPair($upair->GetSubject(), $hours);
				}

			return $upairs;
		}

		public static function GetPairPriorityInfo(SemesterSchedule $schedule, int $day, DBLoad $load, int $pnum): array
		{
			$info = array("Нет информации по формированию");

			$pair = $schedule->GetDaySchedule($day)?->GetLoadSchedule($load)?->GetPair($pnum);
			$schedule->GetDaySchedule($day)?->GetLoadSchedule($load)?->RemovePair($pnum);

			self::PrepareAlgorithm($schedule->GetYear(), $schedule->GetSemester(), $schedule);

			self::$algorithm->SetProcValue("Day", $day);
			self::$algorithm->SetProcValue("PriorityLoad", $load);
			self::$algorithm->SetProcValue("PriorityHour", max(UserConfig::GetParameter("ScheduleStartReserveHour"), $pnum * 2 - 1));

			if (!isset($pair) || $pair instanceof SemesterDayLoadSchedulePairEmpty)
			{
				$fhour = null;
				$finfo = null;
				$shour = null;
				$sinfo = null;

				self::$algorithm->Process(function($pair) use (&$fhour, &$finfo, &$shour, &$sinfo) {
					if (self::$algorithm->GetProcValue("SearchHour2") !== null || self::$algorithm->GetProcValue("SearchWeekDiv") !== null)
					{
						$sinfo = ($shour = $pair)->GetPriorityInfo();
						return true;
					}
					else
					{
						if ($pair->GetProcValue("OneHour") && $pair->GetProcValue("Hour") % 2 == 1)
						{
							self::$algorithm->SetProcValue("SearchHour2", $pair);
							$finfo = ($fhour = $pair)->GetPriorityInfo();
						}
						else if ($pair->GetProcValue("WeekDivided"))
						{
							self::$algorithm->SetProcValue("SearchWeekDiv", $pair);
							$finfo = ($fhour = $pair)->GetPriorityInfo();
						}
						else
						{
							$finfo = ($fhour = $pair)->GetPriorityInfo();
							return true;
						}
					}
	
					if ($pair->GetObject()->GetHours() > 0) return false;
	
					return null;
				}, function() {
					if (($week1 = self::$algorithm->GetProcValue("SearchWeekDiv")) !== null && $week1->GetObject()->GetHours() == 1)
					{
						$week1->SetProcValue("WeekDivided", false);
						$week1->SetProcValue("OneHour", true);
						self::$algorithm->SetProcValue("SearchWeekDiv", null);
						self::$algorithm->SetProcValue("SearchHour2", $week1);

						return true;
					}
				});

				if (isset($fhour))
				{
					if (isset($shour))
						$info = array("Отдельное формирование (".$fhour->GetObject()->GetSubject()->GetName()."). Приоритет часа (предмет 1): ".$finfo["priority"]);
					else
						$info = array("Отдельное формирование (".$fhour->GetObject()->GetSubject()->GetName()."). Приоритет часа: ".$finfo["priority"]);

					foreach ($finfo["criterions"] as $criterion) $info[] = $criterion;

					if (isset($shour))
					{
						$info[] = "Отдельное формирование (".$shour->GetObject()->GetSubject()->GetName()."). Приоритет часа (предмет 2): ".$sinfo["priority"];
						foreach ($sinfo["criterions"] as $criterion) $info[] = $criterion;
					}
				}
			}
			else if ($pair->GetType() == SemesterDayLoadSchedulePairType::FullPair || $pair->GetType() == SemesterDayLoadSchedulePairType::FirstHour || $pair->GetType() == SemesterDayLoadSchedulePairType::SecondHour)
			{
				if ($pair->GetType() == SemesterDayLoadSchedulePairType::SecondHour)
					self::$algorithm->SetProcValue("PriorityHour", $pnum * 2);

				self::$algorithm->SetProcValue("PriorityHours", $pair->GetType() == SemesterDayLoadSchedulePairType::FullPair ? 2 : 1);
				
				foreach (self::$algorithm->GetObjects() as $object)
					if ($object->GetObject()->GetSubject()->GetID() == $pair->GetSubject()->GetID())
					{
						$object->UpdatePriority();
						$pinfo = $object->GetPriorityInfo();

						$info = array("Отдельное формирование (".$pair->GetSubject()->GetName()."). Приоритет часа: ".$pinfo["priority"]);
						foreach ($pinfo["criterions"] as $criterion) $info[] = $criterion;

						break;
					}
			}
			else if ($pair->GetType() == SemesterDayLoadSchedulePairType::HoursDivided || $pair->GetType() == SemesterDayLoadSchedulePairType::WeeksDivided)
			{
				self::$algorithm->SetProcValue("PriorityHours", $pair->GetType() == SemesterDayLoadSchedulePairType::HoursDivided ? 1 : 2);

				foreach (self::$algorithm->GetObjects() as $object)
					if ($object->GetObject()->GetSubject()->GetID() == $pair->GetSubject()->GetID())
					{
						$object->UpdatePriority();
						$pinfo = $object->GetPriorityInfo();

						$info = array("Отдельное формирование (".$pair->GetSubject()->GetName()."). Приоритет часа (предмет 1): ".$pinfo["priority"]);
						foreach ($pinfo["criterions"] as $criterion) $info[] = $criterion;

						if ($object->GetPriority() > 0)
						{
							if ($pair->GetType() == SemesterDayLoadSchedulePairType::HoursDivided)
								self::$algorithm->SetProcValue("SearchHour2", $object);
							else
								self::$algorithm->SetProcValue("SearchWeekDiv", $object);

							foreach (self::$algorithm->GetObjects() as $object2)
								if ($object2->GetObject()->GetSubject()->GetID() == $pair->GetSubject2()->GetID())
								{
									$object2->UpdatePriority();
									$pinfo = $object2->GetPriorityInfo();

									$info[] = "Отдельное формирование (".$pair->GetSubject2()->GetName()."). Приоритет часа (предмет 2): ".$pinfo["priority"];
									foreach ($pinfo["criterions"] as $criterion) $info[] = $criterion;
								}
						}

						break;
					}
			}

			return $info;
		}

		private static function PrepareNewSchedule(int $year, int $semester): ?SemesterSchedule
		{
			$graph = YearGraph::GetFromDatabase($year);
			if (!isset($graph)) return null;

			$weeks = Utilities::GetYearWeeks($year);
			$semesterstart = null;
			$semesterend = null;

			foreach ($graph->GetGroups() as $gid => $activities)
			{
				if ((new DBGroup($gid))->GetValue()?->GetValue("Extramural") != 0) continue;

				foreach ($activities->GetActivities() as $activity)
					if ($activity->GetSemester() == $semester)
					{
						[$start, $end] = Utilities::GetActivityStartEnd($activity, $weeks);
						if (!isset($start, $end)) continue;

						if (!isset($semesterstart) || $start < $semesterstart)
							$semesterstart = $start;

						if (!isset($semesterend) || $end > $semesterend)
							$semesterend = $end;
					}
			}

			if (!isset($semesterstart, $semesterend)) return null;

			return new SemesterSchedule($year, $semester, $semesterstart, $semesterend);
		}

		public static function PrepareAlgorithm(int $year, int $semester, ?SemesterSchedule $lock = null): bool
		{
			$yload = YearLoad::GetFromDatabase($year);
			if (!isset($yload)) return false;

			$loadhours = array();
			$lecthours = array();

			foreach ($yload->GetLoads() as $gload)
			{
				$lid = $gload->GetID()->GetID();
				$workdays = 0;

				foreach ($gload->GetGroups() as $group)
					if ($group->GetGroup()->GetValue()->GetValue("Extramural") == 0 && ($days = Utilities::IsGroupUseSaturdays($group->GetGroup(), $year) ? 6 : 5) > $workdays)
						$workdays = $days;

				if ($workdays == 0) continue;

				$loadhours[$lid]["WorkDays"] = $workdays;

				foreach ($gload->GetSubjects() as $subject)
				{
					if ($subject->GetSemester() != $semester || $subject->GetSubject()->GetValue()->GetValue("Practic") == 1) continue;

					$sid = $subject->GetID()->GetID();
					$whours = $subject->GetWHours();

					$loadhours[$lid]["Subjects"][$sid] = $whours;
					$loadhours[$lid]["Sum"] = ($loadhours[$lid]["Sum"] ?? 0) + $whours;
					$loadhours[$lid]["Diversity"] = ($loadhours[$lid]["Diversity"] ?? 0) + 1;

					for ($day = 0; $day < $workdays; $day++)
					{
						$loadhours[$lid]["Days"][$day]["Subjects"][$sid] = $whours / $workdays;
						$loadhours[$lid]["Days"][$day]["Sum"] = ($loadhours[$lid]["Days"][$day]["Sum"] ?? 0) + $whours / $workdays;
						$loadhours[$lid]["Days"][$day]["Diversity"] = ($loadhours[$lid]["Days"][$day]["Diversity"] ?? 0) + 1;
					}

					foreach ($subject->GetLecturers(true) as $lecturer)
					{
						$id = $lecturer->GetLecturer()->GetID();
						$lwhours = $lecturer->GetID()->GetWHours();

						$lecthours[$id]["Subjects"][$sid] = ($lecthours[$id]["Subjects"][$sid] ?? 0) + $lwhours;
						$lecthours[$id]["Sum"] = ($lecthours[$id]["Sum"] ?? 0) + $lwhours;

						for ($day = 0; $day < $workdays; $day++)
						{
							$lecthours[$id]["Days"][$day]["Subjects"][$sid] = ($lecthours[$id]["Days"][$day]["Subjects"][$sid] ?? 0) + $lwhours / $workdays;
							$lecthours[$id]["Days"][$day]["Sum"] = ($lecthours[$id]["Days"][$day]["Sum"] ?? 0) + $lwhours / $workdays;
						}
					}
				}
			}

			foreach ($loadhours as $lid => $load)
				for ($day = 0; $day < $load["WorkDays"]; $day++)
					$loadhours[$lid]["WDays"][$day] = ($loadhours[$lid]["WDays"][$day - 1] ?? 0) + ($loadhours[$lid]["Days"][$day]["Sum"] ?? 0);

			$schedule = self::PrepareNewSchedule($year, $semester);

			self::$algorithm->PrepareProcess();

			self::$algorithm->SetProcValue("LoadHoursData", $loadhours);
			self::$algorithm->SetProcValue("LoadHoursLeftData", $loadhours);
			self::$algorithm->SetProcValue("LecturerHoursData", $lecthours);
			self::$algorithm->SetProcValue("LecturerHoursLeftData", $lecthours);
			
			self::$algorithm->SetProcValue("LoadBusyness", array());
			self::$algorithm->SetProcValue("LecturerBusyness", array());
			self::$algorithm->SetProcValue("LecturerWeekBusyness", array());

			self::$algorithm->SetProcValue("AddOrder", 1);
			self::$algorithm->SetProcValue("Day", 0);
			self::$algorithm->SetProcValue("Schedule", $schedule);

			$pairs = isset($lock) ? self::FindUnallocPairs($lock) : self::GetPairsForSemesterSchedule($year, $semester);
			foreach ($pairs as $pair) self::$algorithm->AddObjectToProcess($pair);

			if (isset($lock))
				foreach ($lock->GetDaySchedules() as $day => $dschedule)
					foreach ($dschedule->GetLoadSchedules() as $lid => $lschedule)
					{
						if (!($load = new DBLoad($lid))->IsValid()) continue;

						foreach ($lschedule->GetPairs() as $num => $pair)
							self::SetSchedulePair($day, $load, $pair, $num);
					}

			return true;
		}

		public static function ProcessAlgorithm(): void
		{
			set_time_limit(900);

			self::$algorithm->Process(function($pair) {
				if (self::$algorithm->GetProcValue("SearchHour2") !== null)
				{
					self::AddAlgorithmPair(self::$algorithm->GetProcValue("SearchHour2"), $pair);
					self::$algorithm->SetProcValue("SearchHour2", null);
				}
				else if (self::$algorithm->GetProcValue("SearchWeekDiv") !== null)
				{
					self::AddAlgorithmPair(self::$algorithm->GetProcValue("SearchWeekDiv"), $pair);
					self::$algorithm->SetProcValue("SearchWeekDiv", null);
				}
				else
				{
					if ($pair->GetProcValue("OneHour") && $pair->GetProcValue("Hour") % 2 == 1)
					{
						self::$algorithm->SetProcValue("SearchHour2", $pair);
						self::$algorithm->SetProcValue("SearchHour2Info", $pair->GetPriorityInfo());
					}
					else if ($pair->GetProcValue("WeekDivided"))
					{
						self::$algorithm->SetProcValue("SearchWeekDiv", $pair);
						self::$algorithm->SetProcValue("SearchWeekDivInfo", $pair->GetPriorityInfo());
					}
					else
						self::AddAlgorithmPair($pair);
				}

				if ($pair->GetObject()->GetHours() > 0) return false;

				return null;
			}, function() {
				if (($hour1 = self::$algorithm->GetProcValue("SearchHour2")) !== null)
				{
					self::AddAlgorithmPair($hour1);
					self::$algorithm->SetProcValue("SearchHour2", null);

					return true;
				}
				else if (($week1 = self::$algorithm->GetProcValue("SearchWeekDiv")) !== null)
				{
					$week1->SetProcValue("WeekDivided", false);

					if ($week1->GetObject()->GetHours() == 1)
					{
						$week1->SetProcValue("OneHour", true);
						self::$algorithm->SetProcValue("SearchWeekDiv", null);
						self::$algorithm->SetProcValue("SearchHour2", $week1);
						self::$algorithm->SetProcValue("SearchHour2Info", self::$algorithm->GetProcValue("SearchWeekDivInfo"));

						return true;
					}

					self::AddAlgorithmPair($week1);
					self::$algorithm->SetProcValue("SearchWeekDiv", null);

					return true;
				}

				$day = self::$algorithm->GetProcValue("Day");

				if ($day < 6)
				{
					self::$algorithm->SetProcValue("Day", $day + 1);
					return true;
				}
				
				return false;
			});
		}

		private static function AddAlgorithmPair(PriorityAlgorithmObject $pair, ?PriorityAlgorithmObject $hour2 = null): void
		{
			Logs::Write("Расписание на семестр - формирование: день ".(self::$algorithm->GetProcValue("Day") + 1).", час ".$pair->GetProcValue("Hour").", предмет ".$pair->GetObject()->GetSubject()->GetName().", нагрузка ".$pair->GetObject()->GetSubject()->GetLoad()->GetName());

			$pair->GetObject()->SetHours($pair->GetObject()->GetHours() - 1);
			$hour2?->GetObject()->SetHours($hour2->GetObject()->GetHours() - 1);

			$fpair = null;

			if (!isset($hour2))
			{
				$fpair = new SemesterDayLoadSchedulePairNormal($pair->GetObject()->GetSubject(), null, match(true) {
					$pair->GetProcValue("OneHour") && $pair->GetProcValue("Hour") % 2 == 1 => SemesterDayLoadSchedulePairType::FirstHour,
					$pair->GetProcValue("OneHour") && $pair->GetProcValue("Hour") % 2 == 0 => SemesterDayLoadSchedulePairType::SecondHour,
					default => SemesterDayLoadSchedulePairType::FullPair,
				});

				if ($fpair->GetType() == SemesterDayLoadSchedulePairType::FullPair)
					$pair->GetObject()->SetHours($pair->GetObject()->GetHours() - 1);
			}
			else
				$fpair = new SemesterDayLoadSchedulePairNormal($pair->GetObject()->GetSubject(), $hour2->GetObject()->GetSubject(), match(true) {
					$pair->GetProcValue("WeekDivided") => SemesterDayLoadSchedulePairType::WeeksDivided,
					default => SemesterDayLoadSchedulePairType::HoursDivided,
				});

			$info = array();
			$pinfo = match(true) {
				self::$algorithm->GetProcValue("SearchHour2") !== null => self::$algorithm->GetProcValue("SearchHour2Info"),
				self::$algorithm->GetProcValue("SearchWeekDiv") !== null => self::$algorithm->GetProcValue("SearchWeekDivInfo"),
				default => $pair->GetPriorityInfo(),
			};

			if (isset($hour2))
				$info[] = "Добавление предмета №".self::$algorithm->GetProcValue("AddOrder").". Приоритет (предмет 1): ".$pinfo["priority"];
			else
				$info[] = "Добавление предмета №".self::$algorithm->GetProcValue("AddOrder").". Приоритет: ".$pinfo["priority"];

			foreach ($pinfo["criterions"] as $criterion) $info[] = "  ".$criterion;

			self::$algorithm->SetProcValue("AddOrder", self::$algorithm->GetProcValue("AddOrder") + 1);
			
			if (isset($hour2))
			{
				$hinfo = $hour2->GetPriorityInfo();
				$info[] = "Добавление предмета №".self::$algorithm->GetProcValue("AddOrder").". Приоритет (предмет 2): ".$hinfo["priority"];

				foreach ($hinfo["criterions"] as $criterion) $info[] = $criterion;

				self::$algorithm->SetProcValue("AddOrder", self::$algorithm->GetProcValue("AddOrder") + 1);
			}

			$fpair->SetPriorityInfo($info);
			self::SetSchedulePair(self::$algorithm->GetProcValue("Day"), $pair->GetObject()->GetSubject()->GetLoad(), $fpair, ceil($pair->GetProcValue("Hour") / 2));
		}

		private static function SetSchedulePair(int $day, DBLoad $load, SemesterDayLoadSchedulePair $pair, int $pnum): void
		{
			$hour1 = max(UserConfig::GetParameter("ScheduleStartReserveHour"), $pnum * 2 - 1);
			$hour2 = max(UserConfig::GetParameter("ScheduleStartReserveHour"), $pnum * 2);

			if ($pair instanceof SemesterDayLoadSchedulePairNormal)
				if ($pair->GetType() == SemesterDayLoadSchedulePairType::FullPair || $pair->GetType() == SemesterDayLoadSchedulePairType::FirstHour || $pair->GetType() == SemesterDayLoadSchedulePairType::SecondHour)
					for ($h = $pair->GetType() == SemesterDayLoadSchedulePairType::SecondHour ? $hour2 : $hour1; $h <= ($pair->GetType() == SemesterDayLoadSchedulePairType::FirstHour ? $hour1 : $hour2); $h++)
						self::SetScheduleHour($day, $load, $pair->GetSubject(), $h);
				else if ($pair->GetType() == SemesterDayLoadSchedulePairType::HoursDivided)
					for ($h = $hour1; $h <= $hour2; $h++)
						self::SetScheduleHour($day, $load, $h == $hour1 ? $pair->GetSubject() : $pair->GetSubject2(), $h);
				else if ($pair->GetType() == SemesterDayLoadSchedulePairType::WeeksDivided)
				{
					self::SetScheduleHour($day, $load, $pair->GetSubject(), $hour1, false, $hour2);
					self::SetScheduleHour($day, $load, $pair->GetSubject2(), $hour2, true, $hour1);
				}

			self::$algorithm->GetProcValue("Schedule")->GetDaySchedule($day, true)->GetLoadSchedule($load, true)->SetPair($pnum, $pair);
		}

		private static function SetScheduleHour(int $day, DBLoad $load, DBLoadSubject $subject, int $hour, ?bool $weekdiv = null, int $weekhour2 = 0): void
		{
				$lid = $load->GetID();
				$sid = $subject->GetID();

				self::$algorithm->GetProcValue("LoadBusyness")[$lid][$day][$hour] = true;

				$leftdata = &self::$algorithm->GetProcValue("LoadHoursLeftData");

				$leftdata[$lid]["Subjects"][$sid]--;
				if ($leftdata[$lid]["Subjects"][$sid] >= 0) $leftdata[$lid]["Sum"]--;

				$leftdata[$lid]["Days"][$day]["Subjects"][$sid]--;
				if ($leftdata[$lid]["Days"][$day]["Subjects"][$sid] >= 0) $leftdata[$lid]["Days"][$day]["Sum"]--;

				if ($leftdata[$lid]["Subjects"][$sid] == 0)
					$leftdata[$lid]["Diversity"]--;

				if ($leftdata[$lid]["Days"][$day]["Subjects"][$sid] == 0)
					$leftdata[$lid]["Days"][$day]["Diversity"]--;
				
				for ($d = $day; isset($leftdata[$lid]["WDays"][$d]); $d++)
					$leftdata[$lid]["WDays"][$d]--;

				foreach (self::GetSubjectLecturersIDs($subject) as $lectid)
				{
					$leftldata = &self::$algorithm->GetProcValue("LecturerHoursLeftData");

					$leftldata[$lectid]["Subjects"][$sid]--;
					if ($leftldata[$lectid]["Subjects"][$sid] >= 0) $leftldata[$lectid]["Sum"]--;

					$leftldata[$lectid]["Days"][$day]["Subjects"][$sid]--;
					if ($leftldata[$lectid]["Days"][$day]["Subjects"][$sid] >= 0) $leftldata[$lectid]["Days"][$day]["Sum"]--;
				}

				if ($weekdiv !== null)
					foreach (self::GetSubjectLecturersIDs($subject, true) as $lectid)
					{
						self::$algorithm->GetProcValue("LecturerWeekBusyness")[$weekdiv ? 1 : 0][$day][$hour][$lectid] = true;
						self::$algorithm->GetProcValue("LecturerWeekBusyness")[$weekdiv ? 1 : 0][$day][$weekhour2][$lectid] = true;
					}
				else
					foreach (self::GetSubjectLecturersIDs($subject, true) as $lectid)
						self::$algorithm->GetProcValue("LecturerBusyness")[$day][$hour][$lectid] = true;
		}

		public static function GetSubjectLecturersIDs(DBLoadSubject $subject, bool $unique = false): array
		{
			$lids = array();
			$cache = array();

			if (isset($subject))
				foreach ($subject->GetLecturers(true) as $lecturer)
					if (!$unique || !isset($cache[$lecturer->GetLecturer()->GetID()]))
						$cache[$lids[] = $lecturer->GetLecturer()->GetID()] = true;

			return $lids;
		}

		public static function IsHoursAvailable(SemesterScheduleUnallocPair $pair, int $hour, bool $one, ?bool $weekdiv = null): bool
		{
			$lid = $pair->GetSubject()->GetLoad()?->GetID();
			if (!isset($lid)) return false;

			$lectids = self::GetSubjectLecturersIDs($pair->GetSubject(), true);
			$day = self::$algorithm->GetProcValue("Day");

			for ($h = $hour; $h < $hour + ($one ? 1 : 2); $h++)
			{
				if (isset(self::$algorithm->GetProcValue("LoadBusyness")[$lid][$day][$h])) return false;

				if (count($lectids) > 0)
					foreach ($lectids as $lectid)
						if (
							isset(self::$algorithm->GetProcValue("LecturerBusyness")[$day][$h][$lectid]) ||
							(($weekdiv === null || $weekdiv === false) && isset(self::$algorithm->GetProcValue("LecturerWeekBusyness")[0][$day][$h][$lectid])) ||
							(($weekdiv === null || $weekdiv === true) && isset(self::$algorithm->GetProcValue("LecturerWeekBusyness")[1][$day][$h][$lectid]))
						) return false;
			}

			return true;
		}

		static function ConstructSchedule(int $year, int $semester, ?SemesterSchedule $lock = null): ?SemesterSchedule
		{
			if (!self::PrepareAlgorithm($year, $semester, $lock))
				return null;

			self::ProcessAlgorithm();

			return self::$algorithm->GetProcValue("Schedule");
		}

		static function GetSemesterSchedulesForDate(DateTime $date): array
		{
			$schedules = array();
			$year = Utilities::GetStartYear($date);

			foreach (Collections::GetCollection("SemesterSchedules")->GetAllLastChanges(array("Year" => $year)) as $schedule)
			{
				$start = new DateTime($schedule->GetData()->GetValue("StartDate"));
				$end = new DateTime($schedule->GetData()->GetValue("EndDate"));

				if ($date >= $start && $date <= $end)
					$schedules[] = SemesterSchedule::GetFromDatabase(array($year, $schedule->GetData()->GetValue("Semester")));
			}

			usort($schedules, function($s1, $s2) { return $s1->GetStartDate() <=> $s2->GetStartDate(); });

			return $schedules;
		}

		public static function InitializeAlgorithm()
		{
			self::$algorithm = new PriorityAlgorithm(array(
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Наличие корректной нагрузки"; }
					public function GetDescription(): string { return "Проверяет, принадлежит ли предмет корректной нагрузке"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$load = $object->GetObject()->GetSubject()->GetLoad();
						if (!isset($load)) return null;

						if ($algorithm->GetProcValue("PriorityLoad") !== null && $algorithm->GetProcValue("PriorityLoad")->GetID() != $load->GetID())
							return null;
		
						$object->SetProcValue("Load", $load);
						return true;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Количество основных преподавателей для предмета"; }
					public function GetDescription(): string { return "Увеличивает приоритет прямопропорционально количеству преподавателей под предмет"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$lects = SemesterConstructor::GetSubjectLecturersIDs($object->GetObject()->GetSubject(), true);
						if (count($lects) == 0) return null;
		
						$object->SetProcValue("Lecturers", $lects);

						return new PriorityAlgorithmCriterionData(count($lects), count($lects));
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск второго часа) Предметы не совпадают"; }
					public function GetDescription(): string { return "Проверяет, совпадает ли текущий предмет с предметом первого часа"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $object->GetAlgorithm()->GetProcValue("SearchHour2") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						if ($algorithm->GetProcValue("SearchHour2")->GetObject()->GetSubject()->GetID() == $object->GetObject()->GetSubject()->GetID())
							return false;
						
						return true;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск предмета для чередования по неделям) Предметы не совпадают"; }
					public function GetDescription(): string { return "Проверяет, совпадает ли текущий предмет с предметом первой недели"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $object->GetAlgorithm()->GetProcValue("SearchWeekDiv") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						if ($algorithm->GetProcValue("SearchWeekDiv")->GetObject()->GetSubject()->GetID() == $object->GetObject()->GetSubject()->GetID())
							return false;
						
						return true;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск второго часа) Нагрузки совпадают"; }
					public function GetDescription(): string { return "Проверяет, совпадает ли нагрузка текущего предмета с нагрузкой предмета первого часа"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $object->GetAlgorithm()->GetProcValue("SearchHour2") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						if ($algorithm->GetProcValue("SearchHour2")->GetObject()->GetSubject()->GetLoad()?->GetID() == $object->GetProcValue("Load")->GetID())
							return true;
						
						return false;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск предмета для чередования по неделям) Нагрузки совпадают"; }
					public function GetDescription(): string { return "Проверяет, совпадает ли нагрузка текущего предмета с нагрузкой предмета первой недели"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $object->GetAlgorithm()->GetProcValue("SearchWeekDiv") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						if ($algorithm->GetProcValue("SearchWeekDiv")->GetObject()->GetSubject()->GetLoad()?->GetID() == $object->GetProcValue("Load")->GetID())
							return true;
						
						return false;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Общий остаток часов у нагрузки"; }
					public function GetDescription(): string { return "Постепенно уменьшает приоритет в процессе уменьшения кол-ва часов по всем предметам у нагрузки текущего предмета"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$left = $algorithm->GetProcValue("LoadHoursLeftData")[$object->GetProcValue("Load")->GetID()] ?? null;
						if (!isset($left)) return false;
		
						$init = $algorithm->GetProcValue("LoadHoursData")[$object->GetProcValue("Load")->GetID()];
		
						$object->SetProcValue("Left", $left);
						$object->SetProcValue("Init", $init);

						if ($left["Sum"] <= 0) return false;
		
						return new PriorityAlgorithmCriterionData($left["Sum"], 1/* $left["Sum"] / 10 */);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Остаток часов у нагрузки на текущий день недели"; }
					public function GetDescription(): string { return "Уменьшает приоритет в процессе вычитки предметов у нагрузки предмета (в рамках дня недели)"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$day = $algorithm->GetProcValue("Day");
						$left = $object->GetProcValue("Left")["WDays"][$day] ?? 0;
						$mpleft = $left;

						if ($mpleft <= 0)
						{
							// Важно! В последний день все оставшиеся пары должны быть выставлены вне зависимости от расчётного кол-ва часов в день
							if ($day < $object->GetProcValue("Left")["WorkDays"] - 1) return false;

							$mpleft = 1;
						}
		
						return new PriorityAlgorithmCriterionData($left, $mpleft / 5);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Остаток часов на предмет у нагрузки"; }
					public function GetDescription(): string { return "Уменьшает приоритет при вычитке текущего предмета у нагрузки текущего предмета"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$left = $object->GetProcValue("Left")["Subjects"][$object->GetObject()->GetSubject()->GetID()] ?? 0;
						if ($left <= 0) return false;
		
						return new PriorityAlgorithmCriterionData($left, 1/* $left / 10 */);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Остаток часов на предмет у нагрузки на текущий день недели"; }
					public function GetDescription(): string { return "Уменьшает приоритет при вычитке текущего предмета у нагрузки текущего предмета (в рамках дня недели)"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$day = $algorithm->GetProcValue("Day");
						$sid = $object->GetObject()->GetSubject()->GetID();

						$left = $object->GetProcValue("Left")["Days"][$day]["Subjects"][$sid] ?? 0;
						$mpleft = $left;

						if ($mpleft <= 0)
						{
							// Важно! В последний день все оставшиеся пары должны быть выставлены вне зависимости от расчётного кол-ва часов в день
							if ($day < $object->GetProcValue("Left")["WorkDays"] - 1) return false;

							$mpleft = 1;
						}
		
						return new PriorityAlgorithmCriterionData($left, $mpleft / 5);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Общий остаток часов у преподавателей"; }
					public function GetDescription(): string { return "Увеличивает приоритет прямопропорционально суммированному остатку часов всех преподавателей текущего предмета"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$left = 0;

						foreach ($object->GetProcValue("Lecturers") as $lectid)
							$left += $algorithm->GetProcValue("LecturerHoursLeftData")[$lectid]["Sum"];
						
						if ($left == 0) return false;
		
						return new PriorityAlgorithmCriterionData($left, $left / 10);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Остаток часов у преподавателей на текущий день недели"; }
					public function GetDescription(): string { return "Уменьшает приоритет при вычитке предметов у преподавателей текущего предмета"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$day = $algorithm->GetProcValue("Day");
						$left = 0;

						foreach ($object->GetProcValue("Lecturers") as $lectid)
							$left += $algorithm->GetProcValue("LecturerHoursLeftData")[$lectid]["Days"][$day]["Sum"];

						$mpleft = $left;
						if ($mpleft < 1) $mpleft = 1;
		
						return new PriorityAlgorithmCriterionData($left, $mpleft / 5);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Остаток часов на предмет у преподавателей"; }
					public function GetDescription(): string { return "Уменьшает приоритет при вычитке предметов у преподавателей текущего предмета"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$sid = $object->GetObject()->GetSubject()->GetID();
						$left = 0;

						foreach ($object->GetProcValue("Lecturers") as $lectid)
							$left += $algorithm->GetProcValue("LecturerHoursLeftData")[$lectid]["Subjects"][$sid];

						if ($left == 0) return false;
		
						return new PriorityAlgorithmCriterionData($left, $left / 10);
					}
				},
				/* new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Процент остатка часов на предмет у преподавателей на текущий день недели (осталось / всего)"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$day = $algorithm->GetProcValue("Day");
						$sid = $object->GetObject()->GetSubject()->GetID();
						$left = 0;
						$init = 0;

						foreach ($object->GetProcValue("Lecturers") as $lectid)
						{
							$left += $algorithm->GetProcValue("LecturerHoursLeftData")[$lectid]["Days"][$day]["Subjects"][$sid];
							$init += $algorithm->GetProcValue("LecturerHoursData")[$lectid]["Days"][$day]["Subjects"][$sid];
						}

						$mpleft = $left;
						if ($mpleft <= 0) $mpleft = 1 / (2 - $mpleft);
		
						return new PriorityAlgorithmCriterionData($left." / ".$init, $mpleft / $init);
					}
				}, */
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Соотношение кол-ва оставшихся часов на разнообразие предметов (общее, кол-во предметов / кол-во часов)"; }
					public function GetDescription(): string { return "Увеличивает приоритет, если количество оставшихся часов на всех предметы меньше количества оставшихся предметов"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$diversity = $object->GetProcValue("Left")["Diversity"] ?? 0;
						if ($diversity <= 0) return false;
		
						$sum = $object->GetProcValue("Left")["Sum"] ?? 0;
		
						return new PriorityAlgorithmCriterionData($diversity." / ".$sum, $diversity / $sum);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Соотношение кол-ва оставшихся часов на разнообразие предметов (текущий день недели, кол-во предметов / кол-во часов)"; }
					public function GetDescription(): string { return "Увеличивает приоритет, если количество оставшихся часов на всех предметы меньше количества оставшихся предметов (в рамках дня недели)"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$diversity = $object->GetProcValue("Left")["Days"][$algorithm->GetProcValue("Day")]["Diversity"] ?? 0;
						if ($diversity <= 0) return false;
		
						$sum = $object->GetProcValue("Left")["Days"][$algorithm->GetProcValue("Day")]["Sum"] ?? 0;
		
						return new PriorityAlgorithmCriterionData($diversity." / ".$sum, $diversity / $sum);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск второго часа) Нечетное кол-во оставшихся часов на предмет"; }
					public function GetDescription(): string { return "Проверяет факт того, что текущий предмет имеет нечётное оставшееся количество часов"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $object->GetAlgorithm()->GetProcValue("SearchHour2") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hours = $object->GetObject()->GetHours();
						if ($hours % 2 == 0)
							return false; //new PriorityAlgorithmCriterionData("Нет ($hours)", 0.01);
						else
							return true; //new PriorityAlgorithmCriterionData("Да ($hours)", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Занятость закрепленных преподавателей в текущий день (часов / преподавателей)"; }
					public function GetDescription(): string { return "Уменьшает приоритет при увеличении количества занятых часов преподавателями текущего предмета в текущий день недели"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$lectids = $object->GetProcValue("Lecturers");
						$lecthours = 0;
						$day = $algorithm->GetProcValue("Day");

						foreach ($lectids as $lectid)
						{
							$lectbusyness = $algorithm->GetProcValue("LecturerBusyness")[$day] ?? null;

							if (isset($lectbusyness))
								foreach ($lectbusyness as $hours)
									if (isset($hours[$lectid])) $lecthours++;

							foreach ($algorithm->GetProcValue("LecturerWeekBusyness") as $weekbusyness)
							{
								$lectwbusyness = $weekbusyness[$day] ?? null;

								if (isset($lectwbusyness))
									foreach ($lectwbusyness as $hours)
										if (isset($hours[$lectid])) $lecthours += 0.5;
							}
						}

						return new PriorityAlgorithmCriterionData($lecthours." / ".count($lectids), 1 / (1 + $lecthours / count($lectids)));
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Выявление приоритетного количества часов"; }
					public function GetDescription(): string { return "Определяет, какое количество часов выставить этому предмету (пару или час) и модифицирует общий приоритет в соответствии с приоритетом этого количества часов"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$houralg = SemesterConstructor::$hcountalg;
						$houralg->PrepareProcess();
						$houralg->SetProcValue("MainAlg", $algorithm);
						$houralg->SetProcValue("MainObj", $object);

						if ($algorithm->GetProcValue("PriorityHours") !== null)
							$houralg->AddObjectToProcess($algorithm->GetProcValue("PriorityHours"));
						else
							for ($shours = 2; $shours >= 1; $shours--)
								$houralg->AddObjectToProcess($shours);
		
						$priorhours = null;
						$houralg->Process(function($hours) use(&$priorhours) {
							$priorhours = $hours;
		
							return true;
						});
		
						if (!isset($priorhours)) return false;

						$object->SetProcValue("OneHour", $priorhours->GetObject() == 1);
						$object->SetProcValue("Hour", $priorhours->GetProcValue("Hour"));
						$object->SetProcValue("WeekDivided", $priorhours->GetProcValue("WeekDivided") ?? false);

						return new PriorityAlgorithmCriterionData($priorhours->GetObject(), $priorhours->GetPriority() / 1000, $priorhours);
					}
				},
			));

			self::$hcountalg = new PriorityAlgorithm(array(
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Кол-во часов не превышает оставшееся кол-во часов на предмет"; }
					public function GetDescription(): string { return "Проверяет, что текущее количество часов не выше оставшегося количества часов на предмет нагрузки"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						return $object->GetObject() <= $algorithm->GetProcValue("MainObj")->GetObject()->GetHours();
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск второго часа) Один час"; }
					public function GetDescription(): string { return "Проверяет, что выбрано количество часов равное 1 под текущие условия"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						return $object->GetObject() == 1;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск предмета для чередования по неделям или четное кол-во оставшихся часов и нет приоритета 1 часа) Полная пара"; }
					public function GetDescription(): string { return "Проверяет, что выбрано количество часов равное 2 под текущие условия"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool {
						return $algorithm->GetProcValue("MainObj")->GetObject()->GetHours() % 2 == 0 && 
						$algorithm->GetProcValue("MainObj")->GetObject()->GetSubject()->GetValue()->GetValue("OneHourPrior") == 0 ||
						$algorithm->GetProcValue("MainAlg")->GetProcValue("SearchWeekDiv") !== null;
					}
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						return $object->GetObject() == 2;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск второго часа) Следующий час свободен"; }
					public function GetDescription(): string { return "Проверяет возможность выставления предмета вслед за первым часом"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$nhour = $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2")->GetProcValue("Hour") + 1;
						$object->SetProcValue("Hour", $nhour);
						
						return SemesterConstructor::IsHoursAvailable($algorithm->GetProcValue("MainObj")->GetObject(), $nhour, true);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Поиск предмета для чередования по неделям) Возможно провести пару в тоже самое время"; }
					public function GetDescription(): string { return "Проверяет возможность выставления предмета в те же часы, что и первый предмет, но во вторую неделю"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchWeekDiv") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hour = $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchWeekDiv")->GetProcValue("Hour");
						$object->SetProcValue("Hour", $hour);
						
						return SemesterConstructor::IsHoursAvailable($algorithm->GetProcValue("MainObj")->GetObject(), $hour, false, true);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Поиск предмета для чередования по неделям) Кандидат для создания чередования по неделям"; }
					public function GetDescription(): string { return "Увеличивает приоритет, если предмету может быть установлено вместе с первым предметом чередование по неделям"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchWeekDiv") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$pair = $algorithm->GetProcValue("MainObj")->GetObject();
						if ($pair->GetHours() % 2 == 1 && $pair->GetSubject()->GetValue()->GetValue("OneHourPrior") == 0)
						{
							$object->SetProcValue("WeekDivided", true);
							return new PriorityAlgorithmCriterionData("Да", 10);
						}
						else
							return new PriorityAlgorithmCriterionData("Нет", 0);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Поиск второго часа) Предмету требуется выставление 1 часа по возможности"; }
					public function GetDescription(): string { return "Увеличивает приоритет, если предмету установлено требование выставляться по 1 часу"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2") !== null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						if ($algorithm->GetProcValue("MainObj")->GetObject()->GetSubject()->GetValue()->GetValue("OneHourPrior") == 1)
							return new PriorityAlgorithmCriterionData("Да", 10);
						else
							return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Поиск второго часа, нет приоритета 1 часа) Нечётное кол-во оставшихся часов"; }
					public function GetDescription(): string { return "Не позволяет выставиться как 1 час предметам, у которых остались полные пары. Увеличивает приоритет предметов, у которых остался только 1 час"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool {
						return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2") !== null &&
						$algorithm->GetProcValue("MainObj")->GetObject()->GetSubject()->GetValue()->GetValue("OneHourPrior") == 0;
					}
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hours = $algorithm->GetProcValue("MainObj")->GetObject()->GetHours();

						if ($hours == 1)
							return new PriorityAlgorithmCriterionData("Остался 1 час", 2);
						
						return $hours % 2 == 1;
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Не поиск второго часа и нет приоритета 1 часа) Полная пара"; }
					public function GetDescription(): string { return "Уменьшает приоритет одиночными часам"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool {
						return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2") === null &&
						$algorithm->GetProcValue("MainObj")->GetObject()->GetSubject()->GetValue()->GetValue("OneHourPrior") == 0;
					}
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						if ($object->GetObject() == 1)
							return new PriorityAlgorithmCriterionData("Нет", 0.5);
						else
							return new PriorityAlgorithmCriterionData("Да", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Не поиск второго часа) Предмету требуется выставление 1 часа по возможности"; }
					public function GetDescription(): string { return "Модифицирует приоритет, если предмету установлено требование выставления 1 часа. Увеличивает, если найден еще один предмет с таким требованием, уменьшает в иных случаях."; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2") === null; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$pair = $algorithm->GetProcValue("MainObj")->GetObject();

						if ($pair->GetSubject()->GetValue()->GetValue("OneHourPrior") == 1)
						{
							foreach ($algorithm->GetProcValue("MainObj")->GetProcValue("Left")["Subjects"] as $sid => $hours)
								if (
									$hours > 0 && $sid != $pair->GetSubject()->GetID() &&
									($hours % 2 == 1 || (new DBLoadSubject($sid))->GetValue()->GetValue("OneHourPrior") == 1)
								)
									return new PriorityAlgorithmCriterionData("Да [есть подходящий 2 час]", $object->GetObject() == 1 ? 5 : 0.1);

							return new PriorityAlgorithmCriterionData("Да [нет подходящего 2 часа]", $object->GetObject() == 1 && $pair->GetHours() > 1 ? 0 : 1);
						}
						else
							return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Один час, не поиск второго часа, нет приоритета 1 часа, нечётное кол-во часов) Есть доступный предмет с приоритетом 1 часа"; }
					public function GetDescription(): string { return "Увеличивает приоритет, если текущему предмету найден потенциальный подходящий второй час, при этом текущий предмет имеет нечётное количество часов"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool {
						return $object->GetObject() == 1 && $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2") === null &&
						$algorithm->GetProcValue("MainObj")->GetObject()->GetHours() % 2 == 1 && $algorithm->GetProcValue("MainObj")->GetObject()->GetSubject()->GetValue()->GetValue("OneHourPrior") == 0;
					}
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						foreach ($algorithm->GetProcValue("MainObj")->GetProcValue("Left")["Subjects"] as $sid => $hours)
							if (
								$hours > 0 && $sid != $algorithm->GetProcValue("MainObj")->GetObject()->GetSubject()->GetID() &&
								(new DBLoadSubject($sid))->GetValue()->GetValue("OneHourPrior") == 1
							)
								return new PriorityAlgorithmCriterionData("Да", 2);
						
						return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "(Не поиск второго часа или предмета для чередования по неделям) Определение первого часа проведения занятия"; }
					public function GetDescription(): string { return "Определяет приоритетный номер первого часа для этого предмета и модифицирует общий приоритет в соответствии с приоритетом этого часа"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool {
						return $algorithm->GetProcValue("MainAlg")->GetProcValue("SearchHour2") === null &&
						$algorithm->GetProcValue("MainAlg")->GetProcValue("SearchWeekDiv") === null;
					}
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hnumalg = SemesterConstructor::$hnumalg;
						$hnumalg->PrepareProcess();

						$hnumalg->SetProcValue("MainAlg", $algorithm->GetProcValue("MainAlg"));
						$hnumalg->SetProcValue("MainObj", $algorithm->GetProcValue("MainObj"));
						$hnumalg->SetProcValue("Hours", $object->GetObject());

						if (($prhour = $algorithm->GetProcValue("MainAlg")->GetProcValue("PriorityHour")) !== null)
							$hnumalg->AddObjectToProcess($prhour);
						else
							for ($h = UserConfig::GetParameter("ScheduleStartDefaultHour"); $h <= UserConfig::GetParameter("ScheduleEndDefaultHour") * 2; $h += $object->GetObject())
								$hnumalg->AddObjectToProcess($h);
						
						$priorhour = null;
						$hnumalg->Process(function($hour) use(&$priorhour) {
							$priorhour = $hour;

							return true;
						});

						if (!isset($priorhour)) return false;

						$object->SetProcValue("Hour", $priorhour->GetObject());
						$object->SetProcValue("WeekDivided", $priorhour->GetProcValue("WeekDivided") ?? false);

						return new PriorityAlgorithmCriterionData($priorhour->GetObject(), $priorhour->GetPriority() / 1000, $priorhour);
					}
				},
			));

			self::$hnumalg = new PriorityAlgorithm(array(
				/* new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Пустые часы между последним и текущим часами"; }
					public function GetDescription(): string { return "Уменьшает приоритет текущего часа, если его выставление создаст пустые часы между предметами"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$lhour = null;
						$lid = $object->GetObject()[1]->GetProcValue("Load")->GetID();
						$day = $object->GetObject()[2]->GetProcValue("Day");
						$busyness = $object->GetObject()[2]->GetProcValue("LoadBusyness")[$lid][$day] ?? null;

						if (isset($busyness))
							foreach ($busyness as $hour => $_)
								if (!isset($lhour) || $lhour < $hour) $lhour = $hour;

						$hour = $object->GetObject()[0];
						$lnhour = isset($lhour) ? $lhour + 1 : UserConfig::GetParameter("ScheduleStartDefaultHour");

						if ($hour > $lnhour)
							return new PriorityAlgorithmCriterionData($hour - $lnhour, 1 / ($hour - $lnhour) * 0.5);
						else
							return new PriorityAlgorithmCriterionData("-", 1);
					}
				}, */
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Разница между первым незанятым и текущим часами"; }
					public function GetDescription(): string { return "Уменьшает приоритет текущего часа, если он не заполняет существующие пустые пары между предметами, если они есть"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$lhour = UserConfig::GetParameter("ScheduleStartDefaultHour");
						$lid = $algorithm->GetProcValue("MainObj")->GetProcValue("Load")->GetID();
						$day = $algorithm->GetProcValue("MainAlg")->GetProcValue("Day");
						$busyness = $algorithm->GetProcValue("MainAlg")->GetProcValue("LoadBusyness")[$lid][$day] ?? null;

						if (isset($busyness))
							while (isset($busyness[$lhour]))
								$lhour++;

						$hour = $object->GetObject();
						if ($hour > $lhour)
							return new PriorityAlgorithmCriterionData($hour - $lhour, 1 / (($hour - $lhour) * 100));
						else
							return new PriorityAlgorithmCriterionData("-", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Превышение лимита на пары в день (часов)"; }
					public function GetDescription(): string { return "Уменьшает приоритет текущего часа, если превышает номер последнего часа, который является нормой в день"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$max = UserConfig::GetParameter("ScheduleEndDefaultHour");
						$hour = $object->GetObject();

						if ($hour > $max)
							return new PriorityAlgorithmCriterionData($hour - $max, 1 / (($hour - $max) * 100));
						else
							return new PriorityAlgorithmCriterionData("-", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "Разница между первым незанятым и текущим часами преподавателей (общая разница / преподавателей)"; }
					public function GetDescription(): string { return "Если у преподавателей уже установлены часы, увеличивает приоритет, если текущий час идет после последнего установленного, уменьшает в ином случае. Не меняет приоритет, если у преподавателей не установлены часы"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$hours = 0;
						$anyhours = false;
						$lecturers = $algorithm->GetProcValue("MainObj")->GetProcValue("Lecturers");
						$day = $algorithm->GetProcValue("MainAlg")->GetProcValue("Day");
						$hour = $object->GetObject();

						foreach ($lecturers as $lectid)
						{
							$busyness = array();
							$lectbusyness = $algorithm->GetProcValue("MainAlg")->GetProcValue("LecturerBusyness")[$day] ?? null;

							if (isset($lectbusyness))
								foreach ($lectbusyness as $h => $lhours)
									if (isset($lhours[$lectid]))
										$busyness[$h] = true;

							foreach ($algorithm->GetProcValue("MainAlg")->GetProcValue("LecturerWeekBusyness") as $week)
							{
								$lectwbusyness = $week[$day] ?? null;

								if (isset($lectwbusyness))
									foreach ($lectwbusyness as $h => $lhours)
										if (isset($lhours[$lectid]))
											$busyness[$h] = true;
							}

							if (count($busyness) == 0) continue;
							
							$lhour = null;
							foreach ($busyness as $h => $_)
								if (!isset($lhour) || $lhour < $h) $lhour = $h;

							$hours += max(0, $hour - ($lhour + 1));
							$anyhours = $anyhours || $lhours !== null;
						}

						if ($anyhours)
							if ($hours > 0)
								return new PriorityAlgorithmCriterionData($hours." / ".count($lecturers), 1 / (1 + $hours / count($lecturers)));
							else
								return new PriorityAlgorithmCriterionData(0, 2);
						else
							return new PriorityAlgorithmCriterionData("-", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetName(): string { return "(Два часа) Кандидат для создания чередования по неделям"; }
					public function GetDescription(): string { return "Увеличивает приоритет, если предмет подходит для возможного чередования по неделям с другими предметами"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return $algorithm->GetProcValue("Hours") == 2; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						$pair = $algorithm->GetProcValue("MainObj")->GetObject();

						if ($pair->GetHours() % 2 == 1 && SemesterConstructor::IsHoursAvailable($pair, $object->GetObject(), false, false))
						{
							$lid = $pair->GetSubject()->GetLoad()->GetID();

							foreach ($algorithm->GetProcValue("MainAlg")->GetProcValue("LoadHoursLeftData")[$lid]["Subjects"] as $sid => $hours)
								if ($hours % 2 == 1 && $sid != $pair->GetSubject()->GetID() && (new DBLoadSubject($sid))->GetValue()->GetValue("OneHourPrior") == 0)
								{
									$object->SetProcValue("WeekDivided", true);
									return new PriorityAlgorithmCriterionData("Да", 2);
								}
						}
						
						return new PriorityAlgorithmCriterionData("Нет", 1);
					}
				},
				new class extends PriorityAlgorithmCriterion
				{
					public function GetModifierRange(): ?array { return null; }
					public function GetName(): string { return "Час не занят преподавателями или группами"; }
					public function GetDescription(): string { return "Проверяет возможность установки предмета на место текущего часа"; }
					public function ShouldApply(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): bool { return true; }
					public function ApplyCriterion(PriorityAlgorithmObject $object, PriorityAlgorithm $algorithm): PriorityAlgorithmCriterionData|bool|null
					{
						if ($algorithm->GetProcValue("Hours") == 2)
							return $object->GetProcValue("WeekDivided") !== null || SemesterConstructor::IsHoursAvailable($algorithm->GetProcValue("MainObj")->GetObject(), $object->GetObject(), false);

						return SemesterConstructor::IsHoursAvailable($algorithm->GetProcValue("MainObj")->GetObject(), $object->GetObject(), true);
					}
				},
			));

			foreach (self::$algorithm->GetCriterions() as $id => $criterion)
				if ($criterion->GetModifierRange() !== null && ($mod = $_COOKIE["editor-semesterschedule-config-main-$id"] ?? null) !== null)
					$criterion->SetModifier($mod);

			foreach (self::$hcountalg->GetCriterions() as $id => $criterion)
				if ($criterion->GetModifierRange() !== null && ($mod = $_COOKIE["editor-semesterschedule-config-hourcount-$id"] ?? null) !== null)
					$criterion->SetModifier($mod);
			
			foreach (self::$hnumalg->GetCriterions() as $id => $criterion)
				if ($criterion->GetModifierRange() !== null && ($mod = $_COOKIE["editor-semesterschedule-config-hournum-$id"] ?? null) !== null)
					$criterion->SetModifier($mod);
		}
	}
	SemesterConstructor::InitializeAlgorithm();
?>