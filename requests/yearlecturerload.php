<?php
	use ScheduleControl\Logic\{DBLecturer, YearLoad, YearLoadConstructor};
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		AJAXRequest::CheckUserAccess("LoadSubjectLecturerRead");

		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$year = AJAXRequest::GetIntParameter("year");

		$load = YearLoad::GetFromDatabase($year);
		if (!isset($load)) AJAXRequest::ClientError(0, "Нагрузка на год не найдена");

		$lecturer = new DBLecturer(AJAXRequest::GetIntParameter("lecturer"));
		if (!$lecturer->IsValid()) AJAXRequest::ClientError(1, "Преподаватель не найден");

		$lload = YearLoadConstructor::BuildLecturerLoad($load, $lecturer);
		AJAXRequest::Response($lload->ToJSON(true));
	});
?>