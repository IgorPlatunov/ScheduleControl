<?php
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use ScheduleControl\Logic\{DBArea, DBGroup, DBLecturer, DBLoad, Schedule, SemesterDayLoadSchedulePairType, SemesterSchedule, Utilities, YearGraph, YearGroupLoad};
	use ScheduleControl\{UserConfig, Utils};

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::ClientError(0, "Запрос GET не поддерживается, используйте POST");
	}, function($data) {
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$type = AJAXRequest::GetParameter("type");

		if ($type == "schedule")
		{
			$schedule = Schedule::FromJSON($data);
			$response = array();

			foreach (Collections::GetCollection("Areas")->GetAllLastChanges() as $adata)
			{
				$area = new DBArea($adata->GetData()->GetValue("ID"));
				$areainfo = array(
					"title" => "Расписание учебных занятий (".$area->GetName().")",
					"date" => Utils::SqlDate($schedule->GetDate(), true),
					"rows" => array(),
				);

				$groups = array();
				foreach ($schedule->GetGroups() as $gid => $gschedule)
					if (($group = new DBGroup($gid))->IsValid() && $group->GetArea()?->GetID() == $area->GetID())
						$groups[] = array("group" => $group, "schedule" => $gschedule);

				usort($groups, function($a, $b) {
					$aextramural = $a["group"]->GetValue()->GetValue("Extramural");
					$bextramural = $b["group"]->GetValue()->GetValue("Extramural");

					if ($aextramural == $bextramural)
						return $a["group"]->GetName() <=> $b["group"]->GetName();
					else
						$aextramural <=> $bextramural;
				});

				$perrow = min(UserConfig::GetParameter("ScheduleExportGroupsPerRow"), count($groups));
				for ($row = 0; isset($groups[$row * $perrow]); $row++)
				{
					$rowdata = array("groups" => array(), "pairs" => array());
					$maxpair = UserConfig::GetParameter("ScheduleExportMinPairs");

					for ($i = $row * $perrow; $i < ($row + 1) * $perrow; $i++)
					{
						$group = $groups[$i] ?? null;

						if (isset($group))
						{
							foreach ($group["schedule"]->GetHours() as $h => $hour)
								if ($maxpair < ($pair = floor($h + 1) / 2)) $maxpair = $pair;

							$rowdata["groups"][] = $group["group"]->GetName();
						}
						else
							$rowdata["groups"][] = "";
					}

					$glhours = array();

					for ($p = floor((UserConfig::GetParameter("ScheduleStartReserveHour") + 1) / 2); $p <= $maxpair; $p++)
					{
						$pdata = array("num" => $p, "hours" => array());
						$fbells = null;

						for ($h = max(UserConfig::GetParameter("ScheduleStartReserveHour"), $p * 2 - 1); $h <= $p * 2; $h++)
						{
							$hdata = array("num" => $h, "bells" => "", "groups" => array());
							$bells = Collections::GetCollection("BellsScheduleTimes")->GetLastChange(array($schedule->GetBellsSchedule()->GetID(), $h));

							if (isset($fbells) && $fbells->GetData()->GetValue("EndTime") == $bells->GetData()->GetValue("StartTime"))
							{
								$hdata["bells"] = null;
								$pdata["hours"][$h - 1]["bells"] = (new DateTime($fbells->GetData()->GetValue("StartTime")))->format("H:i")." - ".(new DateTime($bells->GetData()->GetValue("EndTime")))->format("H:i");
							}
							else
								$hdata["bells"] = isset($bells) ? (new DateTime($bells->GetData()->GetValue("StartTime")))->format("H:i")." - ".(new DateTime($bells->GetData()->GetValue("EndTime")))->format("H:i") : "";

							$fbells = $bells;

							for ($i = $row * $perrow; $i < ($row + 1) * $perrow; $i++)
							{
								$group = $groups[$i] ?? null;
								$hour = isset($group) ? $group["schedule"]?->GetHour($h) : null;
								$hgdata = array("occupation" => "", "rooms" => "");

								if (isset($hour))
								{
									$hgdata["occupation"] = $hour->GetOccupationName();
									$hgdata["rooms"] = array();

									foreach ($hour->GetLecturerRooms() as $lectroom)
										if (array_search($room = $lectroom->GetRoom()->GetName(), $hgdata["rooms"]) === false)
											$hgdata["rooms"][] = $room;

									$hgdata["rooms"] = Utils::ConcatArray($hgdata["rooms"], "\n");
								}

								if (isset($glhours[$i]) && $glhours[$i]["occupation"] == $hgdata["occupation"] && $glhours[$i]["rooms"] == $hgdata["rooms"])
									$hdata["groups"][] = null;
								else
									$hdata["groups"][] = $glhours[$i] = $hgdata;
							}

							$pdata["hours"][] = $hdata;
						}

						$rowdata["pairs"][] = $pdata;
					}

					$areainfo["rows"][] = $rowdata;
				}

				$response[] = $areainfo;
			}

			AJAXRequest::Response($response);
		}
		else if ($type == "lectschedule")
		{
			$schedule = Schedule::FromJSON($data);
			$response = array("lecturers" => array(), "date" => Utils::SqlDate($schedule->GetDate(), true), "pairs" => array());
			$pairs = array();

			foreach (Collections::GetCollection("Lecturers")->GetAllLastChanges() as $ldata)
			{
				$lid = $ldata->GetData()->GetValue("ID");
				$lectdata = array();
				$hours = array();
				$hourc = 0;

				foreach ($schedule->GetGroups() as $gid => $gschedule)
					foreach ($gschedule->GetHours() as $h => $hour)
						foreach ($hour->GetLecturerRooms() as $lroom)
							if ($lroom->GetLecturer()->GetID() == $lid)
							{
								$pnum = floor(($h + 1) / 2);
								$lectdata[$pnum][$h][$gid] = true;

								for ($i = 0; $i < 2; $i++)
									$hours[max(UserConfig::GetParameter("ScheduleStartReserveHour"), $pnum * 2 - $i)] = $pnum;
							}

				$lresponse = array("lecturer" => (new DBLecturer($lid))->GetName(), "pairs" => array());
				ksort($hours);

				foreach ($lectdata as $p => $phours)
				{
					$pairs[$p] = true;
					$lhourgroup = null;
					$phourc = 0;

					foreach ($hours as $h => $hp)
					{
						if ($p != $hp) continue;

						$str = "";
						$phourc++;

						if (isset($phours[$h]))
						{
							$groups = array();

							foreach ($phours[$h] as $gid => $_)
								$groups[] = (new DBGroup($gid))->GetName();

							$str = Utils::ConcatArray($groups, ", ");
						}

						if (isset($lhourgroup) && $str == $lhourgroup)
							$lresponse["pairs"][$p][] = null;
						else
							$lresponse["pairs"][$p][] = $lhourgroup = $str;
					}

					$hourc = max($hourc, $phourc);
				}

				$lresponse["hours"] = max(1, $hourc);
				$response["lecturers"][] = $lresponse;
			}
			
			foreach ($response["lecturers"] as $id => $lresponse)
			{
				foreach ($pairs as $p => $_)
					if (!isset($lresponse["pairs"][$p]))
						for ($i = 0; $i < $lresponse["hours"]; $i++)
							$response["lecturers"][$id]["pairs"][$p][] = $i == 0 ? "" : null;

				for ($i = $lresponse["hours"] - 1; $i >= 0; $i--)
				{
					$empty = true;

					foreach ($response["lecturers"][$id]["pairs"] as $p => $hours)
						if ($hours[$i] !== null) { $empty = false; break; }

					if ($empty)
					{
						$response["lecturers"][$id]["hours"]--;

						foreach ($response["lecturers"][$id]["pairs"] as $p => $hours)
							unset($response["lecturers"][$id]["pairs"][$p][$i]);
					}
				}
			}

			usort($response["lecturers"], function($a, $b) { return $a["lecturer"] <=> $b["lecturer"]; });

			foreach ($pairs as $p => $_)
				$response["pairs"][] = $p;

			sort($response["pairs"]);
			
			AJAXRequest::Response($response);
		}
		else if ($type == "semesterschedule")
		{
			$load = new DBLoad(AJAXRequest::GetIntParameter("load"));
			$schedule = SemesterSchedule::FromJSON($data);

			$response = array("load" => $load->GetName(), "days" => array(), "pairs" => array());

			$subjectToText = function($subject)
			{
				if (!isset($subject)) return "";

				$text = $subject->GetName();
				$lecturers = $subject->GetLecturers();

				if (count($lecturers) > 0)
					$text .= "\n".Utils::ConcatArray(array_map(function($l) { return $l->GetName(); }, $lecturers), "\n");

				return $text;
			};
			
			foreach ($schedule->GetDaySchedules() as $day => $dschedule)
			{
				$lschedule = $dschedule->GetLoadSchedule($load);
				if (!isset($lschedule)) continue;

				$count = 0;

				foreach ($lschedule->GetPairs() as $p => $pair)
				{
					$pdata = $response["pairs"][$p] ?? array("count" => 0, "days" => array());

					if ($pair->GetType() == SemesterDayLoadSchedulePairType::FullPair)
					{
						$count = max($count, 1); $pdata["count"] = max($pdata["count"], 1);
						$pdata["days"][$day][0][0] = $subjectToText($pair->GetSubject());
					}
					else if ($pair->GetType() == SemesterDayLoadSchedulePairType::FirstHour || $pair->GetType() == SemesterDayLoadSchedulePairType::SecondHour)
					{
						$count = max($count, 1); $pdata["count"] = max($pdata["count"], 2);
						$pdata["days"][$day][$pair->GetType() == SemesterDayLoadSchedulePairType::FirstHour ? 0 : 1][0] = $subjectToText($pair->GetSubject());
						$pdata["days"][$day][$pair->GetType() == SemesterDayLoadSchedulePairType::SecondHour ? 0 : 1][0] = "";
					}
					else if ($pair->GetType() == SemesterDayLoadSchedulePairType::HoursDivided)
					{
						$count = max($count, 1); $pdata["count"] = max($pdata["count"], 2);
						$pdata["days"][$day][0][0] = $subjectToText($pair->GetSubject());
						$pdata["days"][$day][1][0] = $subjectToText($pair->GetSubject2());
					}
					else if ($pair->GetType() == SemesterDayLoadSchedulePairType::WeeksDivided)
					{
						$count = max($count, 2); $pdata["count"] = max($pdata["count"], 1);
						$pdata["days"][$day][0][0] = $subjectToText($pair->GetSubject());
						$pdata["days"][$day][0][1] = $subjectToText($pair->GetSubject2());
					}

					$response["pairs"][$p] = $pdata;
				}

				$response["days"][$day] = $count;
			}

			foreach ($response["days"] as $d => $c)
				foreach ($response["pairs"] as $p => $pair)
					if (!isset($pair["days"][$d]))
						for ($pc = 0; $pc < $pair["count"]; $pc++)
							for ($dc = 0; $dc < $c; $dc++)
								$response["pairs"][$p]["days"][$d][$pc][$dc] = $pc == 0 && $dc == 0 ? "" : null;
					else
						foreach ($pair["days"][$d] as $r => $row)
							for ($dc = 0; $dc < $c; $dc++)
								if (!isset($row[$dc]))
									$response["pairs"][$p]["days"][$d][$r][$dc] = $r == 0 && $dc == 0 ? "" : null;

			foreach ($response["pairs"] as $p => $pair)
				foreach ($pair["days"] as $d => $rows)
					for ($pc = 0; $pc < $pair["count"]; $pc++)
						if (!isset($rows[$pc]))
							for ($dc = 0; $dc < $response["days"][$d]; $dc++)
								$response["pairs"][$p]["days"][$d][$pc][$dc] = $pc == 0 && $dc == 0 ? "" : null;

			AJAXRequest::Response($response);
		}
		else if ($type == "yearload")
		{
			$load = YearGroupLoad::FromJSON($data);
			$response = array("name" => $load->GetName(), "semesters" => array(), "subjects" => array());
			$subjects = array();

			foreach ($load->GetSubjects() as $subject)
			{
				$dataid = null;

				foreach ($subjects as $id => $subj)
					if ($subj["name"] == $subject->GetName())
						{ $dataid = $id; break; }

				if (!isset($dataid))
				{
					$subjects[] = array("name" => $subject->GetName(), "lecturers" => array(), "semesters" => array());
					$dataid = count($subjects) - 1;
				}

				foreach ($subject->GetLecturers() as $lecturer)
					$subjects[$dataid]["lecturers"][$lecturer->GetLecturer()->GetName()] = $lecturer->IsMain() ? 1 : 0;

				$subjects[$dataid]["semesters"][$subject->GetSemester()] = array(
					"hours" => $subject->GetHours() == 0 ? "" : $subject->GetHours(),
					"whours" => $subject->GetWHours() == 0 ? "" : $subject->GetWHours(),
					"lphours" => $subject->GetLPHours() == 0 ? "" : $subject->GetLPHours(),
					"cdhours" => $subject->GetCDHours() == 0 ? "" : $subject->GetCDHours(),
					"cdphours" => $subject->GetCDHours() == 0 ? "" : $subject->GetCDPHours(),
					"ehours" => $subject->GetEHours() == 0 ? "" : $subject->GetEHours(),
				);
				$response["semesters"][$subject->GetSemester()] = true;
			}

			$response["semesters"] = array_keys($response["semesters"]);
			sort($response["semesters"]);
			usort($subjects, function($a, $b) { return $a["name"] <=> $b["name"]; });

			foreach ($subjects as $subject)
			{
				$sum = 0;

				foreach ($subject["semesters"] as $semester)
					$sum += $semester["hours"] ?? 0;

				uksort($subject["lecturers"], function($a, $b) use($subject) {
					$av = $subject["lecturers"][$a];
					$bv = $subject["lecturers"][$b];

					return $av == $bv ? $a <=> $b : $bv <=> $av;
				});
				$lecturers = array_keys($subject["lecturers"]);

				$sresponse = array("name" => $subject["name"], "lecturers" => Utils::ConcatArray($lecturers, "\n"), "hours" => $sum, "semesters" => $subject["semesters"]);

				foreach ($response["semesters"] as $semester)
					if (!isset($subject["semesters"][$semester]))
						$sresponse["semesters"][$semester] = array("hours" => "", "whours" => "", "lphours" => "", "cdhours" => "", "cdphours" => "", "ehours" => "");

				$response["subjects"][] = $sresponse;
			}

			AJAXRequest::Response($response);
		}
		else if ($type == "yeargraph")
		{
			$graph = YearGraph::FromJSON($data);

			$wdays = [0, 2, 5];
			$response = array("weeks" => array(), "groups" => array(), "wdays" => count($wdays), "design" => array());
			$groups = array();
			$designs = array();

			$weeks = Utilities::GetYearWeeks($graph->GetYear());

			foreach ($graph->GetGroups() as $gid => $gdata)
			{
				$group = new DBGroup($gid);
				if (!$group->IsValid()) continue;

				$course = Utilities::GetGroupCourse($group, $graph->GetYear());
				if (!isset($course)) continue;

				$response["groups"][] = array("group" => $group->GetName(), "course" => $course);
				$groups[] = $group;
			}

			foreach ($weeks as $id => $week)
			{
				$month = (int)$week->format("m");
				$wyear = (int)$week->format("Y");
				$month = $wyear == $graph->GetYear() ? max($month, 9) : min($month, 8);
				$weekd = array("days" => array(), "month" => $month, "groups" => array());

				foreach ($wdays as $wday)
				{
					$date = clone $week;
					date_add($date, new DateInterval("P".$wday."D"));

					$weekd["days"][] = $date->format("d");
				}

				foreach ($groups as $group)
				{
					$acts = array();
					$start = null;
					$end = null;
					
					foreach ($graph->GetGroup($group)->GetActivities() as $activity)
						if (($aend = $activity->GetWeek() + $activity->GetLength()) > $id && ($astart = $activity->GetWeek()) < $id + 1)
						{
							$start = isset($start) ? min($start, $astart) : $astart;
							$end = isset($end) ? max($end, $aend) : $aend;
							$acts[] = $actname = $activity->GetActivity()->GetName();

							if (!isset($designs[$actname]))
								$designs[$actname] = $activity->GetActivity()->GetFullName();
						}

					if (!isset($start) || $start > $id) array_unshift($acts, "");
					if (!isset($end) || $end < $id + 1) $acts[] = "";

					$weekd["groups"][] = Utils::ConcatArray($acts, "/");
				}

				$response["weeks"][] = $weekd;
			}

			asort($designs);

			foreach ($designs as $design => $name)
				$response["design"][] = array($name, $design);

			AJAXRequest::Response($response);
		}

		AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>