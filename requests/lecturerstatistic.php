<?php
	use ScheduleControl\Utils;
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use ScheduleControl\Logic\{Curriculum, DBGroup, YearLoadConstructor, DBLecturer, DBLoadSubject, Utilities, YearLoad};

	require_once("../php/ajaxrequests.php");

	AJAXRequest::Proccess(function() {
		Collections::SetActualDate(AJAXRequest::GetDateTimeParameter("actdate"));
		$date = AJAXRequest::GetDateTimeParameter("date");
		$lecturer = new DBLecturer(AJAXRequest::GetIntParameter("lecturer"));

		if (!$lecturer->IsValid())
			AJAXRequest::ClientError(0, "Некорректный преподаватель");

		$year = Utilities::GetStartYear($date);

		$yearload = YearLoad::GetFromDatabase($year);
		if (!isset($yearload))
			AJAXRequest::ClientError(1, "Нагрузка на год не найдена");

		$lectload = YearLoadConstructor::BuildLecturerLoad($yearload, $lecturer);

		$response = array(
			"load" => $lectload->ToJSON(true),
			"subjects" => array(),
		);

		$sum = array(
			"subject" => array("id" => -1, "name" => "ВСЕГО", "fullname" => "ВСЕГО"),
			"groups" => array(
				array(
					"group" => array("id" => -1, "name" => "", "fullname" => ""),
					"semesters" => array(
						1 => array(
							"shours" => 0,
							"hours" => 0,
							"whours" => 0,
							"lphours" => 0,
							"cdhours" => 0,
							"cdphours" => 0,
							"ehours" => 0,

							"chours" => 0,
							"diff" => 0,
						),
						2 => array(
							"shours" => 0,
							"hours" => 0,
							"whours" => 0,
							"lphours" => 0,
							"cdhours" => 0,
							"cdphours" => 0,
							"ehours" => 0,

							"chours" => 0,
							"diff" => 0,
						),
					),
				),
			),
		);

		foreach ($response["load"]["subjects"] as $id => &$subject)
			foreach ($subject["groups"] as &$group)
				foreach ($group["semesters"] as $s => &$semester)
				{
					$sum["groups"][0]["semesters"][$s]["shours"] += $semester["shours"];
					$sum["groups"][0]["semesters"][$s]["hours"] += $semester["hours"];
					$sum["groups"][0]["semesters"][$s]["whours"] += $semester["whours"];
					$sum["groups"][0]["semesters"][$s]["lphours"] += $semester["lphours"];
					$sum["groups"][0]["semesters"][$s]["cdhours"] += $semester["cdhours"];
					$sum["groups"][0]["semesters"][$s]["cdphours"] += $semester["cdphours"];
					$sum["groups"][0]["semesters"][$s]["ehours"] += $semester["ehours"];

					$semester["chours"] = 0;

					$curriculum = Curriculum::GetFromDatabase(($gobj = DBGroup::FromJSON($group["group"]))->GetCurriculum());
					if (isset($curriculum) && ($course = Utilities::GetGroupCourse($gobj, $year)) !== null)
						$semester["chours"] = $curriculum->GetSubject((new DBLoadSubject($subject["subject"]["id"]))->GetSubject())?->GetCourse($course)?->GetSemester($s)?->GetHours() ?? 0;

					$semester["diff"] = $semester["shours"] - $semester["chours"];

					$sum["groups"][0]["semesters"][$s]["chours"] += $semester["chours"];
					$sum["groups"][0]["semesters"][$s]["diff"] += $semester["diff"];
				}

		$response["load"]["subjects"][] = $sum;
		unset($subject, $group, $semester);

		foreach ($lectload->GetSubjects() as $subject)
		{
			$subj = array(
				"name" => $subject->GetSubject()->GetName(),
				"fullname" => $subject->GetSubject()->GetFullName(),
				"lefthours" => 0,
				"percent" => 0,
				"groups" => array(),
			);

			foreach ($subject->GetGroups() as $gid => $grp)
			{
				$s = Utilities::GetGroupActivity($group = new DBGroup($gid), $date)?->GetSemester();
				if (!isset($s) || ($semester = $grp->GetSemester($s)) === null) continue;

				$hours = $grp->GetSemester($s)?->GetHours() ?? 0;
				$passedhours = 0;
				[$sstart, $send] = Utilities::GetGroupSemesterStartEnd($group, $year, $s);

				foreach (Collections::GetCollection("ScheduleHourRooms")->GetAllLastChanges(array(
					"Lecturer" => $lecturer->GetID(),
					"Group" => $gid,
				)) as $hlect)
					if (
						($hdate = new DateTime($hlect->GetData()->GetDBValue("Schedule"))) <= $date && $hdate >= $sstart && $hdate <= $send &&
						($hour = Collections::GetCollection("ScheduleHours")->GetLastChange(array(
							$hlect->GetData()->GetDBValue("Schedule"),
							$gid,
							$hlect->GetData()->GetValue("Hour"),
						))) !== null && ($hsubj = new DBLoadSubject($hour->GetData()->GetDBValue("Subject")))->IsValid() &&
						$hsubj->GetName() == $subject->GetSubject()->GetName() && $hsubj->GetFullName() == $subject->GetSubject()->GetFullName()
					)
						$passedhours++;

				$subj["groups"][] = array(
					"name" => $group->GetName(),
					"fullname" => $group->GetFullName(),
					"hours" => $hours,
					"passedhours" => $passedhours,
					"lefthours" => $lefthours = ($hours - $passedhours),
					"percent" => $percent = $hours == 0 ? 0 : floor((1 - $lefthours / $hours) * 100),
				);

				$subj["lefthours"] += $lefthours;
				$subj["percent"] += $percent;
			}

			if (($count = count($subj["groups"])) > 0)
				$subj["percent"] = floor($subj["percent"] / $count);

			usort($subj["groups"], function($a, $b) {
				if ($a["percent"] != $b["percent"])
					return $a["percent"] <=> $b["percent"];
	
				if ($a["lefthours"] != $b["lefthours"])
					return $b["lefthours"] <=> $a["lefthours"];
	
				return $a["name"] <=> $b["name"];
			});

			$response["subjects"][] = $subj;
		}

		usort($response["subjects"], function($a, $b) {
			if ($a["percent"] != $b["percent"])
				return $a["percent"] <=> $b["percent"];

			if ($a["lefthours"] != $b["lefthours"])
				return $b["lefthours"] <=> $a["lefthours"];

			return $a["name"] <=> $b["name"];
		});

		$sum = array(
			"name" => "ВСЕГО",
			"fullname" => "ВСЕГО",
			"groups" => array(
				array(
					"name" => "",
					"fullname" => "",
					"hours" => 0,
					"passedhours" => 0,
					"lefthours" => 0,
					"percent" => 0,
				),
			),
		);

		$gcount = 0;
		foreach ($response["subjects"] as $subject)
			foreach ($subject["groups"] as $group)
			{
				$sum["groups"][0]["hours"] += $group["hours"];
				$sum["groups"][0]["passedhours"] += $group["passedhours"];
				$sum["groups"][0]["lefthours"] += $group["lefthours"];
				$sum["groups"][0]["percent"] += $group["percent"];
				$gcount++;
			}

		if ($gcount > 0)
			$sum["groups"][0]["percent"] = floor($sum["groups"][0]["percent"] / $gcount);

		$response["subjects"][] = $sum;

		AJAXRequest::Response($response);
	});
?>