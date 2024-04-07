<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\{Core\DBTableRegistryCollections as Collections};
	use DateTime;

	final class YearGraphConstructor
	{
		static function GetFromCurriculums(int $year): YearGraph
		{
			$colgroups = Collections::GetCollection("Groups");
			$colcurracts = Collections::GetCollection("CurriculumActivities");

			$graph = new YearGraph($year);

			foreach ($colgroups->GetAllLastChanges() as $gdata)
			{
				$group = new DBGroup($gdata->GetData()->GetValue("ID"));

				$course = Utilities::GetGroupCourse($group, $year);
				if (!isset($course)) continue;

				$curriculum = $group->GetCurriculum();
				if (!$curriculum->IsValid()) continue;

				$acts = $colcurracts->GetAllLastChanges(array("Curriculum" => $curriculum->GetID(), "Course" => $course));
				if (count($acts) == 0) continue;

				foreach ($acts as $act)
				{
					$activity = new DBActivity($act->GetData()->GetDBValue("Activity"));
					if (!$activity->IsValid()) continue;

					$graph->GetGroup($group, true)->AddActivity(
						null,
						$activity,
						$act->GetData()->GetValue("Week"),
						$act->GetData()->GetValue("Length"),
						$act->GetData()->GetValue("Semester"),
					);
				}
			}

			return $graph;
		}

		static function GetCurriculumsDiscrepancy(YearGraph $graph): array
		{
			$curgraph = self::GetFromCurriculums($graph->GetYear());
			$discrepancy = array();

			foreach ($curgraph->GetGroups() as $gid => $ggraph)
				if ($graph->GetGroup($group = new DBGroup($gid)) === null)
					$discrepancy[$gid] = $ggraph->GetActivitiesLengths();
				else
				{
					$acts = $graph->GetGroup($group)->GetActivitiesLengths();
					$curacts = $ggraph->GetActivitiesLengths();

					foreach ($curacts as $aid => $length)
					{
						$diff = $length - ($acts[$aid] ?? 0);
						if (abs(round($diff) - $diff) < 0.01) $diff = round($diff);

						if ($diff != 0)
							$discrepancy[$gid][$aid] = array("current" => $acts[$aid] ?? 0, "required" => $length);
					}

					foreach ($acts as $aid => $length)
						if (!isset($curacts))
							$discrepancy[$gid][$aid] = array("current" => $length, "required" => 0);
				}

			foreach ($graph->GetGroups() as $gid => $ggraph)
				if ($curgraph->GetGroup(new DBGroup($gid)) === null)
					foreach ($ggraph->GetActivitiesLengths() as $aid => $length)
						$discrepancy[$gid][$aid] = array("current" => $length, "required" => 0);

			return $discrepancy;
		}
	}
?>