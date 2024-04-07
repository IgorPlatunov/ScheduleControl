<?php
	namespace ScheduleControl\Logic;
	use ScheduleControl\Core\DBTableRegistryCollections as Collections;
	use DateTime, RuntimeException;

	final class YearGraph implements JSONAdaptive, DatabaseAdaptive
	{
		use DatabaseTransaction;

		private array $groups = array();

		public function __construct(private int $year) {}
		public function GetYear(): int { return $this->year; }

		public function GetGroups(): array { return $this->groups; }
		public function GetGroup(DBGroup $group, bool $create = false): ?YearActivities
		{ return $this->groups[$group->GetID()] ?? ($create ? $this->AddGroup($group) : null); }

		public function AddGroup(DBGroup $group): YearActivities
		{ return $this->groups[$group->GetID()] = new YearActivities(); }

		public function SetGroup(DBGroup $group, YearActivities $activities): void
		{ $this->groups[$group->GetID()] = $activities; }

		public function ToJSON(bool $names = false): array
		{
			$json = array(
				"year" => $this->year,
				"groups" => array(),
			);

			foreach ($this->groups as $gid => $activities)
			{
				$gobj = new DBGroup($gid);
				if (!$gobj->IsValid()) continue;

				$gjson = array(
					"group" => $gobj->ToJSON($names),
					"activities" => $activities->ToJSON($names),
				);

				if ($names)
					$gjson["course"] = Utilities::GetGroupCourse($gobj, $this->year);

				$json["groups"][] = $gjson;
			}

			return $json;
		}

		public static function FromJSON(array $data): YearGraph
		{
			$graph = new YearGraph($data["year"]);

			foreach ($data["groups"] as $gdata)
				$graph->SetGroup(DBGroup::FromJSON($gdata["group"]), YearActivities::FromJSON($gdata["activities"]));

			return $graph;
		}

		public function SaveToDB(): void
		{	
			$colacts = Collections::GetCollection("YearGraphsActivities");

			$this->DeleteFromDB();

			foreach ($this->groups as $gid => $activities)
			{
				if (!DBGroup::IsValidValue($gid)) continue;

				foreach ($activities->GetActivities() as $act)
				{
					if (!$act->GetActivity()->IsValid()) continue;

					$aid = $colacts->AddChange(array($act->GetID()), array(
						"Year" => $this->year,
						"Group" => $gid,
						"Activity" => $act->GetActivity()->GetID(),
						"Week" => $act->GetWeek(),
						"Length" => $act->GetLength(),
						"Semester" => $act->GetSemester(),
					));

					if ($act->GetID() === null)
						if (isset($aid)) $act->SetID($aid);
						else throw new RuntimeException("Не удалось получить идентификатор созданной связи деятельности и графика на год (не удалось создать связь?)", 500);
				}
			}
		}

		public function DeleteFromDB(): void
		{
			$colacts = Collections::GetCollection("YearGraphsActivities");

			foreach ($colacts->GetAllLastChanges(array("Year" => $this->year)) as $act)
				$colacts->AddChange($act->GetID(), null);
		}

		public static function GetFromDatabase(mixed $param): ?YearGraph
		{
			$colacts = Collections::GetCollection("YearGraphsActivities");
			$graph = new YearGraph($param);

			foreach ($colacts->GetAllLastChanges(array("Year" => $param)) as $act)
			{
				$group = new DBGroup($act->GetData()->GetDBValue("Group"));
				if (!$group->IsValid()) continue;

				$activity = new DBActivity($act->GetData()->GetDBValue("Activity"));
				if (!$activity->IsValid()) continue;

				$graph->GetGroup($group, true)->AddActivity(
					$act->GetData()->GetValue("ID"),
					$activity,
					$act->GetData()->GetValue("Week"),
					$act->GetData()->GetValue("Length"),
					$act->GetData()->GetValue("Semester"),
				);
			}

			return count($graph->groups) == 0 ? null : $graph;
		}
	}
?>