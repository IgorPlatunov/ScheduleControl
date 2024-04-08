<?php
	use ScheduleControl\Logic\{DBLoadSubject, YearGroupLoadSubjectLecturer};
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$subject = new DBLoadSubject(AJAXRequest::GetIntParameter("subject"));

		if (!$subject->IsValid()) AJAXRequest::ClientError(0, "Некорректный предмет нагрузки");

		$lecturers = array();

		foreach ($subject->GetLecturers() as $subjlect)
		{
			$subjlecturer = YearGroupLoadSubjectLecturer::GetFromDatabase($subjlect);
			if (!isset($subjlecturer)) continue;
			
			$data = $subjlecturer->ToJSON(true);
			$data["incount"] = $subjlect->IsCountedLecturer(false);

			$lecturers[] = $data;
		}

		AJAXRequest::Response($lecturers);
	});
?>