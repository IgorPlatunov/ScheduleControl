<?php
	use ScheduleControl\Logic\{YearGraph, YearGraphConstructor, DBGroup, DBActivity, Utilities};
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use ScheduleControl\Core\Logs;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("YearGraphRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$year = AJAXRequest::GetIntParameter("year", false) ?? false;

		if ($year)
		{
			$build = AJAXRequest::GetBoolParameter("build", false) ?? false;

			$graph = null;

			if ($build)
			{
				AJAXRequest::CheckUserAccess("YearLoadWrite");

				$graph = YearGraphConstructor::GetFromCurriculums($year);
			}
			else
			{
				$graph = YearGraph::GetFromDatabase($year);
				if (!isset($graph)) AJAXRequest::ClientError(0, "График на год не найден");
			}

			$response = $graph->ToJSON(true);
			usort($response["groups"], function($g1, $g2) {
				$em1 = (int)DBGroup::FromJSON($g1["group"])->GetValue()->GetValue("Extramural");
				$em2 = (int)DBGroup::FromJSON($g2["group"])->GetValue()->GetValue("Extramural");

				return $em1 == $em2 ? $g1["course"] <=> $g2["course"] : $em1 <=> $em2;
			});

			AJAXRequest::Response($response);
		}
		else
		{
			$years = array();
			$cache = array();

			foreach (Collections::GetCollection("YearGraphsActivities")->GetAllLastChanges() as $activity)
			 if (!isset($cache[$year = $activity->GetData()->GetValue("Year")]))
				$years[] = $cache[$year] = $year;

			sort($years);

			AJAXRequest::Response($years);
		}
	},
	function($data) {
		AJAXRequest::CheckUserAccess("YearGraphWrite");

		$type = AJAXRequest::GetParameter("type");
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		if ($type == "delete")
		{
			$year = AJAXRequest::GetIntParameter("year");
			
			$graph = YearGraph::GetFromDatabase($year);
			if (!isset($graph)) AJAXRequest::ClientError(1, "График на год не найден");

			$graph->DeleteFromDatabase();

			Logs::Write("Удалён график образовательного процесса на ".$graph->GetYear()." год (пользователь: ".Session::CurrentUser()->GetLogin().")");
		}
		else if ($type == "check" || $type == "save")
		{
			$graph = YearGraph::FromJSON($data);

			if (count($graph->GetGroups()) == 0)
				AJAXRequest::ClientError(2, "График не имеет групп");

			$weeks = Utilities::GetYearWeeks($graph->GetYear());

			$lastdate = new DateTime();
			$lastdate->setDate($graph->GetYear() + 1, 9, 1);
			$lastdate->setTime(0, 0);
			$lastweek = Utilities::GetWeekNumber($weeks, $lastdate) ?? count($weeks);

			foreach ($graph->GetGroups() as $gid => $group)
			{
				if (!($gobj = new DBGroup($gid))->IsValid())
					AJAXRequest::ClientError(3, "График имеет некорректную группу");

				if (count($group->GetActivities()) == 0)
					AJAXRequest::ClientError(4, "Группа ".$gobj->GetName()." не имеет деятельностей");

				$nextweek = Utilities::GetWeekNumber($weeks, $weeks[0]);

				foreach ($group->GetActivities() as $aid => $activity)
				{
					if (!$activity->GetActivity()->IsValid())
						AJAXRequest::ClientError(5, "Группа ".$gobj->GetName()." имеет некорректную деятельность #".($aid + 1));

					if ($activity->GetWeek() - $nextweek < -0.001)
						AJAXRequest::ClientError(5, "Деятельность #".($aid + 1)." группы ".$gobj->GetName()." имеет некорректное начало");

					if ($aid == count($group->GetActivities()) - 1)
						if ($lastweek - ($activity->GetWeek() + $activity->GetLength()) < -0.001)
							AJAXRequest::ClientError(6, "Последняя деятельность группы ".$gobj->GetName()." имеет некорректное окончание");

					$nextweek = $activity->GetWeek() + $activity->GetLength();
				}
			}

			$discrepancy = YearGraphConstructor::GetCurriculumsDiscrepancy($graph);

			if ($type == "save" && count($discrepancy) == 0)
			{
				$graph->SaveToDatabase();

				Logs::Write("Сохранён график образовательного процесса на ".$graph->GetYear()." год (пользователь: ".Session::CurrentUser()->GetLogin().")");
			}

			$output = array();

			foreach ($discrepancy as $gid => $acts)
				foreach ($acts as $aid => $diff)
					$output[] = array(
						"group" => (new DBGroup($gid))->ToJSON(true),
						"activity" => (new DBActivity($aid))->ToJSON(true),
						"required" => round($diff["required"] * 7),
						"current" => round($diff["current"] * 7),
					);

			AJAXRequest::Response($output);
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>