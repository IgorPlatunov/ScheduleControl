<?php
	use ScheduleControl\Core\{Logs, DBTableRegistryCollections as Collections};
	use ScheduleControl\Logic\{DBGroup, Utilities};
	use ScheduleControl\UserConfig;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		$name = AJAXRequest::GetParameter("name");
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$offset = AJAXRequest::GetIntParameter("offset", false);
		$limit = AJAXRequest::GetIntParameter("limit", false);

		$special = AJAXRequest::GetIntParameter("specialtype", false) ?? -1;
		$specialinfo = null;

		if ($special >= 0)
		{
			$specialinfo = json_decode(AJAXRequest::GetParameter("specialinfo"), true);
			if (!isset($specialinfo)) AJAXRequest::ClientError(1, "Некорректные данные по специальному запросу");
		}

		$collection = Collections::GetCollection($name);
		if (!isset($collection)) AJAXRequest::ClientError(0, "Коллекция регистров не найдена");

		$response = array(
			"columns" => array(),
			"rows" => array(),
		);

		foreach ($collection->GetTable()->GetColumns() as $cname => $column)
			$response["columns"][] = array(
				"name" => $cname,
				"nicename" => $column->GetNiceName(),
				"inputtype" => $column->GetInputType(),
				"inputlimits" => $column->GetInputLimits(),
				"data" => $collection->IsDataColumn($cname),
				"locked" => $cname == "ID" || $collection->GetDateColumn() == $column,
				"foreign" => $collection->GetTable()->IsForeignKey($cname) ? array(
					"table" => $column->GetForeignInfo()->GetTable()->GetInfo()->GetName(),
					"column" => $column->GetForeignInfo()->GetColumn()->GetName(),
				) : null,
			);

		$special1 = null;
		if (($special == 1 || $special == 3) && isset($specialinfo["group"], $specialinfo["date"]) && ($group = new DBGroup($specialinfo["group"]))->IsValid() && $specialinfo["date"])
			$special1 = array(
				"year" => $year = Utilities::GetStartYear($date = new DateTime($specialinfo["date"])),
				"loads" => array_map(function($load) { return $load->GetID(); }, $group->GetLoads($year)),
				"course" => Utilities::GetGroupCourse($group, $year),
				"semester" => $semester = Utilities::GetGroupActivity($group, $date)?->GetSemester(),
				"activities" => isset($semester) ? array_map(function($act) { return $act->GetActivity()->GetID(); }, Utilities::GetGroupSemesterActivities($group, $year, $semester)) : array(),
				"area" => $group->GetArea()?->GetID(),
			);

		$special2 = $special == 2 ? new DateTime($specialinfo["date"] ?? "now") : null;

		$rows = $collection->GetAllLastChanges(null, $limit, $offset);
		foreach ($rows as $rowid => $row)
		{
			if ($special == 0)
			{
				if (array_search($row->GetData()->GetValue("ID"), $specialinfo) !== false) continue;
			}
			if (isset($special1))
			{
				if ($name == "YearGroupLoadSubjects" && (array_search($row->GetData()->GetDBValue("Load"), $special1["loads"]) === false || $row->GetData()->GetDBValue("Semester") != $special1["semester"])) continue;
				if ($name == "Activities" && array_search($row->GetData()->GetValue("ID"), $special1["activities"]) === false) continue;
				if ($name == "Rooms" && $row->GetData()->GetDBValue("Area") != $special1["area"]) continue;
			}
			if (isset($special2) && $name == "Groups")
			{
				if (Utilities::GetGroupCourse($group = new DBGroup($row->GetData()->GetValue("ID")), Utilities::GetStartYear($special2)) === null) continue;
				if (Utilities::GetGroupActivity($group, $special2) === null) continue;

				if (array_search($row->GetData()->GetValue("ID"), $specialinfo["groups"] ?? array()) !== false) continue;
			}
			if ($special == 3 && isset($specialinfo["filter"]))
			{
				if (array_search($row->GetData()->GetValue("ID"), $specialinfo["filter"]) !== false) continue;
			}

			$rowdata = array(
				"name" => $row->GetData()->GetName(),
				"fullname" => $row->GetData()->GetFullName(),
				"cells" => array(),
			);

			foreach ($row->GetData()->values as $colname => $value)
				$rowdata["cells"][$colname] = array(
					"dbvalue" => $value->GetDBValue(),
					"foreignname" => $collection->GetTable()->IsForeignKey($colname) ? $value->GetNiceValue() : null,
					"foreignfname" => $collection->GetTable()->IsForeignKey($colname) ? $value->GetValue()?->GetFullName() ?? null : null,
				);

			$response["rows"][] = $rowdata;
		}

		AJAXRequest::Response($response);
	},
	function($data) {
		$name = AJAXRequest::GetParameter("name");
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));

		$collection = Collections::GetCollection($name);
		if (!isset($collection)) AJAXRequest::ClientError(0, "Коллекция регистров не найдена");

		if (!$collection->HasAccess(Session::CurrentUser()))
			AJAXRequest::ClientError(1, "Пользователь не имеет доступа на изменение этой коллекции регистров", 3);

		foreach ($data as $row)
		{
			if (!isset($row["old"]) && !isset($row["new"])) continue;

			if ($name == "Config")
			{
				if (!isset($row["new"])) continue;

				UserConfig::SetParameter($row["new"]["Parameter"], $row["new"]["Value"]);
			}
			else
			{
				$ids = array();

				if (isset($row["new"]["ID"]) && $row["new"]["ID"] === "")
					$row["new"]["ID"] = null;

				if (isset($row["old"]))
					foreach ($collection->GetIDColumns() as $id => $column)
						$ids[$id] = $row["old"][$column->GetName()];
				else
					foreach ($collection->GetIDColumns() as $id => $column)
						$ids[$id] = $row["new"][$column->GetName()];

				$collection->AddChange($ids, $row["new"]);
			}
		}

		Logs::Write("Изменены данные коллекции регистров ".$collection->GetTable()->GetInfo()->GetName()." (пользователь: ".Session::CurrentUser()->GetLogin().")");
	});
?>