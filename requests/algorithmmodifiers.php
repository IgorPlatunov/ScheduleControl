<?php
	use ScheduleControl\Logic\{Constructor, SemesterConstructor, PriorityAlgorithmCriterion};

	require_once("../php/ajaxrequests.php");

	function GetCriterionInfo(PriorityAlgorithmCriterion $criterion): array
	{
		$info = array("name" => $criterion->GetName(), "desc" => $criterion->GetDescription());

		if (($range = $criterion->GetModifierRange()) !== null)
		{
			$info["min"] = $range[0];
			$info["max"] = $range[1];
		}

		return $info;
	}

	AJAXRequest::Proccess(function() {
		$type = AJAXRequest::GetParameter("type");

		if ($type == "semesterschedule")
		{
			AJAXRequest::CheckUserAccess("SemesterScheduleWrite");

			$response = array("main" => array(), "hourcount" => array(), "hournum" => array());

			foreach (SemesterConstructor::$algorithm->GetCriterions() as $id => $criterion)
				$response["main"][$id] = GetCriterionInfo($criterion);

			foreach (SemesterConstructor::$hcountalg->GetCriterions() as $id => $criterion)
				$response["hourcount"][$id] = GetCriterionInfo($criterion);

			foreach (SemesterConstructor::$hnumalg->GetCriterions() as $id => $criterion)
				$response["hournum"][$id] = GetCriterionInfo($criterion);

			AJAXRequest::Response($response);
		}
		elseif ($type == "schedule")
		{
			AJAXRequest::CheckUserAccess("ScheduleWrite");

			$response = array("main" => array(), "occupation" => array(), "hournum" => array());

			foreach (Constructor::$algorithm->GetCriterions() as $id => $criterion)
				$response["main"][$id] = GetCriterionInfo($criterion);

			foreach (Constructor::$occalg->GetCriterions() as $id => $criterion)
				$response["occupation"][$id] = GetCriterionInfo($criterion);

			foreach (Constructor::$houralg->GetCriterions() as $id => $criterion)
				$response["hournum"][$id] = GetCriterionInfo($criterion);

			AJAXRequest::Response($response);
		}
		else
			AJAXRequest::ClientError(0, "Неизвестный тип запроса");
	});
?>