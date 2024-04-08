<?php
	use ScheduleControl\Core\{DataBase, DBTableRegistryCollections as Collections};
	use ScheduleControl\Logic\DBLecturer;
	use ScheduleControl\Utils;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("ScheduleRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$lecturer = new DBLecturer(AJAXRequest::GetIntParameter("lecturer"));

		if (!$lecturer->IsValid())
			AJAXRequest::ClientError(0, "Некорректный преподаватель");

		$unavs = array();

		foreach (Collections::GetCollection("NotAvailableLecturers")->GetAllLastChanges(array("Lecturer" => $lecturer->GetID())) as $unav)
		{
			if (!isset($unavs[$id = $unav->GetData()->GetValue("NotAvalID")]))
				$unavs[$id] = array(
					"comment" => "",
					"count" => 0,
				);

			$unavs[$id]["count"]++;

			if (mb_strlen($comment = $unav->GetData()->GetValue("Comment")) > mb_strlen($unavs[$id]["comment"]))
				$unavs[$id]["comment"] = $comment;

			$start = new DateTime($unav->GetData()->GetValue("StartDate"));
			if (!isset($unavs[$id]["start"]) || $start < $unavs[$id]["start"])
				$unavs[$id]["start"] = $start;

			$end = new DateTime($unav->GetData()->GetValue("EndDate"));
			if (!isset($unavs[$id]["end"]) || $end < $unavs[$id]["end"])
				$unavs[$id]["end"] = $end;
		}

		$response = array();

		foreach ($unavs as $id => $unav)
			$response[] = array(
				"id" => $id,
				"count" => $unav["count"],
				"start" => Utils::SqlDate($unav["start"]),
				"end" => Utils::SqlDate($unav["end"]),
				"comment" => $unav["comment"],
			);

		AJAXRequest::Response($response);
	}, function($data) {
		AJAXRequest::CheckUserAccess("ScheduleWrite");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$lecturer = new DBLecturer(AJAXRequest::GetIntParameter("lecturer"));

		if (!$lecturer->IsValid())
			AJAXRequest::ClientError(0, "Некорректный преподаватель");

		if (!isset($data["from"], $data["to"], $data["period"], $data["comment"]))
			AJAXRequest::ClientError(1, "Некорректные данные для добавления");

		$from = new DateTime($data["from"]);
		$to = new DateTime($data["to"]);
		$diff = ($to->getTimestamp() - $from->getTimestamp()) / 60 / 60;

		if ($diff < 1)
			AJAXRequest::ClientError(2, "Разница между началом и окончанием действия должна быть не менее 1 часа");

		$collection = Collections::GetCollection("NotAvailableLecturers");
		$nextid = 0;

		while (count($collection->GetAllLastChanges(array("NotAvalID" => $nextid), 1)) > 0)
			$nextid++;

		if ($data["period"])
		{
			if (!isset($data["first"], $data["firstend"], $data["length"]) || $data["length"] == 2 && !isset($data["custom"]))
				AJAXRequest::ClientError(1, "Некорректные данные для добавления");

			$interval = new DateInterval($data["length"] == 0 ? "P7D" : ($data["length"] == 1 ? "P1D" : "PT".((int)$data["custom"])."H"));

			$intstart = new DateTime();
			$intend = new DateTime();
			date_add($intend, $interval);

			if (($intend->getTimestamp() - $intstart->getTimestamp()) / 60 / 60 < 1)
				AJAXRequest::ClientError(3, "Периодичность должна быть не менее 1 часа");

			$first = new DateTime($data["first"]);
			$end = new DateTime($data["firstend"]);

			if (($end->getTimestamp() - $first->getTimestamp()) / 60 / 60 < 1)
				AJAXRequest::ClientError(4, "Разница между началом и окончанием первого отсутствия должна быть не менее 1 часа");

			$count = 0;

			while($first <= $to && $end >= $from)
			{
				$collection->AddChange(array(null, $nextid), array(
					"Lecturer" => $lecturer->GetID(),
					"StartDate" => Utils::SqlDate($first),
					"EndDate" => Utils::SqlDate($end),
					"Comment" => $count == 0 ? $data["comment"] : "",
				));

				date_add($first, $interval);
				date_add($end, $interval);
				$count++;
			}

			AJAXRequest::Response(array("count" => $count));
		}
		else
		{
			$collection->AddChange(array(null, $nextid), array(
				"Lecturer" => $lecturer->GetID(),
				"StartDate" => Utils::SqlDate($from),
				"EndDate" => Utils::SqlDate($to),
				"Comment" => $data["comment"],
			));

			AJAXRequest::Response(array("count" => 1));
		}
	});
?>