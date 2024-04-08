<?php
	use ScheduleControl\Utils;
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use ScheduleControl\Logic\{Constructor, DBGroup, Utilities};

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$date = AJAXRequest::GetDateTimeParameter("date");
		$group = new DBGroup(AJAXRequest::GetIntParameter("group"));

		if (!$group->IsValid())
			AJAXRequest::ClientError(0, "Некорректная группа");

		$info = Constructor::GetGroupInfo($group, $date);
		if (!isset($info))
			AJAXRequest::ClientError(1, "Статистика не сформирована. Группа, возможно, не обучается в данный момент");

		[$start, $end] = Utilities::GetActivityStartEnd($info["Activity"], Utilities::GetStartYear($date));

		$response = array(
			"general" => array(
				"Наименование группы" => $group->GetFullName(),
				"Обучение по субботам" => $info["Saturdays"] ? "Да" : "Нет",
				"Нагрузки" => Utils::ConcatArray(array_map(function($load) { return $load->GetName(); }, $info["Loads"]), ", "),
				"Текущий курс" => $info["Course"],
				"Текущий семестр" => ($info["Course"] - 1) * 2 + $info["Semester"],
				"Текущая деятельность" => $info["Activity"]->GetActivity()->GetFullName(),
				"Первый день деятельности" => $start->format("d.m.Y"),
				"Последний день деятельности" => $end->format("d.m.Y"),
				"Всего дней на деятельность" => $info["ActivityDays"],
				"Пройдено дней деятельности" => $info["ActivityDays"] - $info["ActivityDaysLeft"],
				"Осталось дней на деятельность" => $info["ActivityDaysLeft"],
				"Процент прохождения деятельности" => floor((1 - $info["ActivityDaysLeft"] / $info["ActivityDays"]) * 100)."%",
			),
			"subjects" => array(),
		);

		foreach ($info["Subjects"] as $sid => $subject)
			$response["subjects"][] = array(
				"name" => $subject->GetAbbreviation(),
				"fullname" => $subject->GetName(),
				"hours" => $subject->GetHours(),
				"passedhours" => $subject->GetHours() - $info["SubjectLeftHours"][$sid],
				"lefthours" => $info["SubjectLeftHours"][$sid],
				"percent" => floor((1 - $info["SubjectLeftHours"][$sid] / $subject->GetHours()) * 100),
				"hoursperday" => $info["ActivityDaysLeft"] == 0 ? 0 : round($info["SubjectLeftHours"][$sid] / $info["ActivityDaysLeft"], 2),
			);

		usort($response["subjects"], function($a, $b) {
			if ($a["percent"] != $b["percent"])
				return $a["percent"] <=> $b["percent"];

			if ($a["hoursperday"] != $b["hoursperday"])
				return $b["hoursperday"] <=> $a["hoursperday"];

			if ($a["lefthours"] != $b["lefthours"])
				return $b["lefthours"] <=> $a["lefthours"];

			return $a["name"] <=> $b["name"];
		});

		$sum = array(
			"name" => "ВСЕГО",
			"fullname" => "ВСЕГО",
			"hours" => 0,
			"passedhours" => 0,
			"lefthours" => 0,
			"percent" => 0,
			"hoursperday" => 0,
		);

		foreach ($response["subjects"] as $subject)
		{
			$sum["hours"] += $subject["hours"];
			$sum["passedhours"] += $subject["passedhours"];
			$sum["lefthours"] += $subject["lefthours"];
			$sum["percent"] += $subject["percent"];
			$sum["hoursperday"] += $subject["hoursperday"];
		}

		if (($count = count($response["subjects"])) > 0)
			$sum["percent"] = floor($sum["percent"] / $count);

		$sum["hoursperday"] = round($sum["hoursperday"], 2);

		$response["subjects"][] = $sum;

		AJAXRequest::Response($response);
	});
?>